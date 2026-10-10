<?php

namespace App\Http\Controllers\Fulfillment;

use App\Http\Controllers\Controller;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use App\Models\Inventory\InventoryLocation;
use App\Models\User\User;
use App\Services\Fulfillment\FulfillmentAuditService;
use App\Services\Fulfillment\FulfillmentService;
use App\Services\Fulfillment\ShipmentService;
use App\Services\Fulfillment\TrackingService;
use App\Services\Tracking\TrackingSyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    protected FulfillmentService $fulfillmentService;

    protected FulfillmentAuditService $auditService;

    public function __construct(FulfillmentService $fulfillmentService, FulfillmentAuditService $auditService)
    {
        $this->fulfillmentService = $fulfillmentService;
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        $query = Order::with(['location:id,name,code', 'assignedPicker:id,name', 'assignedPacker:id,name'])
            ->withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('marketplace')) {
            $query->where('marketplace', $request->marketplace);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('marketplace_order_id', 'like', "%{$search}%")
                    ->orWhere('buyer_name', 'like', "%{$search}%")
                    ->orWhere('buyer_username', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDir);

        $orders = $query->paginate(25)->withQueryString();

        $stats = [
            'pending' => Order::where('status', Order::STATUS_PENDING)->count(),
            'picking' => Order::whereIn('status', [Order::STATUS_ASSIGNED, Order::STATUS_PICKING])->count(),
            'packing' => Order::whereIn('status', [Order::STATUS_PICKED, Order::STATUS_PACKING])->count(),
            'shipped' => Order::whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_IN_TRANSIT])->count(),
            'overdue' => Order::overdue()->count(),
            'on_hold' => Order::where('status', Order::STATUS_ON_HOLD)->count(),
        ];

        $locations = InventoryLocation::where('organization_id', $request->user()->organization_id)
            ->where('is_active', true)
            ->get(['id', 'name', 'code']);

        return Inertia::render('Fulfillment/Orders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'locations' => $locations,
            'filters' => $request->only(['status', 'priority', 'location_id', 'marketplace', 'search', 'date_from', 'date_to', 'sort', 'direction']),
        ]);
    }

    public function show(Order $order)
    {
        $order->load([
            'items.product:id,name,sku,internal_sku',
            'items.supplier:id,name',
            'shipments.shipmentItems.orderItem',
            'shipments.trackingEvents',
            'returnRequests',
            'supplierFulfillmentRequest.supplier:id,name',
            'location:id,name,code',
            'assignedPicker:id,name',
            'assignedPacker:id,name',
            'credential:id,name,seller_username',
            'customer:id,name',
        ]);

        $timeline = $this->auditService->getOrderTimeline($order);

        $locations = InventoryLocation::where('organization_id', $order->organization_id)
            ->where('is_active', true)
            ->get(['id', 'name', 'code']);

        $pickers = User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['fulfillment_manager', 'shipper']);
        })->get(['id', 'name']);

        return Inertia::render('Fulfillment/Orders/Show', [
            'order' => $order,
            'timeline' => $timeline,
            'locations' => $locations,
            'availableWorkers' => $pickers,
        ]);
    }

    public function assign(Request $request, Order $order)
    {
        $request->validate([
            'location_id' => 'required|exists:inventory_locations,id',
        ]);

        $location = InventoryLocation::findOrFail($request->location_id);
        $this->fulfillmentService->assignOrder($order, $location, $request->user());

        return back()->with('success', 'Order assigned to '.$location->name);
    }

    public function hold(Request $request, Order $order)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $this->fulfillmentService->holdOrder($order, $request->reason, $request->user());

        return back()->with('success', 'Order placed on hold.');
    }

    public function release(Request $request, Order $order)
    {
        $this->fulfillmentService->releaseOrder($order, $request->user());

        return back()->with('success', 'Order released from hold.');
    }

    public function cancel(Request $request, Order $order)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $this->fulfillmentService->cancelOrder($order, $request->reason, $request->user());

        return back()->with('success', 'Order cancelled.');
    }

    public function bulkAssign(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
            'location_id' => 'required|exists:inventory_locations,id',
        ]);

        $location = InventoryLocation::findOrFail($request->location_id);
        $orders = Order::whereIn('id', $request->order_ids)->get();

        $assigned = 0;
        foreach ($orders as $order) {
            if ($order->status === Order::STATUS_PENDING) {
                $this->fulfillmentService->assignOrder($order, $location, $request->user());
                $assigned++;
            }
        }

        return back()->with('success', "{$assigned} orders assigned to {$location->name}.");
    }

    public function bulkPriority(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
            'priority' => 'required|in:standard,high,urgent',
        ]);

        $orders = Order::whereIn('id', $request->order_ids)->get();

        foreach ($orders as $order) {
            $this->fulfillmentService->changePriority($order, $request->priority, $request->user());
        }

        return back()->with('success', count($request->order_ids).' orders updated to '.$request->priority.' priority.');
    }

    public function auditTrail(Order $order)
    {
        $timeline = $this->auditService->getOrderTimeline($order);

        return response()->json(['timeline' => $timeline]);
    }

    public function manualShip(Request $request, Order $order, ShipmentService $shipmentService, TrackingService $trackingService)
    {
        $data = $request->validate([
            'carrier' => 'required|string|max:64',
            'service' => 'nullable|string|max:128',
            'tracking_number' => 'required|string|max:128',
            'tracking_url' => 'nullable|url|max:500',
            'shipped_at' => 'nullable|date',
            'location_id' => 'nullable|exists:inventory_locations,id',
        ]);

        if (in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED], true)) {
            return back()->with('error', 'Cannot manually ship an order in status: '.$order->status);
        }

        if (! $order->location_id && ! empty($data['location_id'])) {
            $location = InventoryLocation::findOrFail($data['location_id']);
            $this->fulfillmentService->assignOrder($order, $location, $request->user());
            $order->refresh();
        }

        $shipment = $shipmentService->manualShip($order, $data, $request->user());

        $this->fulfillmentService->shipOrder($order->fresh(), $request->user());

        $trackingService->uploadTrackingToMarketplace($shipment);

        return back()->with('success', "Tracking {$shipment->tracking_number} saved and shipment marked as shipped.");
    }

    public function refreshTracking(Shipment $shipment, TrackingSyncService $tracking)
    {
        $created = $tracking->sync($shipment);

        return back()->with('success', $created > 0
            ? "Tracking refreshed: {$created} new event(s)."
            : 'Tracking refreshed; no new events.');
    }
}
