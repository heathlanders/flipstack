<?php

namespace App\Http\Controllers\Fulfillment;

use App\Http\Controllers\Controller;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use App\Services\Fulfillment\FulfillmentPrintService;
use App\Services\Fulfillment\FulfillmentService;
use App\Services\Fulfillment\ShipmentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PackController extends Controller
{
    protected FulfillmentService $fulfillmentService;

    protected ShipmentService $shipmentService;

    protected FulfillmentPrintService $printService;

    public function __construct(
        FulfillmentService $fulfillmentService,
        ShipmentService $shipmentService,
        FulfillmentPrintService $printService
    ) {
        $this->fulfillmentService = $fulfillmentService;
        $this->shipmentService = $shipmentService;
        $this->printService = $printService;
    }

    public function packQueue(Request $request)
    {
        $orders = Order::with([
            'items.product:id,name,sku',
            'location:id,name,code',
            'shipments',
            'assignedPacker:id,name',
        ])
            ->whereIn('status', [Order::STATUS_PICKED, Order::STATUS_PACKING, Order::STATUS_PACKED])
            ->orderByRaw("array_position(ARRAY['urgent', 'high', 'standard'], priority)")
            ->orderBy('ship_by_date')
            ->paginate(20);

        $stats = [
            'awaiting_pack' => Order::where('status', Order::STATUS_PICKED)->count(),
            'packing' => Order::where('status', Order::STATUS_PACKING)->count(),
            'awaiting_label' => Order::where('status', Order::STATUS_PACKED)
                ->whereDoesntHave('shipments', fn ($q) => $q->whereNotNull('label_url'))
                ->count(),
            'ready_to_ship' => Shipment::where('status', Shipment::STATUS_LABEL_PURCHASED)->count(),
        ];

        return Inertia::render('Fulfillment/Pack/Index', [
            'orders' => $orders,
            'stats' => $stats,
        ]);
    }

    public function startPack(Request $request, Order $order)
    {
        $this->fulfillmentService->startPacking($order, $request->user());

        // Create shipment with all picked items
        $items = $order->items->where('pick_status', 'picked')->map(function ($item) {
            return [
                'order_item_id' => $item->id,
                'quantity' => $item->quantity,
            ];
        })->values()->toArray();

        if (! empty($items) && $order->shipments()->count() === 0) {
            $this->shipmentService->createShipment($order, $items, $order->location);
        }

        return back()->with('success', 'Packing started for order '.$order->marketplace_order_id);
    }

    public function getRates(Request $request, Order $order)
    {
        $shipment = $order->shipments()->where('status', Shipment::STATUS_PENDING)->first();

        if (! $shipment) {
            return back()->with('error', 'No pending shipment found for this order.');
        }

        // Update dimensions if provided
        if ($request->filled('weight_oz') || $request->filled('length')) {
            $this->shipmentService->updateDimensions($shipment, $request->only(['weight_oz', 'length', 'width', 'height']));
        }

        $rates = $this->shipmentService->getRates($shipment);

        return back()->with('rates', $rates->toArray());
    }

    public function purchaseLabel(Request $request, Order $order)
    {
        $request->validate([
            'rate_id' => 'required|string',
            'weight_oz' => 'nullable|numeric|min:0.1',
            'length' => 'nullable|numeric|min:0.1',
            'width' => 'nullable|numeric|min:0.1',
            'height' => 'nullable|numeric|min:0.1',
        ]);

        $shipment = $order->shipments()->whereIn('status', [Shipment::STATUS_PENDING, Shipment::STATUS_PACKED])->first();

        if (! $shipment) {
            return back()->with('error', 'No shipment available for label purchase.');
        }

        // Update dimensions
        if ($request->filled('weight_oz')) {
            $this->shipmentService->updateDimensions($shipment, $request->only(['weight_oz', 'length', 'width', 'height']));
        }

        $shipment = $this->shipmentService->purchaseLabel($shipment, $request->rate_id, $request->user());

        // Auto-print shipping label if configured
        $this->printService->autoPrintShippingLabel($shipment, $request->user());

        return back()->with('success', 'Label purchased. Tracking: '.$shipment->tracking_number);
    }

    public function completePack(Request $request, Order $order)
    {
        $request->validate([
            'weight_oz' => 'nullable|numeric|min:0.1',
            'length' => 'nullable|numeric|min:0.1',
            'width' => 'nullable|numeric|min:0.1',
            'height' => 'nullable|numeric|min:0.1',
        ]);

        $dimensions = $request->only(['weight_oz', 'length', 'width', 'height']);
        $this->fulfillmentService->completePacking($order, $dimensions, $request->user());

        // Generate packing slip (auto-prints if configured)
        $this->printService->generatePackingSlip($order, $request->user());

        return back()->with('success', 'Packing completed for order '.$order->marketplace_order_id);
    }

    public function printPackingSlip(Request $request, Order $order)
    {
        $result = $this->printService->generatePackingSlip($order, $request->user(), true);

        return back()->with('success', 'Packing slip generated.')->with('download_url', $result['url']);
    }
}
