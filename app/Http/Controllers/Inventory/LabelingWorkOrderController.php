<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\LabelingWorkOrder;
use App\Models\Inventory\LabelingWorkOrderItem;
use App\Models\Inventory\WarehouseBin;
use App\Services\Inventory\LabelingWorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LabelingWorkOrderController extends Controller
{
    public function __construct(
        protected LabelingWorkflowService $labelingService,
    ) {
        $this->middleware(['auth']);
    }

    private function getOrganizationId(Request $request): int
    {
        $user = $request->user();

        return $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;
    }

    public function index(Request $request): Response
    {
        $organizationId = $this->getOrganizationId($request);

        $query = LabelingWorkOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with(['location:id,name,code', 'assignedTo:id,name', 'creator:id,name'])
            ->withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->get('location_id'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('work_order_number', 'ILIKE', "%{$search}%")
                    ->orWhere('source_type', 'ILIKE', "%{$search}%");
            });
        }

        $workOrders = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $stats = $this->labelingService->getStats($organizationId);

        $locations = InventoryLocation::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Labeling/Index', [
            'workOrders' => $workOrders,
            'stats' => $stats,
            'locations' => $locations,
            'filters' => $request->only('search', 'status', 'location_id'),
        ]);
    }

    public function create(Request $request): Response
    {
        $organizationId = $this->getOrganizationId($request);

        $products = Product::where('organization_id', $organizationId)
            ->whereIn('status', ['active', 'draft'])
            ->select('id', 'name', 'sku', 'condition', 'labeling_status')
            ->orderBy('name')
            ->limit(500)
            ->get();

        $locations = InventoryLocation::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Labeling/Create', [
            'products' => $products,
            'locations' => $locations,
        ]);
    }

    public function store(Request $request)
    {
        $organizationId = $this->getOrganizationId($request);

        $validated = $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'uuid|exists:products,id',
            'location_id' => 'required|uuid|exists:inventory_locations,id',
            'priority' => 'nullable|string|in:urgent,high,standard',
            'notes' => 'nullable|string|max:2000',
        ]);

        $wo = $this->labelingService->createManual(
            productIds: $validated['product_ids'],
            organizationId: $organizationId,
            locationId: $validated['location_id'],
            userId: $request->user()->id,
        );

        if (! empty($validated['priority']) && $validated['priority'] !== 'standard') {
            $wo->update(['priority' => $validated['priority']]);
        }
        if (! empty($validated['notes'])) {
            $wo->update(['notes' => $validated['notes']]);
        }

        return redirect()->route('inventory.labeling.show', $wo->id)
            ->with('success', 'Labeling work order created.');
    }

    public function show(Request $request, LabelingWorkOrder $workOrder): Response
    {
        $workOrder->loadMissing([
            'items.product:id,name,sku,barcode,condition,labeling_status',
            'items.productVariation:id,sku,variation_attributes',
            'items.bin.zone:id,name,code',
            'items.labeledBy:id,name',
            'items.shelvedBy:id,name',
            'location:id,name,code',
            'assignedTo:id,name',
            'creator:id,name',
        ]);

        $progress = $workOrder->getProgress();

        // Get available bins at this location for assignment
        $bins = [];
        if ($workOrder->location_id) {
            $bins = WarehouseBin::whereHas('zone', fn ($q) => $q->where('location_id', $workOrder->location_id)->where('is_active', true))
                ->active()
                ->select('id', 'zone_id', 'code', 'label', 'aisle', 'rack', 'shelf', 'barcode', 'max_capacity', 'current_count')
                ->with('zone:id,name,code')
                ->orderBy('code')
                ->get()
                ->map(fn ($bin) => [
                    'id' => $bin->id,
                    'code' => $bin->full_code,
                    'label' => $bin->label,
                    'aisle' => $bin->aisle,
                    'rack' => $bin->rack,
                    'shelf' => $bin->shelf,
                    'zone_name' => $bin->zone?->name,
                    'at_capacity' => $bin->isAtCapacity(),
                    'capacity_display' => $bin->max_capacity
                        ? $bin->current_count.'/'.$bin->max_capacity
                        : $bin->current_count.' items',
                ]);
        }

        return Inertia::render('Inventory/Labeling/Show', [
            'workOrder' => $workOrder,
            'progress' => $progress,
            'bins' => $bins,
        ]);
    }

    public function start(Request $request, LabelingWorkOrder $workOrder)
    {
        $this->labelingService->startWorkOrder($workOrder, $request->user()->id);

        return redirect()->back()->with('success', 'Work order started.');
    }

    public function printAll(Request $request, LabelingWorkOrder $workOrder)
    {
        $this->labelingService->markAllLabeled($workOrder, $request->user()->id);

        return redirect()->back()->with('success', 'All items marked as labeled.');
    }

    public function complete(Request $request, LabelingWorkOrder $workOrder)
    {
        $this->labelingService->completeWorkOrder($workOrder, $request->user()->id);

        return redirect()->back()->with('success', 'Work order completed.');
    }

    public function markLabeled(Request $request, LabelingWorkOrderItem $item)
    {
        $this->labelingService->markItemLabeled($item, $request->user()->id);

        return redirect()->back()->with('success', 'Item marked as labeled.');
    }

    public function assignBin(Request $request, LabelingWorkOrderItem $item)
    {
        $validated = $request->validate([
            'bin_id' => 'required|uuid|exists:warehouse_bins,id',
        ]);

        $this->labelingService->assignBin($item, $validated['bin_id'], $request->user()->id);

        return redirect()->back()->with('success', 'Item assigned to bin and shelved.');
    }

    public function skipItem(Request $request, LabelingWorkOrderItem $item)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $this->labelingService->skipItem($item, $validated['reason']);

        return redirect()->back()->with('success', 'Item skipped.');
    }
}
