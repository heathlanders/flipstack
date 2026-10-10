<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\Supplier;
use App\Models\Procurement\PurchaseOrder;
use App\Services\Inventory\BatchTrackingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BatchController extends Controller
{
    public function __construct(
        protected BatchTrackingService $batchService
    ) {
        $this->middleware(['auth']);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $query = InventoryBatch::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with(['supplier:id,name,company_name'])
            ->withCount('items');

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'ILIKE', "%{$search}%")
                    ->orWhere('name', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        $batches = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $stats = $this->batchService->getStats($organizationId);

        return Inertia::render('Inventory/Batches/Index', [
            'batches' => $batches,
            'stats' => $stats,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $suppliers = Supplier::where('organization_id', $organizationId)
            ->select('id', 'name', 'company_name')
            ->orderBy('name')
            ->get();

        $purchaseOrders = PurchaseOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', ['cancelled'])
            ->select('id', 'po_number', 'supplier_id')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return Inertia::render('Inventory/Batches/Create', [
            'suppliers' => $suppliers,
            'purchaseOrders' => $purchaseOrders,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'supplier_id' => 'nullable|uuid|exists:suppliers,id',
            'purchase_order_id' => 'nullable|uuid|exists:purchase_orders,id',
            'description' => 'nullable|string|max:1000',
            'acquisition_date' => 'nullable|date',
            'source_description' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:2000',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required|uuid|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $batch = $this->batchService->create($validated, $organizationId);

        return redirect()->route('inventory.batches.show', $batch)
            ->with('success', 'Batch created.');
    }

    public function show(InventoryBatch $batch): Response
    {
        $batch->load(['items.product:id,name,sku,cost_price', 'supplier:id,name,company_name', 'purchaseOrder:id,po_number']);

        return Inertia::render('Inventory/Batches/Show', [
            'batch' => $batch,
        ]);
    }

    public function addItem(Request $request, InventoryBatch $batch)
    {
        if (! $batch->isOpen()) {
            return redirect()->back()->with('error', 'Cannot add items to a closed batch.');
        }

        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'unit_cost' => 'required|numeric|min:0',
        ]);

        $this->batchService->addItem($batch, $validated);
        $batch->recalculate();

        return redirect()->back()->with('success', 'Item added to batch.');
    }

    public function close(InventoryBatch $batch)
    {
        $this->batchService->close($batch);

        return redirect()->back()->with('success', 'Batch closed.');
    }

    public function productSearch(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $search = $request->get('q', '');

        $products = Product::where('organization_id', $organizationId)
            ->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('sku', 'ILIKE', "%{$search}%");
            })
            ->select('id', 'name', 'sku', 'cost_price')
            ->limit(20)
            ->get();

        return response()->json($products);
    }
}
