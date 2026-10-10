<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Fulfillment\Order;
use App\Services\Fulfillment\FulfillmentAuditService;
use App\Services\Fulfillment\FulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileOrderController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        protected FulfillmentService $fulfillmentService,
        protected FulfillmentAuditService $auditService
    ) {}

    /**
     * GET /api/fulfillment/orders
     * List orders with filters, paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string',
            'priority' => 'nullable|string|in:urgent,high,standard',
            'marketplace' => 'nullable|string',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $query = Order::with([
            'items.product:id,name,sku,internal_sku,barcode',
            'items.location:id,name,code',
            'location:id,name,code',
            'assignedPicker:id,name',
        ])
            ->where('organization_id', $organizationId)
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('marketplace')) {
            $query->where('marketplace', $request->marketplace);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('marketplace_order_id', 'ilike', "%{$search}%")
                    ->orWhere('buyer_name', 'ilike', "%{$search}%");
            });
        }

        $perPage = min($request->input('per_page', 20), 100);
        $orders = $query->paginate($perPage);

        // Compute stats across all org orders (not just the filtered page)
        $stats = DB::table('orders')
            ->where('organization_id', $organizationId)
            ->select([
                DB::raw("COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending"),
                DB::raw("COUNT(CASE WHEN status = 'picking' THEN 1 END) as picking"),
                DB::raw("COUNT(CASE WHEN status = 'packing' THEN 1 END) as packing"),
                DB::raw("COUNT(CASE WHEN status = 'shipped' THEN 1 END) as shipped"),
                DB::raw("COUNT(CASE WHEN status = 'on_hold' THEN 1 END) as on_hold"),
                DB::raw("COUNT(CASE WHEN ship_by_date < NOW() AND status NOT IN ('shipped', 'delivered', 'cancelled', 'returned') THEN 1 END) as overdue"),
            ])
            ->first();

        return response()->json([
            'success' => true,
            'data' => $orders->map(fn ($order) => $this->formatOrder($order)),
            'meta' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ],
            'stats' => [
                'pending' => (int) $stats->pending,
                'picking' => (int) $stats->picking,
                'packing' => (int) $stats->packing,
                'shipped' => (int) $stats->shipped,
                'on_hold' => (int) $stats->on_hold,
                'overdue' => (int) $stats->overdue,
            ],
        ]);
    }

    /**
     * GET /api/fulfillment/orders/{order}
     * Order detail with items, shipments, timeline.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($order->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $order->load([
            'items.product:id,name,sku,internal_sku,barcode',
            'items.location:id,name,code',
            'location:id,name,code',
            'assignedPicker:id,name',
            'shipments',
        ]);

        $timeline = $this->auditService->getOrderTimeline($order);

        return response()->json([
            'success' => true,
            'data' => array_merge($this->formatOrder($order), [
                'shipments' => $order->shipments->map(fn ($s) => [
                    'id' => $s->id,
                    'shipment_number' => $s->shipment_number,
                    'status' => $s->status,
                    'carrier' => $s->carrier,
                    'service' => $s->service,
                    'tracking_number' => $s->tracking_number,
                    'tracking_url' => $s->tracking_url,
                    'label_url' => $s->label_url,
                    'label_cost' => $s->label_cost ? (float) $s->label_cost : null,
                    'shipped_at' => $s->shipped_at?->toIso8601String(),
                ]),
                'timeline' => $timeline,
            ]),
        ]);
    }

    /**
     * POST /api/fulfillment/orders/{order}/assign
     * Assign order to a location.
     */
    public function assign(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|exists:inventory_locations,id',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($order->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            $location = \App\Models\Inventory\InventoryLocation::findOrFail($request->location_id);
            $this->fulfillmentService->assignOrder($order, $location, $user);

            $order->refresh()->load([
                'items.product:id,name,sku,internal_sku,barcode',
                'location:id,name,code',
                'assignedPicker:id,name',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order assigned to '.$location->name,
                'data' => $this->formatOrder($order),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/fulfillment/orders/{order}/hold
     * Put order on hold.
     */
    public function hold(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($order->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            $this->fulfillmentService->holdOrder($order, $request->reason, $user);

            return response()->json([
                'success' => true,
                'message' => 'Order '.$order->marketplace_order_id.' placed on hold',
                'data' => ['id' => $order->id, 'status' => Order::STATUS_ON_HOLD],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/fulfillment/orders/{order}/release
     * Release order from hold.
     */
    public function release(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($order->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            $this->fulfillmentService->releaseOrder($order, $user);

            return response()->json([
                'success' => true,
                'message' => 'Order '.$order->marketplace_order_id.' released from hold',
                'data' => ['id' => $order->id, 'status' => Order::STATUS_ASSIGNED],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Format an order for JSON response.
     */
    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'marketplace_order_id' => $order->marketplace_order_id,
            'marketplace' => $order->marketplace,
            'status' => $order->status,
            'priority' => $order->priority,
            'ship_by_date' => $order->ship_by_date?->toIso8601String(),
            'is_overdue' => $order->isOverdue(),
            'buyer_name' => $order->buyer_name,
            'total_amount' => (float) $order->total_amount,
            'items_count' => $order->items_count ?? $order->items->count(),
            'location' => $order->location ? [
                'id' => $order->location->id,
                'name' => $order->location->name,
                'code' => $order->location->code,
            ] : null,
            'assigned_picker' => $order->assignedPicker ? [
                'id' => $order->assignedPicker->id,
                'name' => $order->assignedPicker->name,
            ] : null,
            'items' => $order->relationLoaded('items') ? $order->items->map(fn ($item) => [
                'id' => $item->id,
                'sku' => $item->sku,
                'title' => $item->title,
                'quantity' => $item->quantity,
                'image_url' => $item->image_url,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'internal_sku' => $item->product->internal_sku,
                    'barcode' => $item->product->barcode,
                ] : null,
            ])->values() : [],
        ];
    }
}
