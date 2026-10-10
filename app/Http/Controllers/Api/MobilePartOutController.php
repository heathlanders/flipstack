<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\PartOutWorkOrder;
use App\Models\Inventory\PartOutWorkOrderItem;
use App\Services\Inventory\PartOutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobilePartOutController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        protected PartOutService $partOutService
    ) {}

    /**
     * GET /api/part-out
     * List part-out work orders for the mobile app.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $query = PartOutWorkOrder::with([
            'sourceProduct:id,name,sku,barcode',
            'location:id,name,code',
            'assignedTo:id,name',
        ])
            ->withCount('items')
            ->where('organization_id', $organizationId);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', [
                PartOutWorkOrder::STATUS_DRAFT,
                PartOutWorkOrder::STATUS_IN_PROGRESS,
            ]);
        }

        // Filter by location
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        // Filter to user's orders (assigned to them or unassigned)
        if ($request->boolean('my_orders')) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhereNull('assigned_to');
            });
        }

        $query->orderBy('created_at', 'desc');

        $workOrders = $query->get();

        // Stats across all statuses for this org
        $allOrders = PartOutWorkOrder::where('organization_id', $organizationId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft")
            ->selectRaw("COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress")
            ->selectRaw("COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed")
            ->first();

        $stats = [
            'total' => (int) $allOrders->total,
            'draft' => (int) $allOrders->draft,
            'in_progress' => (int) $allOrders->in_progress,
            'completed' => (int) $allOrders->completed,
        ];

        $locations = InventoryLocation::where('is_active', true)
            ->get(['id', 'name', 'code']);

        return response()->json([
            'success' => true,
            'data' => $workOrders->map(fn ($wo) => $this->formatWorkOrder($wo)),
            'locations' => $locations->map(fn ($loc) => [
                'id' => $loc->id,
                'name' => $loc->name,
                'code' => $loc->code,
            ]),
            'stats' => $stats,
        ]);
    }

    /**
     * GET /api/part-out/{workOrder}
     * Show a single part-out work order with all items.
     */
    public function show(Request $request, PartOutWorkOrder $workOrder): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($workOrder->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Work order not found'], 404);
        }

        $workOrder->load([
            'items',
            'sourceProduct:id,name,sku,barcode,available_quantity,cost_price,featured_image',
            'location:id,name,code',
            'assignedTo:id,name',
            'creator:id,name',
        ]);

        $data = $this->formatWorkOrder($workOrder);
        $data['items'] = $workOrder->items->map(fn ($item) => $this->formatItem($item))->values();
        $data['creator'] = $workOrder->creator ? [
            'id' => $workOrder->creator->id,
            'name' => $workOrder->creator->name,
        ] : null;
        $data['source_product'] = $workOrder->sourceProduct ? [
            'id' => $workOrder->sourceProduct->id,
            'name' => $workOrder->sourceProduct->name,
            'sku' => $workOrder->sourceProduct->sku,
            'barcode' => $workOrder->sourceProduct->barcode,
            'available_quantity' => $workOrder->sourceProduct->available_quantity,
            'cost_price' => (float) $workOrder->sourceProduct->cost_price,
            'featured_image' => $workOrder->sourceProduct->featured_image,
        ] : null;

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * POST /api/part-out/{workOrder}/start
     * Start working on a part-out work order.
     */
    public function start(Request $request, PartOutWorkOrder $workOrder): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($workOrder->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Work order not found'], 404);
        }

        if ($workOrder->status !== PartOutWorkOrder::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Work order must be in draft status to start.',
            ], 422);
        }

        $workOrder->update([
            'status' => PartOutWorkOrder::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'assigned_to' => $user->id,
        ]);

        $workOrder->refresh()->load([
            'items',
            'sourceProduct:id,name,sku,barcode',
            'location:id,name,code',
            'assignedTo:id,name',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Work order '.$workOrder->work_order_number.' started.',
            'data' => $this->formatWorkOrder($workOrder),
        ]);
    }

    /**
     * POST /api/part-out/items/{item}/extract
     * Mark an item as extracted.
     */
    public function extractItem(Request $request, PartOutWorkOrderItem $item): JsonResponse
    {
        $request->validate([
            'barcode' => 'nullable|string',
            'condition' => 'nullable|string|max:30',
            'notes' => 'nullable|string',
        ]);

        if (! $item->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Item is not in pending status.',
            ], 422);
        }

        $updates = [
            'extraction_status' => PartOutWorkOrderItem::EXTRACTION_EXTRACTED,
            'extracted_at' => now(),
            'extracted_by' => auth()->id(),
        ];

        if ($request->filled('barcode')) {
            if (strcasecmp($request->barcode, $item->part_number) === 0) {
                $updates['barcode_verified'] = true;
            }
            $updates['scanned_barcode'] = $request->barcode;
        }

        if ($request->filled('condition')) {
            $updates['condition'] = $request->condition;
        }

        if ($request->filled('notes')) {
            $updates['extraction_notes'] = $request->notes;
        }

        $item->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Item extracted: '.$item->name,
            'data' => $this->formatItem($item->fresh()),
        ]);
    }

    /**
     * POST /api/part-out/items/{item}/skip
     * Skip an item with a reason.
     */
    public function skipItem(Request $request, PartOutWorkOrderItem $item): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if (! $item->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Item is not in pending status.',
            ], 422);
        }

        $item->update([
            'extraction_status' => PartOutWorkOrderItem::EXTRACTION_SKIPPED,
            'extracted_at' => now(),
            'extracted_by' => auth()->id(),
            'extraction_notes' => $request->reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item skipped: '.$item->name,
            'data' => $this->formatItem($item->fresh()),
        ]);
    }

    /**
     * POST /api/part-out/items/{item}/notes
     * Update extraction notes for an item.
     */
    public function updateItemNotes(Request $request, PartOutWorkOrderItem $item): JsonResponse
    {
        $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        $item->update([
            'extraction_notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notes updated.',
            'data' => $this->formatItem($item->fresh()),
        ]);
    }

    /**
     * POST /api/part-out/items/{item}/scan-verify
     * Verify a scanned barcode matches an item's part number.
     */
    public function scanVerify(Request $request, PartOutWorkOrderItem $item): JsonResponse
    {
        $request->validate([
            'barcode' => 'required|string',
        ]);

        $scanned = $request->barcode;
        $matched = $item->part_number && strcasecmp($scanned, $item->part_number) === 0;

        return response()->json([
            'success' => true,
            'matched' => $matched,
            'match_type' => $matched ? 'part_number' : null,
            'expected' => $item->part_number,
            'scanned' => $scanned,
        ]);
    }

    /**
     * POST /api/part-out/{workOrder}/complete
     * Complete a part-out work order (execute all extractions).
     */
    public function complete(Request $request, PartOutWorkOrder $workOrder): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($workOrder->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Work order not found'], 404);
        }

        $workOrder->load('items');

        $pendingCount = $workOrder->items->where('extraction_status', PartOutWorkOrderItem::EXTRACTION_PENDING)->count();
        if ($pendingCount > 0) {
            return response()->json([
                'success' => false,
                'message' => $pendingCount.' item(s) still pending. All items must be extracted or skipped before completing.',
            ], 422);
        }

        try {
            $this->partOutService->executeWorkOrder($workOrder, auth()->id());

            $extractedCount = $workOrder->items->where('extraction_status', PartOutWorkOrderItem::EXTRACTION_EXTRACTED)->count();
            $skippedCount = $workOrder->items->where('extraction_status', PartOutWorkOrderItem::EXTRACTION_SKIPPED)->count();

            return response()->json([
                'success' => true,
                'message' => 'Work order '.$workOrder->work_order_number.' completed.',
                'data' => [
                    'work_order_id' => $workOrder->id,
                    'status' => PartOutWorkOrder::STATUS_COMPLETED,
                    'components_created' => $extractedCount,
                    'skipped' => $skippedCount,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/part-out/{workOrder}/cancel
     * Cancel a part-out work order.
     */
    public function cancel(Request $request, PartOutWorkOrder $workOrder): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($workOrder->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Work order not found'], 404);
        }

        if (! $workOrder->canCancel()) {
            return response()->json([
                'success' => false,
                'message' => 'Work order cannot be cancelled in its current status.',
            ], 422);
        }

        // Reset all items to pending state
        $workOrder->items()->update([
            'extraction_status' => PartOutWorkOrderItem::EXTRACTION_PENDING,
            'extracted_at' => null,
            'extracted_by' => null,
            'extraction_notes' => null,
            'barcode_verified' => false,
            'scanned_barcode' => null,
        ]);

        $this->partOutService->cancelWorkOrder($workOrder);

        return response()->json([
            'success' => true,
            'message' => 'Work order '.$workOrder->work_order_number.' cancelled.',
            'data' => [
                'work_order_id' => $workOrder->id,
                'status' => PartOutWorkOrder::STATUS_CANCELLED,
            ],
        ]);
    }

    /**
     * Format a work order for JSON response.
     */
    private function formatWorkOrder(PartOutWorkOrder $wo): array
    {
        return [
            'id' => $wo->id,
            'work_order_number' => $wo->work_order_number,
            'status' => $wo->status,
            'source_product' => $wo->sourceProduct ? [
                'id' => $wo->sourceProduct->id,
                'name' => $wo->sourceProduct->name,
                'sku' => $wo->sourceProduct->sku,
                'barcode' => $wo->sourceProduct->barcode,
            ] : null,
            'location' => $wo->location ? [
                'id' => $wo->location->id,
                'name' => $wo->location->name,
                'code' => $wo->location->code,
            ] : null,
            'assigned_to' => $wo->assignedTo ? [
                'id' => $wo->assignedTo->id,
                'name' => $wo->assignedTo->name,
            ] : null,
            'source_quantity' => $wo->source_quantity,
            'source_total_cost' => (float) $wo->source_total_cost,
            'cost_allocation_method' => $wo->cost_allocation_method,
            'parent_continues' => $wo->parent_continues,
            'parent_new_name' => $wo->parent_new_name,
            'notes' => $wo->notes,
            'items_count' => $wo->items_count ?? $wo->items->count(),
            'extraction_progress' => $wo->getExtractionProgress(),
            'started_at' => $wo->started_at?->toIso8601String(),
            'completed_at' => $wo->completed_at?->toIso8601String(),
            'created_at' => $wo->created_at->toIso8601String(),
        ];
    }

    /**
     * Format a work order item for JSON response.
     */
    private function formatItem(PartOutWorkOrderItem $item): array
    {
        return [
            'id' => $item->id,
            'work_order_id' => $item->work_order_id,
            'component_product_id' => $item->component_product_id,
            'name' => $item->name,
            'part_number' => $item->part_number,
            'manufacturer' => $item->manufacturer,
            'category' => $item->category,
            'quantity' => $item->quantity,
            'allocated_cost' => (float) $item->allocated_cost,
            'allocated_cost_per_unit' => (float) $item->allocated_cost_per_unit,
            'cost_allocation_pct' => (float) $item->cost_allocation_pct,
            'suggested_selling_price' => (float) $item->suggested_selling_price,
            'condition' => $item->condition,
            'notes' => $item->notes,
            'extraction_status' => $item->extraction_status,
            'extracted_at' => $item->extracted_at?->toIso8601String(),
            'extracted_by' => $item->extracted_by,
            'extraction_notes' => $item->extraction_notes,
            'barcode_verified' => $item->barcode_verified,
            'scanned_barcode' => $item->scanned_barcode,
        ];
    }
}
