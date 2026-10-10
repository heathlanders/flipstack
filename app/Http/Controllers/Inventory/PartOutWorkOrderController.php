<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\PartOutTemplate;
use App\Models\Inventory\PartOutWorkOrder;
use App\Services\Inventory\LabelingWorkflowService;
use App\Services\Inventory\PartOutPricingService;
use App\Services\Inventory\PartOutService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartOutWorkOrderController extends Controller
{
    public function __construct(
        protected PartOutService $partOutService,
        protected PartOutPricingService $pricingService
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

        $query = PartOutWorkOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with([
                'sourceProduct:id,name,sku,cost_price',
                'template:id,name',
            ])
            ->withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('work_order_number', 'ILIKE', "%{$search}%")
                    ->orWhereHas('sourceProduct', fn ($pq) => $pq->where('name', 'ILIKE', "%{$search}%")->orWhere('sku', 'ILIKE', "%{$search}%"));
            });
        }

        $workOrders = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $stats = $this->partOutService->getStats($organizationId);

        return Inertia::render('Inventory/PartOut/WorkOrders/Index', [
            'workOrders' => $workOrders,
            'stats' => $stats,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function create(Request $request): Response
    {
        $organizationId = $this->getOrganizationId($request);

        $products = Product::where('organization_id', $organizationId)
            ->where('available_quantity', '>', 0)
            ->select('id', 'name', 'sku', 'cost_price', 'selling_price', 'available_quantity', 'component_breakdown')
            ->orderBy('name')
            ->limit(500)
            ->get();

        $templates = PartOutTemplate::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->with('items')
            ->orderBy('name')
            ->get();

        $locations = InventoryLocation::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/PartOut/WorkOrders/Create', [
            'products' => $products,
            'templates' => $templates,
            'locations' => $locations,
            'preselectedProductId' => $request->get('product_id'),
            'preselectedTemplateId' => $request->get('template_id'),
        ]);
    }

    public function store(Request $request)
    {
        $organizationId = $this->getOrganizationId($request);

        $validated = $request->validate([
            'source_product_id' => 'required|uuid|exists:products,id',
            'template_id' => 'nullable|uuid|exists:part_out_templates,id',
            'source_quantity' => 'required|integer|min:1',
            'cost_allocation_method' => 'required|string|in:market_value,percentage,even',
            'location_id' => 'nullable|uuid|exists:inventory_locations,id',
            'parent_continues' => 'boolean',
            'parent_new_name' => 'nullable|string|max:255',
            'remaining_parent_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.manufacturer' => 'nullable|string|max:100',
            'items.*.category' => 'nullable|string|max:50',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.cost_allocation_pct' => 'nullable|numeric|min:0|max:100',
            'items.*.suggested_selling_price' => 'nullable|numeric|min:0',
            'items.*.condition' => 'nullable|string|max:30',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        $validated['created_by'] = $request->user()->id;

        $workOrder = $this->partOutService->createWorkOrder($validated, $organizationId);

        return redirect()->route('inventory.part-out.work-orders.show', $workOrder)
            ->with('success', 'Work order created.');
    }

    public function show(PartOutWorkOrder $workOrder): Response
    {
        $workOrder->load([
            'sourceProduct:id,name,sku,cost_price,selling_price,available_quantity,is_parted_out',
            'template:id,name',
            'items.componentProduct:id,name,sku,cost_price,selling_price,available_quantity',
            'location:id,name,code',
            'creator:id,name',
            'completedBy:id,name',
            'createdProducts:id,name,sku,cost_price,selling_price,available_quantity,parted_from_product_id',
        ]);

        return Inertia::render('Inventory/PartOut/WorkOrders/Show', [
            'workOrder' => $workOrder,
        ]);
    }

    public function execute(Request $request, PartOutWorkOrder $workOrder)
    {
        if (! $workOrder->canExecute()) {
            return redirect()->back()
                ->with('error', 'Work order cannot be executed in its current status.');
        }

        try {
            $result = $this->partOutService->executeWorkOrder($workOrder, $request->user()->id);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to execute work order: '.$e->getMessage());
        }

        // Trigger labeling work order for created component products
        try {
            $componentIds = $result->items->pluck('component_product_id')->filter()->values()->toArray();
            app(LabelingWorkflowService::class)->createFromPartOut($result, $componentIds);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to create labeling WO from part-out', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('inventory.part-out.work-orders.show', $workOrder)
            ->with('success', 'Work order executed. Component products have been created.');
    }

    public function cancel(PartOutWorkOrder $workOrder)
    {
        if (! $workOrder->canCancel()) {
            return redirect()->back()
                ->with('error', 'Work order cannot be cancelled in its current status.');
        }

        $this->partOutService->cancelWorkOrder($workOrder);

        return redirect()->route('inventory.part-out.work-orders.show', $workOrder)
            ->with('success', 'Work order cancelled.');
    }

    public function previewCostAllocation(Request $request)
    {
        $validated = $request->validate([
            'source_total_cost' => 'required|numeric|min:0',
            'remaining_parent_cost' => 'nullable|numeric|min:0',
            'cost_allocation_method' => 'required|string|in:market_value,percentage,even',
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.cost_allocation_pct' => 'nullable|numeric|min:0|max:100',
            'items.*.suggested_selling_price' => 'nullable|numeric|min:0',
        ]);

        $preview = $this->partOutService->previewCostAllocation($validated);

        return response()->json($preview);
    }

    public function refreshPricing(Request $request)
    {
        $organizationId = $this->getOrganizationId($request);

        $validated = $request->validate([
            'components' => 'required|array|min:1',
            'components.*.name' => 'required|string',
            'components.*.part_number' => 'nullable|string',
        ]);

        $pricing = $this->pricingService->priceComponents($validated['components'], $organizationId);

        return response()->json($pricing);
    }
}
