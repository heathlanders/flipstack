<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\OrderItem;
use App\Services\Fulfillment\FulfillmentService;
use App\Services\Fulfillment\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobilePackController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        protected FulfillmentService $fulfillmentService,
        protected ShipmentService $shipmentService
    ) {}

    /**
     * GET /api/fulfillment/pack
     * Pack queue: orders with status picked/packing.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $orders = Order::with([
            'items.product:id,name,sku,internal_sku,barcode',
            'location:id,name,code',
            'shipments',
        ])
            ->where('organization_id', $organizationId)
            ->whereIn('status', [Order::STATUS_PICKED, Order::STATUS_PACKING])
            ->orderByRaw("array_position(ARRAY['urgent', 'high', 'standard'], priority)")
            ->orderBy('ship_by_date')
            ->get();

        $stats = DB::table('orders')
            ->where('organization_id', $organizationId)
            ->select([
                DB::raw("COUNT(CASE WHEN status = 'picked' THEN 1 END) as awaiting_pack"),
                DB::raw("COUNT(CASE WHEN status = 'packing' THEN 1 END) as packing"),
            ])
            ->first();

        $shipmentStats = DB::table('shipments')
            ->where('organization_id', $organizationId)
            ->select([
                DB::raw("COUNT(CASE WHEN status = 'label_purchased' THEN 1 END) as awaiting_label"),
                DB::raw("COUNT(CASE WHEN status = 'packed' THEN 1 END) as ready_to_ship"),
            ])
            ->first();

        return response()->json([
            'success' => true,
            'data' => $orders->map(fn ($order) => $this->formatPackOrder($order)),
            'stats' => [
                'awaiting_pack' => (int) $stats->awaiting_pack,
                'packing' => (int) $stats->packing,
                'awaiting_label' => (int) $shipmentStats->awaiting_label,
                'ready_to_ship' => (int) $shipmentStats->ready_to_ship,
            ],
        ]);
    }

    /**
     * GET /api/fulfillment/pack/{order}
     * Single order for packing with items and shipment.
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
            'shipments.items',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatPackOrder($order),
        ]);
    }

    /**
     * POST /api/fulfillment/pack/{order}/start
     * Start packing. Auto-create shipment if none exists.
     */
    public function start(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($order->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            $this->fulfillmentService->startPacking($order, $user);

            // Auto-create shipment with picked items if no shipment exists
            if ($order->shipments()->count() === 0 && $order->location) {
                $pickedItems = $order->items
                    ->where('pick_status', OrderItem::PICK_PICKED)
                    ->map(fn ($item) => [
                        'order_item_id' => $item->id,
                        'quantity' => $item->quantity,
                    ])
                    ->values()
                    ->toArray();

                if (! empty($pickedItems)) {
                    $this->shipmentService->createShipment($order, $pickedItems, $order->location);
                }
            }

            $order->refresh()->load([
                'items.product:id,name,sku,internal_sku,barcode',
                'location:id,name,code',
                'shipments',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Packing started for order '.$order->marketplace_order_id,
                'data' => $this->formatPackOrder($order),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/fulfillment/pack/{order}/rates
     * Get shipping rates. Optionally accept dimensions.
     */
    public function rates(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'weight_oz' => 'nullable|numeric|min:0.1',
            'length' => 'nullable|numeric|min:0.1',
            'width' => 'nullable|numeric|min:0.1',
            'height' => 'nullable|numeric|min:0.1',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($order->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            $shipment = $order->shipments()->latest()->first();

            if (! $shipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'No shipment exists for this order. Start packing first.',
                ], 422);
            }

            // Update dimensions if provided
            $dimensions = array_filter([
                'weight_oz' => $request->weight_oz,
                'length' => $request->length,
                'width' => $request->width,
                'height' => $request->height,
            ]);

            if (! empty($dimensions)) {
                $shipment = $this->shipmentService->updateDimensions($shipment, $dimensions);
            }

            $rates = $this->shipmentService->getRates($shipment);

            return response()->json([
                'success' => true,
                'data' => $rates->map(fn ($rate) => [
                    'id' => $rate['id'] ?? $rate['rate_id'] ?? null,
                    'carrier' => $rate['carrier'] ?? null,
                    'service' => $rate['service'] ?? null,
                    'rate' => $rate['rate'] ?? $rate['price'] ?? null,
                    'currency' => $rate['currency'] ?? 'USD',
                    'delivery_days' => $rate['delivery_days'] ?? $rate['est_delivery_days'] ?? null,
                    'delivery_date' => $rate['delivery_date'] ?? null,
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/fulfillment/pack/{order}/purchase-label
     * Purchase a shipping label.
     */
    public function purchaseLabel(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'rate_id' => 'required|string',
            'weight_oz' => 'nullable|numeric|min:0.1',
            'length' => 'nullable|numeric|min:0.1',
            'width' => 'nullable|numeric|min:0.1',
            'height' => 'nullable|numeric|min:0.1',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($order->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            $shipment = $order->shipments()->latest()->first();

            if (! $shipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'No shipment exists for this order.',
                ], 422);
            }

            // Update dimensions if provided
            $dimensions = array_filter([
                'weight_oz' => $request->weight_oz,
                'length' => $request->length,
                'width' => $request->width,
                'height' => $request->height,
            ]);

            if (! empty($dimensions)) {
                $shipment = $this->shipmentService->updateDimensions($shipment, $dimensions);
            }

            $shipment = $this->shipmentService->purchaseLabel($shipment, $request->rate_id, $user);

            return response()->json([
                'success' => true,
                'message' => 'Label purchased successfully',
                'data' => [
                    'shipment_id' => $shipment->id,
                    'tracking_number' => $shipment->tracking_number,
                    'tracking_url' => $shipment->tracking_url,
                    'label_url' => $shipment->label_url,
                    'carrier' => $shipment->carrier,
                    'service' => $shipment->service,
                    'label_cost' => (float) $shipment->label_cost,
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
     * POST /api/fulfillment/pack/{order}/complete
     * Complete packing.
     */
    public function complete(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'weight_oz' => 'nullable|numeric|min:0.1',
            'length' => 'nullable|numeric|min:0.1',
            'width' => 'nullable|numeric|min:0.1',
            'height' => 'nullable|numeric|min:0.1',
        ]);

        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($order->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            $dimensions = array_filter([
                'weight_oz' => $request->weight_oz,
                'length' => $request->length,
                'width' => $request->width,
                'height' => $request->height,
            ]);

            $this->fulfillmentService->completePacking($order, $dimensions, $user);

            return response()->json([
                'success' => true,
                'message' => 'Packing completed for order '.$order->marketplace_order_id,
                'data' => [
                    'order_id' => $order->id,
                    'status' => Order::STATUS_PACKED,
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
     * Format an order for pack queue response.
     */
    private function formatPackOrder(Order $order): array
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
            'items' => $order->relationLoaded('items') ? $order->items->map(fn ($item) => [
                'id' => $item->id,
                'sku' => $item->sku,
                'title' => $item->title,
                'quantity' => $item->quantity,
                'pick_status' => $item->pick_status,
                'image_url' => $item->image_url,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'internal_sku' => $item->product->internal_sku,
                    'barcode' => $item->product->barcode,
                ] : null,
            ])->values() : [],
            'shipments' => $order->relationLoaded('shipments') ? $order->shipments->map(fn ($s) => [
                'id' => $s->id,
                'shipment_number' => $s->shipment_number,
                'status' => $s->status,
                'carrier' => $s->carrier,
                'service' => $s->service,
                'tracking_number' => $s->tracking_number,
                'label_url' => $s->label_url,
                'label_cost' => $s->label_cost ? (float) $s->label_cost : null,
            ]) : [],
        ];
    }
}
