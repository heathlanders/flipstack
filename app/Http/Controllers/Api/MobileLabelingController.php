<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Inventory\LabelingWorkOrder;
use App\Models\Inventory\LabelingWorkOrderItem;
use App\Services\Inventory\LabelingWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileLabelingController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        protected LabelingWorkflowService $labelingService,
    ) {}

    private function getOrganizationId(Request $request): int
    {
        return $this->resolveOrganizationId($request->user())
            ?? abort(response()->json([
                'success' => false,
                'message' => 'No organization associated with this account.',
            ], 422));
    }

    /**
     * GET /api/labeling
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->getOrganizationId($request);

        $query = LabelingWorkOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->actionable()
            ->with(['location:id,name,code', 'assignedTo:id,name'])
            ->withCount('items');

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        $workOrders = $query
            ->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 WHEN priority = 'high' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->get();

        $stats = $this->labelingService->getStats($organizationId);

        return response()->json([
            'work_orders' => $workOrders,
            'stats' => $stats,
        ]);
    }

    /**
     * GET /api/labeling/{workOrder}
     */
    public function show(LabelingWorkOrder $workOrder): JsonResponse
    {
        $workOrder->loadMissing([
            'items.product:id,name,sku,barcode,condition',
            'items.productVariation:id,sku,variation_attributes',
            'items.bin.zone:id,name,code',
            'items.labeledBy:id,name',
            'items.shelvedBy:id,name',
            'location:id,name,code',
            'assignedTo:id,name',
        ]);

        return response()->json([
            'work_order' => $workOrder,
            'progress' => $workOrder->getProgress(),
        ]);
    }

    /**
     * POST /api/labeling/{workOrder}/start
     */
    public function start(Request $request, LabelingWorkOrder $workOrder): JsonResponse
    {
        $this->labelingService->startWorkOrder($workOrder, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Work order started.',
            'work_order' => $workOrder->fresh(),
        ]);
    }

    /**
     * POST /api/labeling/{workOrder}/mark-all-labeled
     */
    public function markAllLabeled(Request $request, LabelingWorkOrder $workOrder): JsonResponse
    {
        $this->labelingService->markAllLabeled($workOrder, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'All items marked as labeled.',
            'progress' => $workOrder->fresh()->getProgress(),
        ]);
    }

    /**
     * POST /api/labeling/{workOrder}/complete
     */
    public function complete(Request $request, LabelingWorkOrder $workOrder): JsonResponse
    {
        $this->labelingService->completeWorkOrder($workOrder, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Work order completed.',
            'work_order' => $workOrder->fresh(),
        ]);
    }

    /**
     * POST /api/labeling/items/{item}/mark-labeled
     */
    public function markLabeled(Request $request, LabelingWorkOrderItem $item): JsonResponse
    {
        $this->labelingService->markItemLabeled($item, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Item marked as labeled.',
            'item' => $item->fresh(['product:id,name,sku']),
        ]);
    }

    /**
     * POST /api/labeling/items/{item}/scan-bin
     */
    public function scanBin(Request $request, LabelingWorkOrderItem $item): JsonResponse
    {
        $request->validate([
            'barcode' => 'required|string',
        ]);

        $this->labelingService->assignBinByBarcode($item, $request->barcode, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Item shelved.',
            'item' => $item->fresh(['bin.zone:id,name,code', 'product:id,name,sku']),
        ]);
    }

    /**
     * POST /api/labeling/items/{item}/skip
     */
    public function skipItem(Request $request, LabelingWorkOrderItem $item): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $this->labelingService->skipItem($item, $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Item skipped.',
            'item' => $item->fresh(),
        ]);
    }

    /**
     * POST /api/labeling/items/{item}/verify
     */
    public function verifyShelf(Request $request, LabelingWorkOrderItem $item): JsonResponse
    {
        $request->validate([
            'barcode' => 'required|string',
        ]);

        if (! $item->isShelved() || ! $item->bin) {
            return response()->json(['success' => false, 'message' => 'Item is not shelved.'], 422);
        }

        $matched = $item->bin->barcode === $request->barcode;

        if ($matched) {
            $item->update(['bin_verified' => true]);
        }

        return response()->json([
            'success' => $matched,
            'matched' => $matched,
            'message' => $matched ? 'Bin verified.' : 'Barcode does not match assigned bin.',
            'expected_barcode' => $item->bin->barcode,
            'scanned_barcode' => $request->barcode,
        ]);
    }

    /**
     * GET /api/labeling/suggest-bin
     */
    public function suggestBin(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'location_id' => 'nullable|uuid|exists:inventory_locations,id',
        ]);

        $product = \App\Models\Catalog\Product::findOrFail($request->product_id);
        $bin = $this->labelingService->suggestBin($product, $request->location_id);

        if (! $bin) {
            return response()->json(['success' => false, 'message' => 'No available bins found.']);
        }

        return response()->json([
            'success' => true,
            'bin' => [
                'id' => $bin->id,
                'code' => $bin->full_code,
                'barcode' => $bin->barcode,
                'aisle' => $bin->aisle,
                'rack' => $bin->rack,
                'shelf' => $bin->shelf,
                'zone' => $bin->zone?->name,
                'capacity_display' => $bin->max_capacity
                    ? $bin->current_count.'/'.$bin->max_capacity
                    : $bin->current_count.' items',
            ],
        ]);
    }
}
