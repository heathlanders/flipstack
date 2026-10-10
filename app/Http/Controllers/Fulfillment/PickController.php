<?php

namespace App\Http\Controllers\Fulfillment;

use App\Http\Controllers\Controller;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\OrderItem;
use App\Models\Inventory\InventoryLocation;
use App\Services\Fulfillment\FulfillmentPrintService;
use App\Services\Fulfillment\FulfillmentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PickController extends Controller
{
    protected FulfillmentService $fulfillmentService;

    protected FulfillmentPrintService $printService;

    public function __construct(FulfillmentService $fulfillmentService, FulfillmentPrintService $printService)
    {
        $this->fulfillmentService = $fulfillmentService;
        $this->printService = $printService;
    }

    public function pickList(Request $request)
    {
        $locationId = $request->get('location_id');

        $query = Order::with([
            'items.product:id,name,sku,internal_sku',
            'items.product.inventoryLevels:id,product_id,location_id,bin_id,quantity_on_hand',
            'items.product.inventoryLevels.bin:id,zone_id,code,label,aisle,rack,shelf,bin_position',
            'items.product.inventoryLevels.bin.zone:id,code,name',
            'items.product.inventoryLevels.location:id,code,name',
            'location:id,name,code',
        ])
            ->whereIn('status', [Order::STATUS_ASSIGNED, Order::STATUS_PICKING])
            ->orderByRaw("array_position(ARRAY['urgent', 'high', 'standard'], priority)")
            ->orderBy('ship_by_date');

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $orders = $query->get();

        // Project a `pick_bin` field onto each item: prefer InventoryLevel at order's location,
        // fall back to the product's highest-stock level.
        $orders->each(function (Order $order) {
            $orderLocationId = $order->location_id;
            foreach ($order->items as $item) {
                $levels = $item->product?->inventoryLevels ?? collect();
                $level = $levels->firstWhere('location_id', $orderLocationId)
                    ?? $levels->sortByDesc('quantity_on_hand')->first();

                $bin = $level?->bin;
                $zoneCode = $bin?->zone?->code;
                $item->pick_bin = $bin
                    ? ['code' => $bin->code, 'zone' => $zoneCode, 'aisle' => $bin->aisle, 'rack' => $bin->rack, 'shelf' => $bin->shelf]
                    : ($level?->location ? ['code' => $level->location->code, 'zone' => null, 'aisle' => null, 'rack' => null, 'shelf' => null] : null);
            }
        });

        $locations = InventoryLocation::where('organization_id', $request->user()->organization_id)
            ->where('is_active', true)
            ->get(['id', 'name', 'code']);

        $stats = [
            'total_orders' => $orders->count(),
            'total_items' => $orders->sum(fn ($o) => $o->items->where('pick_status', 'pending')->sum('quantity')),
            'urgent' => $orders->where('priority', 'urgent')->count(),
            'overdue' => $orders->filter->isOverdue()->count(),
        ];

        return Inertia::render('Fulfillment/Pick/Index', [
            'orders' => $orders,
            'locations' => $locations,
            'stats' => $stats,
            'filters' => $request->only(['location_id']),
        ]);
    }

    public function startPick(Request $request, Order $order)
    {
        $this->fulfillmentService->startPicking($order, $request->user());

        return back()->with('success', 'Picking started for order '.$order->marketplace_order_id);
    }

    public function pickItem(Request $request, OrderItem $orderItem)
    {
        $request->validate([
            'quantity' => 'nullable|integer|min:1|max:'.$orderItem->quantity,
        ]);

        $this->fulfillmentService->pickItem(
            $orderItem,
            $request->user(),
            $request->get('quantity')
        );

        return back()->with('success', 'Item picked: '.$orderItem->title);
    }

    public function shortItem(Request $request, OrderItem $orderItem)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->fulfillmentService->shortItem($orderItem, $request->user(), $request->reason);

        return back()->with('warning', 'Item shorted: '.$orderItem->title);
    }

    public function completePick(Request $request, Order $order)
    {
        $this->fulfillmentService->completePicking($order, $request->user());

        return back()->with('success', 'Picking completed for order '.$order->marketplace_order_id);
    }

    public function printPickList(Request $request)
    {
        $request->validate([
            'location_id' => 'required|exists:inventory_locations,id',
        ]);

        $location = InventoryLocation::findOrFail($request->location_id);
        $orders = $this->fulfillmentService->getPickList($location);

        if ($orders->isEmpty()) {
            return back()->with('info', 'No items to pick at this location.');
        }

        $result = $this->printService->generatePickList($location, $orders, $request->user());

        return back()->with('success', 'Pick list generated.')->with('download_url', $result['url']);
    }
}
