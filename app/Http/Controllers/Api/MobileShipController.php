<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Fulfillment\Shipment;
use App\Services\Fulfillment\FulfillmentService;
use App\Services\Fulfillment\ShipmentService;
use App\Services\Fulfillment\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileShipController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        protected ShipmentService $shipmentService,
        protected FulfillmentService $fulfillmentService,
        protected TrackingService $trackingService
    ) {}

    /**
     * GET /api/fulfillment/ship
     * Ship queue: shipments with status label_purchased or packed.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        $shipments = Shipment::with([
            'order:id,marketplace_order_id,marketplace,status,buyer_name,total_amount,priority',
            'location:id,name,code',
        ])
            ->where('organization_id', $organizationId)
            ->whereIn('status', [Shipment::STATUS_LABEL_PURCHASED, Shipment::STATUS_PACKED])
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = DB::table('shipments')
            ->where('organization_id', $organizationId)
            ->select([
                DB::raw("COUNT(CASE WHEN status IN ('label_purchased', 'packed') THEN 1 END) as ready_to_ship"),
                DB::raw("COUNT(CASE WHEN status = 'shipped' AND shipped_at >= CURRENT_DATE THEN 1 END) as shipped_today"),
                DB::raw("COUNT(CASE WHEN status = 'in_transit' THEN 1 END) as in_transit"),
                DB::raw("COUNT(CASE WHEN status = 'delivered' AND actual_delivery_date = CURRENT_DATE THEN 1 END) as delivered_today"),
            ])
            ->first();

        return response()->json([
            'success' => true,
            'data' => $shipments->map(fn ($s) => $this->formatShipment($s)),
            'stats' => [
                'ready_to_ship' => (int) $stats->ready_to_ship,
                'shipped_today' => (int) $stats->shipped_today,
                'in_transit' => (int) $stats->in_transit,
                'delivered_today' => (int) $stats->delivered_today,
            ],
        ]);
    }

    /**
     * GET /api/fulfillment/ship/{shipment}
     * Shipment detail with order and tracking info.
     */
    public function show(Request $request, Shipment $shipment): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($shipment->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Shipment not found'], 404);
        }

        $shipment->load([
            'order:id,marketplace_order_id,marketplace,status,buyer_name,total_amount,priority,ship_by_date',
            'order.items.product:id,name,sku,internal_sku,barcode',
            'location:id,name,code',
            'items.orderItem',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatShipment($shipment),
        ]);
    }

    /**
     * POST /api/fulfillment/ship/{shipment}/confirm
     * Mark shipment as shipped.
     */
    public function confirm(Request $request, Shipment $shipment): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($shipment->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Shipment not found'], 404);
        }

        try {
            $this->shipmentService->markShipped($shipment, $user);

            $order = $shipment->order;
            $order->load('items');
            $this->fulfillmentService->shipOrder($order, $user);

            // Upload tracking to marketplace in background
            try {
                $this->trackingService->uploadTrackingToMarketplace($shipment->fresh());
            } catch (\Exception $e) {
                // Non-fatal: tracking upload can be retried
            }

            return response()->json([
                'success' => true,
                'message' => 'Shipment confirmed as shipped',
                'data' => [
                    'shipment_id' => $shipment->id,
                    'tracking_number' => $shipment->tracking_number,
                    'status' => Shipment::STATUS_SHIPPED,
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
     * POST /api/fulfillment/ship/{shipment}/void
     * Void a shipping label.
     */
    public function voidLabel(Request $request, Shipment $shipment): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($shipment->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Shipment not found'], 404);
        }

        try {
            $this->shipmentService->voidLabel($shipment, $user);

            return response()->json([
                'success' => true,
                'message' => 'Label voided successfully',
                'data' => [
                    'shipment_id' => $shipment->id,
                    'status' => Shipment::STATUS_CANCELLED,
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
     * GET /api/fulfillment/ship/{shipment}/label
     * Return the label URL.
     */
    public function label(Request $request, Shipment $shipment): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->resolveOrganizationId($user);

        if ($shipment->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Shipment not found'], 404);
        }

        if (! $shipment->label_url) {
            return response()->json([
                'success' => false,
                'message' => 'No label available for this shipment',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'shipment_id' => $shipment->id,
                'label_url' => $shipment->label_url,
                'label_format' => $shipment->label_format,
                'tracking_number' => $shipment->tracking_number,
            ],
        ]);
    }

    /**
     * Format a shipment for JSON response.
     */
    private function formatShipment(Shipment $shipment): array
    {
        return [
            'id' => $shipment->id,
            'shipment_number' => $shipment->shipment_number,
            'status' => $shipment->status,
            'carrier' => $shipment->carrier,
            'service' => $shipment->service,
            'tracking_number' => $shipment->tracking_number,
            'tracking_url' => $shipment->tracking_url,
            'label_url' => $shipment->label_url,
            'label_format' => $shipment->label_format,
            'label_cost' => $shipment->label_cost ? (float) $shipment->label_cost : null,
            'total_shipping_cost' => $shipment->total_shipping_cost ? (float) $shipment->total_shipping_cost : null,
            'package_weight_oz' => $shipment->package_weight_oz ? (float) $shipment->package_weight_oz : null,
            'package_length' => $shipment->package_length ? (float) $shipment->package_length : null,
            'package_width' => $shipment->package_width ? (float) $shipment->package_width : null,
            'package_height' => $shipment->package_height ? (float) $shipment->package_height : null,
            'estimated_delivery_date' => $shipment->estimated_delivery_date,
            'actual_delivery_date' => $shipment->actual_delivery_date,
            'shipped_at' => $shipment->shipped_at?->toIso8601String(),
            'label_purchased_at' => $shipment->label_purchased_at?->toIso8601String(),
            'order' => $shipment->relationLoaded('order') && $shipment->order ? [
                'id' => $shipment->order->id,
                'marketplace_order_id' => $shipment->order->marketplace_order_id,
                'marketplace' => $shipment->order->marketplace,
                'status' => $shipment->order->status,
                'buyer_name' => $shipment->order->buyer_name,
                'total_amount' => (float) $shipment->order->total_amount,
                'priority' => $shipment->order->priority,
            ] : null,
            'location' => $shipment->relationLoaded('location') && $shipment->location ? [
                'id' => $shipment->location->id,
                'name' => $shipment->location->name,
                'code' => $shipment->location->code,
            ] : null,
        ];
    }
}
