<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\OrderItem;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use App\Services\Fulfillment\FulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobilePickController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        protected FulfillmentService $fulfillmentService
    ) {}

    /**
     * GET /api/fulfillment/pick
     * Pick list: orders ready for picking, optionally filtered by location.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'location_id' => 'nullable|exists:inventory_locations,id',
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
            ->whereIn('status', [Order::STATUS_ASSIGNED, Order::STATUS_PICKING])
            ->orderByRaw("array_position(ARRAY['urgent', 'high', 'standard'], priority)")
            ->orderBy('ship_by_date');

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        // Optionally filter to only orders assigned to this picker
        if ($request->boolean('my_picks')) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_picker_id', $user->id)
                    ->orWhereNull('assigned_picker_id');
            });
        }

        $orders = $query->get();

        $locations = InventoryLocation::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get(['id', 'name', 'code']);

        $stats = [
            'total_orders' => $orders->count(),
            'total_items' => $orders->sum(fn ($o) => $o->items->where('pick_status', 'pending')->sum('quantity')),
            'urgent' => $orders->where('priority', 'urgent')->count(),
            'overdue' => $orders->filter->isOverdue()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $orders->map(fn ($order) => $this->formatOrder($order)),
            'locations' => $locations->map(fn ($loc) => [
                'id' => $loc->id,
                'name' => $loc->name,
                'code' => $loc->code,
            ]),
            'stats' => $stats,
        ]);
    }

    /**
     * GET /api/fulfillment/pick/{order}
     * Single order detail for picking.
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
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($order),
        ]);
    }

    /**
     * POST /api/fulfillment/pick/{order}/start
     * Start picking an order.
     */
    public function start(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($order->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            $this->fulfillmentService->startPicking($order, $user);
            $order->refresh()->load([
                'items.product:id,name,sku,internal_sku,barcode',
                'items.location:id,name,code',
                'location:id,name,code',
                'assignedPicker:id,name',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Picking started for order '.$order->marketplace_order_id,
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
     * POST /api/fulfillment/pick/items/{orderItem}/pick
     * Mark an item as picked. Supports barcode verification.
     */
    public function pickItem(Request $request, OrderItem $orderItem): JsonResponse
    {
        $request->validate([
            'quantity' => 'nullable|integer|min:1|max:'.$orderItem->quantity,
            'barcode' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        // Barcode verification: if a barcode is scanned, verify it matches the product
        if ($request->filled('barcode') && $orderItem->product) {
            $product = $orderItem->product;
            $scanned = $request->barcode;
            $matched = $product->barcode === $scanned
                || $product->sku === $scanned
                || $product->internal_sku === $scanned
                || $product->identifiers()->where('value', $scanned)->exists();

            if (! $matched) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barcode does not match this item. Expected SKU: '.$orderItem->sku,
                    'expected_sku' => $orderItem->sku,
                    'scanned_barcode' => $scanned,
                ], 422);
            }
        }

        try {
            $this->fulfillmentService->pickItem($orderItem, $user, $request->get('quantity'));

            return response()->json([
                'success' => true,
                'message' => 'Item picked: '.$orderItem->title,
                'data' => $this->formatItem($orderItem->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/fulfillment/pick/items/{orderItem}/short
     * Mark an item as short (unavailable).
     */
    public function shortItem(Request $request, OrderItem $orderItem): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user = $request->user();

        try {
            $this->fulfillmentService->shortItem($orderItem, $user, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Item shorted: '.$orderItem->title,
                'data' => $this->formatItem($orderItem->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/fulfillment/pick/{order}/complete
     * Complete picking for an order.
     */
    public function complete(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($order->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            $order->load('items');
            $this->fulfillmentService->completePicking($order, $user);

            return response()->json([
                'success' => true,
                'message' => 'Picking completed for order '.$order->marketplace_order_id,
                'data' => [
                    'order_id' => $order->id,
                    'status' => Order::STATUS_PICKED,
                    'items_picked' => $order->items->where('pick_status', OrderItem::PICK_PICKED)->count(),
                    'items_shorted' => $order->items->where('pick_status', OrderItem::PICK_SHORT)->count(),
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
     * POST /api/fulfillment/pick/items/{orderItem}/scan-verify
     * Verify a scanned barcode matches an item without picking it.
     */
    public function scanVerify(Request $request, OrderItem $orderItem): JsonResponse
    {
        $request->validate([
            'barcode' => 'required|string|max:100',
        ]);

        $scanned = $request->barcode;
        $matched = false;
        $matchType = null;

        if ($orderItem->product) {
            $product = $orderItem->product;
            if ($product->barcode === $scanned) {
                $matched = true;
                $matchType = 'barcode';
            } elseif ($product->sku === $scanned) {
                $matched = true;
                $matchType = 'sku';
            } elseif ($product->internal_sku === $scanned) {
                $matched = true;
                $matchType = 'internal_sku';
            } elseif ($product->identifiers()->where('value', $scanned)->exists()) {
                $matched = true;
                $matchType = 'identifier';
            }
        } elseif ($orderItem->sku === $scanned) {
            $matched = true;
            $matchType = 'sku';
        }

        return response()->json([
            'success' => true,
            'matched' => $matched,
            'match_type' => $matchType,
            'scanned_barcode' => $scanned,
            'expected_sku' => $orderItem->sku,
            'item' => $this->formatItem($orderItem),
        ]);
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
            'can_be_picked' => $order->canBePicked(),
            'buyer_name' => $order->buyer_name,
            'total_amount' => (float) $order->total_amount,
            'items_count' => $order->items_count,
            'location' => $order->location ? [
                'id' => $order->location->id,
                'name' => $order->location->name,
                'code' => $order->location->code,
            ] : null,
            'assigned_picker' => $order->assignedPicker ? [
                'id' => $order->assignedPicker->id,
                'name' => $order->assignedPicker->name,
            ] : null,
            'items' => $order->items->map(fn ($item) => $this->formatItem($item))->values(),
            'pick_progress' => [
                'total' => $order->items->count(),
                'picked' => $order->items->where('pick_status', OrderItem::PICK_PICKED)->count(),
                'shorted' => $order->items->where('pick_status', OrderItem::PICK_SHORT)->count(),
                'pending' => $order->items->where('pick_status', OrderItem::PICK_PENDING)->count(),
            ],
        ];
    }

    /**
     * Format an order item for JSON response.
     */
    private function formatItem(OrderItem $item): array
    {
        return [
            'id' => $item->id,
            'order_id' => $item->order_id,
            'product_id' => $item->product_id,
            'sku' => $item->sku,
            'title' => $item->title,
            'quantity' => $item->quantity,
            'pick_status' => $item->pick_status,
            'picked_at' => $item->picked_at?->toIso8601String(),
            'image_url' => $item->image_url,
            'condition' => $item->condition,
            'location' => $item->location ? [
                'id' => $item->location->id,
                'name' => $item->location->name,
                'code' => $item->location->code,
            ] : null,
            'product' => $item->product ? [
                'id' => $item->product->id,
                'name' => $item->product->name,
                'sku' => $item->product->sku,
                'internal_sku' => $item->product->internal_sku,
                'barcode' => $item->product->barcode,
            ] : null,
            'bin_location' => $this->getBinLocation($item),
        ];
    }

    /**
     * Get bin location for a pick item from InventoryLevel.
     */
    private function getBinLocation(OrderItem $item): ?array
    {
        if (! $item->product_id) {
            return null;
        }

        $query = InventoryLevel::where('product_id', $item->product_id)
            ->whereNotNull('bin_id')
            ->with('bin.zone:id,name,code');

        if ($item->location_id) {
            $query->where('location_id', $item->location_id);
        }

        $level = $query->first();

        if (! $level || ! $level->bin) {
            return null;
        }

        return [
            'bin_id' => $level->bin->id,
            'bin_code' => $level->bin->full_code,
            'bin_barcode' => $level->bin->barcode,
            'aisle' => $level->bin->aisle,
            'rack' => $level->bin->rack,
            'shelf' => $level->bin->shelf,
            'zone' => $level->bin->zone?->name,
        ];
    }
}
