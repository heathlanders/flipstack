<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\Supplier;
use App\Models\Procurement\PurchaseOrder;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\ReceivingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseOrderService $purchaseOrderService,
        protected ReceivingService $receivingService
    ) {
        $this->middleware(['auth']);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $query = PurchaseOrder::where('organization_id', $organizationId)
            ->with(['supplier:id,name,company_name', 'location:id,name', 'creator:id,name']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'ILIKE', "%{$search}%")
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'ILIKE', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->get('supplier_id'));
        }

        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('direction', 'desc');
        $allowedSorts = ['po_number', 'status', 'order_date', 'expected_delivery_date', 'total_amount', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('created_at');
        }

        $purchaseOrders = $query->paginate(20)->withQueryString();

        $stats = $this->purchaseOrderService->getStats($organizationId);

        $suppliers = Supplier::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->select('id', 'name', 'company_name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Procurement/PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'stats' => $stats,
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'status', 'supplier_id', 'sort', 'direction']),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $suppliers = Supplier::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->select('id', 'name', 'company_name')
            ->orderBy('name')
            ->get();

        $locations = InventoryLocation::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return Inertia::render('Procurement/PurchaseOrders/Create', [
            'suppliers' => $suppliers,
            'locations' => $locations,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'location_id' => ['nullable', 'uuid', 'exists:inventory_locations,id'],
            'order_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'items.*.supplier_product_id' => ['nullable', 'uuid'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.sku' => ['nullable', 'string', 'max:100'],
            'items.*.quantity_ordered' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $po = $this->purchaseOrderService->create($validated, $organizationId, $user->id);

        return redirect()->route('procurement.purchase-orders.show', $po->id)
            ->with('success', "Purchase order {$po->po_number} created.");
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load([
            'items.product:id,name,sku,featured_image',
            'items.supplierProduct',
            'supplier:id,name,company_name,email,phone',
            'location:id,name,code',
            'creator:id,name',
            'approver:id,name',
            'receipts.items',
            'receipts.receivedByUser:id,name',
        ]);

        return Inertia::render('Procurement/PurchaseOrders/Show', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder, Request $request): Response
    {
        if (! $purchaseOrder->canBeEdited()) {
            return redirect()->route('procurement.purchase-orders.show', $purchaseOrder->id)
                ->with('error', 'This purchase order cannot be edited.');
        }

        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $purchaseOrder->load(['items.product:id,name,sku', 'supplier', 'location']);

        $suppliers = Supplier::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->select('id', 'name', 'company_name')
            ->orderBy('name')
            ->get();

        $locations = InventoryLocation::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return Inertia::render('Procurement/PurchaseOrders/Edit', [
            'purchaseOrder' => $purchaseOrder,
            'suppliers' => $suppliers,
            'locations' => $locations,
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'location_id' => ['nullable', 'uuid', 'exists:inventory_locations,id'],
            'order_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'uuid'],
            'items.*.product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'items.*.quantity_ordered' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->purchaseOrderService->update($purchaseOrder, $validated);

        return redirect()->route('procurement.purchase-orders.show', $purchaseOrder->id)
            ->with('success', 'Purchase order updated.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): \Illuminate\Http\RedirectResponse
    {
        if (! $purchaseOrder->isDraft()) {
            return back()->with('error', 'Only draft purchase orders can be deleted.');
        }

        $poNumber = $purchaseOrder->po_number;
        $purchaseOrder->delete();

        return redirect()->route('procurement.purchase-orders.index')
            ->with('success', "Purchase order {$poNumber} deleted.");
    }

    public function submit(PurchaseOrder $purchaseOrder, Request $request): \Illuminate\Http\RedirectResponse
    {
        try {
            $this->purchaseOrderService->submit($purchaseOrder, $request->user()->id);

            return back()->with('success', "Purchase order {$purchaseOrder->po_number} submitted.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(PurchaseOrder $purchaseOrder, Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $this->purchaseOrderService->cancel($purchaseOrder, $request->get('reason'));

            return back()->with('success', "Purchase order {$purchaseOrder->po_number} cancelled.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function receive(PurchaseOrder $purchaseOrder, Request $request): Response
    {
        if (! $purchaseOrder->canReceiveItems()) {
            return redirect()->route('procurement.purchase-orders.show', $purchaseOrder->id)
                ->with('error', 'This purchase order cannot receive items.');
        }

        $purchaseOrder->load(['items.product:id,name,sku,featured_image', 'location:id,name,code']);

        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $locations = InventoryLocation::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return Inertia::render('Procurement/PurchaseOrders/Receive', [
            'purchaseOrder' => $purchaseOrder,
            'locations' => $locations,
        ]);
    }

    public function processReceive(PurchaseOrder $purchaseOrder, Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'uuid', 'exists:inventory_locations,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'uuid', 'exists:purchase_order_items,id'],
            'items.*.quantity_received' => ['required', 'integer', 'min:1'],
            'items.*.condition' => ['nullable', 'string', 'in:good,damaged,defective'],
            'items.*.condition_notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->receivingService->receive($purchaseOrder, $validated, $request->user()->id);

            return redirect()->route('procurement.purchase-orders.show', $purchaseOrder->id)
                ->with('success', 'Items received successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function supplierProducts(Request $request): JsonResponse
    {
        $request->validate(['supplier_id' => ['required', 'uuid', 'exists:suppliers,id']]);

        $products = $this->purchaseOrderService->getSupplierProducts($request->get('supplier_id'));

        return response()->json($products);
    }
}
