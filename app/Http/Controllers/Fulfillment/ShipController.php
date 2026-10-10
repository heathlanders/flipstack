<?php

namespace App\Http\Controllers\Fulfillment;

use App\Http\Controllers\Controller;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use App\Services\Fulfillment\FulfillmentPrintService;
use App\Services\Fulfillment\FulfillmentService;
use App\Services\Fulfillment\ShipmentService;
use App\Services\Fulfillment\TrackingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShipController extends Controller
{
    protected ShipmentService $shipmentService;

    protected FulfillmentService $fulfillmentService;

    protected TrackingService $trackingService;

    protected FulfillmentPrintService $printService;

    public function __construct(
        ShipmentService $shipmentService,
        FulfillmentService $fulfillmentService,
        TrackingService $trackingService,
        FulfillmentPrintService $printService
    ) {
        $this->shipmentService = $shipmentService;
        $this->fulfillmentService = $fulfillmentService;
        $this->trackingService = $trackingService;
        $this->printService = $printService;
    }

    public function shipQueue(Request $request)
    {
        $shipments = Shipment::with([
            'order:id,marketplace_order_id,buyer_name,shipping_city,shipping_state,priority,status',
            'order.items:id,order_id,title,quantity',
            'location:id,name,code',
        ])
            ->whereIn('status', [Shipment::STATUS_LABEL_PURCHASED, Shipment::STATUS_PACKED])
            ->orderBy('created_at')
            ->paginate(25);

        $stats = [
            'ready_to_ship' => Shipment::whereIn('status', [Shipment::STATUS_LABEL_PURCHASED, Shipment::STATUS_PACKED])->count(),
            'shipped_today' => Shipment::whereDate('shipped_at', today())->count(),
            'in_transit' => Shipment::where('status', Shipment::STATUS_IN_TRANSIT)->count(),
            'delivered_today' => Shipment::whereDate('actual_delivery_date', today())->count(),
        ];

        return Inertia::render('Fulfillment/Ship/Index', [
            'shipments' => $shipments,
            'stats' => $stats,
        ]);
    }

    public function markShipped(Request $request, Shipment $shipment)
    {
        $this->shipmentService->markShipped($shipment, $request->user());

        // Update parent order status
        $this->fulfillmentService->shipOrder($shipment->order, $request->user());

        // Upload tracking to marketplace
        $this->trackingService->uploadTrackingToMarketplace($shipment);

        return back()->with('success', 'Shipment marked as shipped. Tracking: '.$shipment->tracking_number);
    }

    public function voidLabel(Request $request, Shipment $shipment)
    {
        $this->shipmentService->voidLabel($shipment, $request->user());

        return back()->with('success', 'Label voided for shipment '.$shipment->shipment_number);
    }

    public function bulkMarkShipped(Request $request)
    {
        $request->validate([
            'shipment_ids' => 'required|array|min:1',
            'shipment_ids.*' => 'exists:shipments,id',
        ]);

        $shipments = Shipment::whereIn('id', $request->shipment_ids)
            ->whereIn('status', [Shipment::STATUS_LABEL_PURCHASED, Shipment::STATUS_PACKED])
            ->get();

        $shipped = 0;
        foreach ($shipments as $shipment) {
            $this->shipmentService->markShipped($shipment, $request->user());
            $this->fulfillmentService->shipOrder($shipment->order, $request->user());
            $this->trackingService->uploadTrackingToMarketplace($shipment);
            $shipped++;
        }

        return back()->with('success', "{$shipped} shipments marked as shipped.");
    }

    public function batchPrintLabels(Request $request)
    {
        $request->validate([
            'shipment_ids' => 'required|array|min:1',
            'shipment_ids.*' => 'exists:shipments,id',
        ]);

        $shipments = Shipment::whereIn('id', $request->shipment_ids)
            ->whereNotNull('label_url')
            ->get();

        $results = $this->printService->batchPrintShippingLabels($shipments, $request->user());

        return back()->with('success', "Printed {$results['printed']} labels. Failed: {$results['failed']}. Skipped: {$results['skipped']}.");
    }

    public function downloadLabel(Shipment $shipment)
    {
        if (! $shipment->label_url) {
            abort(404, 'No label available for this shipment.');
        }

        return response()->json(['label_url' => $shipment->label_url]);
    }
}
