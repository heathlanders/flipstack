<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Fulfillment\ReturnRequest;
use App\Services\Fulfillment\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileReturnController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        protected ReturnService $returnService
    ) {}

    /**
     * GET /api/fulfillment/returns
     * List returns with status filter and search.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $query = ReturnRequest::with([
            'order:id,marketplace_order_id,marketplace,buyer_name',
            'orderItem:id,sku,title,image_url',
        ])
            ->where('organization_id', $organizationId)
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('rma_number', 'ilike', "%{$search}%")
                    ->orWhereHas('order', function ($oq) use ($search) {
                        $oq->where('marketplace_order_id', 'ilike', "%{$search}%")
                            ->orWhere('buyer_name', 'ilike', "%{$search}%");
                    });
            });
        }

        $perPage = min($request->input('per_page', 20), 100);
        $returns = $query->paginate($perPage);

        $stats = DB::table('return_requests')
            ->where('organization_id', $organizationId)
            ->select([
                DB::raw("COUNT(CASE WHEN status NOT IN ('refunded', 'denied', 'cancelled') THEN 1 END) as open"),
                DB::raw("COUNT(CASE WHEN status IN ('approved', 'shipped_back') THEN 1 END) as awaiting_receipt"),
                DB::raw("COUNT(CASE WHEN status = 'received' THEN 1 END) as awaiting_inspection"),
                DB::raw("COUNT(CASE WHEN status = 'refunded' AND refunded_at >= date_trunc('month', CURRENT_DATE) THEN 1 END) as refunded_this_month"),
            ])
            ->first();

        return response()->json([
            'success' => true,
            'data' => $returns->map(fn ($r) => $this->formatReturn($r)),
            'meta' => [
                'total' => $returns->total(),
                'per_page' => $returns->perPage(),
                'current_page' => $returns->currentPage(),
                'last_page' => $returns->lastPage(),
            ],
            'stats' => [
                'open' => (int) $stats->open,
                'awaiting_receipt' => (int) $stats->awaiting_receipt,
                'awaiting_inspection' => (int) $stats->awaiting_inspection,
                'refunded_this_month' => (int) $stats->refunded_this_month,
            ],
        ]);
    }

    /**
     * GET /api/fulfillment/returns/{returnRequest}
     * Return detail with order, items, inspection info.
     */
    public function show(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($returnRequest->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Return not found'], 404);
        }

        $returnRequest->load([
            'order:id,marketplace_order_id,marketplace,buyer_name,total_amount,status',
            'orderItem:id,sku,title,image_url,quantity,condition',
            'inspectedByUser:id,name',
            'receivedByUser:id,name',
        ]);

        $data = $this->formatReturn($returnRequest);
        $data['inspection'] = [
            'condition_on_return' => $returnRequest->condition_on_return,
            'inspection_notes' => $returnRequest->inspection_notes,
            'restockable' => $returnRequest->restockable,
            'inspected_by' => $returnRequest->inspectedByUser?->name,
            'inspected_at' => $returnRequest->inspected_at?->toIso8601String(),
        ];
        $data['received_by'] = $returnRequest->receivedByUser?->name;
        $data['received_at'] = $returnRequest->received_at?->toIso8601String();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * POST /api/fulfillment/returns/{returnRequest}/approve
     * Approve a return request.
     */
    public function approve(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($returnRequest->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Return not found'], 404);
        }

        try {
            $this->returnService->approveReturn($returnRequest, $user);

            return response()->json([
                'success' => true,
                'message' => 'Return '.$returnRequest->rma_number.' approved',
                'data' => ['id' => $returnRequest->id, 'status' => ReturnRequest::STATUS_APPROVED],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/fulfillment/returns/{returnRequest}/receive
     * Mark return as received.
     */
    public function receive(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($returnRequest->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Return not found'], 404);
        }

        try {
            $this->returnService->receiveReturn($returnRequest, $user);

            return response()->json([
                'success' => true,
                'message' => 'Return '.$returnRequest->rma_number.' received',
                'data' => ['id' => $returnRequest->id, 'status' => ReturnRequest::STATUS_RECEIVED],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/fulfillment/returns/{returnRequest}/inspect
     * Inspect a return.
     */
    public function inspect(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        $request->validate([
            'condition' => 'required|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'restockable' => 'required|boolean',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($returnRequest->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Return not found'], 404);
        }

        try {
            $this->returnService->inspectReturn($returnRequest, [
                'condition' => $request->condition,
                'notes' => $request->notes,
                'restockable' => $request->restockable,
            ], $user);

            return response()->json([
                'success' => true,
                'message' => 'Return '.$returnRequest->rma_number.' inspected',
                'data' => ['id' => $returnRequest->id, 'status' => ReturnRequest::STATUS_INSPECTED],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/fulfillment/returns/{returnRequest}/restock
     * Restock a return item.
     */
    public function restock(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|exists:inventory_locations,id',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($returnRequest->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Return not found'], 404);
        }

        try {
            $location = \App\Models\Inventory\InventoryLocation::findOrFail($request->location_id);
            $this->returnService->restockItem($returnRequest, $location, $user);

            return response()->json([
                'success' => true,
                'message' => 'Item restocked at '.$location->name,
                'data' => [
                    'id' => $returnRequest->id,
                    'location_id' => $location->id,
                    'location_name' => $location->name,
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
     * Format a return for JSON response.
     */
    private function formatReturn(ReturnRequest $return): array
    {
        return [
            'id' => $return->id,
            'rma_number' => $return->rma_number,
            'status' => $return->status,
            'reason' => $return->reason,
            'reason_details' => $return->reason_details,
            'source' => $return->source,
            'quantity' => $return->quantity,
            'refund_amount' => $return->refund_amount ? (float) $return->refund_amount : null,
            'restockable' => $return->restockable,
            'marketplace_case_id' => $return->marketplace_case_id,
            'created_at' => $return->created_at->toIso8601String(),
            'order' => $return->relationLoaded('order') && $return->order ? [
                'id' => $return->order->id,
                'marketplace_order_id' => $return->order->marketplace_order_id,
                'marketplace' => $return->order->marketplace,
                'buyer_name' => $return->order->buyer_name,
            ] : null,
            'order_item' => $return->relationLoaded('orderItem') && $return->orderItem ? [
                'id' => $return->orderItem->id,
                'sku' => $return->orderItem->sku,
                'title' => $return->orderItem->title,
                'image_url' => $return->orderItem->image_url,
            ] : null,
        ];
    }
}
