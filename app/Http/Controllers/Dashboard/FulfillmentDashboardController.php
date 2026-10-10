<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Catalog\Product;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use App\Models\Inventory\InventoryLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FulfillmentDashboardController extends BaseDashboardController
{
    public function show(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        return Inertia::render('Dashboard/Fulfillment', [
            'dashboardType' => 'fulfillment',
            'user' => [
                'name' => $user->name,
                'role' => $user->getPrimaryRole(),
                'permissions' => $user->getEffectivePermissions(),
            ],
            'metrics' => $this->getFulfillmentMetrics($user),
            'recentActivity' => $this->getRecentActivity($user, 'fulfillment'),
            'alerts' => $this->getAlerts($user),
            'quickActions' => $user->getQuickActions(),
            'orderQueue' => $this->getOrderQueue($organization),
            'shippingMetrics' => $this->getShippingMetrics($organization),
            'inventoryLocations' => $this->getInventoryLocations($organization),
            'carrierPerformance' => $this->getCarrierPerformance($organization),
        ]);
    }

    protected function getFulfillmentMetrics($user)
    {
        $organization = $user->organization;
        $orgId = $organization->id;

        $pendingOrders = Order::where('organization_id', $orgId)
            ->where('status', Order::STATUS_PENDING)
            ->count();

        $processingOrders = Order::where('organization_id', $orgId)
            ->whereIn('status', [
                Order::STATUS_ASSIGNED,
                Order::STATUS_PICKING,
                Order::STATUS_PICKED,
                Order::STATUS_PACKING,
                Order::STATUS_PACKED,
            ])
            ->count();

        $shippedToday = Order::where('organization_id', $orgId)
            ->where('status', Order::STATUS_SHIPPED)
            ->whereDate('shipped_at', today())
            ->count();

        $totalProducts = Product::where('organization_id', $orgId)
            ->where('stock_quantity', '>', 0)
            ->count();

        $lowStockItems = Product::where('organization_id', $orgId)
            ->where('stock_quantity', '<=', 3)
            ->where('stock_quantity', '>', 0)
            ->count();

        // Performance metrics from shipments in last 30 days
        $recentShipments = Shipment::where('organization_id', $orgId)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('shipped_at');

        $avgProcessingHours = (float) ((clone $recentShipments)
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (shipped_at - created_at)) / 3600) as avg_hours')
            ->value('avg_hours') ?? 0);

        $sameDayCount = (clone $recentShipments)
            ->whereRaw('DATE(shipped_at) = DATE(created_at)')
            ->count();

        $totalShipped = (clone $recentShipments)->count();
        $sameDayRate = $totalShipped > 0 ? round(($sameDayCount / $totalShipped) * 100, 1) : 0;

        $deliveredCount = Shipment::where('organization_id', $orgId)
            ->where('created_at', '>=', now()->subDays(30))
            ->where('status', Shipment::STATUS_DELIVERED)
            ->count();

        $onTimeRate = $totalShipped > 0 ? round(($deliveredCount / $totalShipped) * 100, 1) : 0;

        return [
            'orders' => [
                'pending' => $pendingOrders,
                'processing' => $processingOrders,
                'shipped_today' => $shippedToday,
                'total_active' => $pendingOrders + $processingOrders,
            ],
            'inventory' => [
                'available_products' => $totalProducts,
                'low_stock_alerts' => $lowStockItems,
                'stockout_risk' => round(($lowStockItems / max($totalProducts, 1)) * 100, 1),
            ],
            'performance' => [
                'avg_processing_time' => round($avgProcessingHours, 1),
                'same_day_shipping_rate' => $sameDayRate,
                'on_time_delivery' => $onTimeRate,
            ],
        ];
    }

    protected function getOrderQueue($organization)
    {
        $orgId = $organization->id;

        $toPick = Order::where('organization_id', $orgId)
            ->whereIn('status', [Order::STATUS_ASSIGNED, Order::STATUS_PICKING])
            ->withCount('items')
            ->with(['items:id,order_id,title,quantity'])
            ->orderBy('priority', 'desc')
            ->orderBy('ship_by_date')
            ->limit(10)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->marketplace_order_id ?? $order->id,
                'customer' => $order->buyer_name,
                'items' => $order->items_count ?? 0,
                'priority' => $order->priority,
                'deadline' => $order->ship_by_date,
                'platform' => $order->marketplace,
                'total' => $order->total_amount,
            ]);

        $toPack = Order::where('organization_id', $orgId)
            ->whereIn('status', [Order::STATUS_PICKED, Order::STATUS_PACKING])
            ->withCount('items')
            ->orderBy('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->marketplace_order_id ?? $order->id,
                'customer' => $order->buyer_name,
                'items' => $order->items_count ?? 0,
                'picked_at' => $order->updated_at,
                'platform' => $order->marketplace,
                'shipping_method' => null,
            ]);

        $readyToShip = Order::where('organization_id', $orgId)
            ->where('status', Order::STATUS_PACKED)
            ->withCount('items')
            ->orderBy('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->marketplace_order_id ?? $order->id,
                'customer' => $order->buyer_name,
                'items' => $order->items_count ?? 0,
                'packed_at' => $order->updated_at,
                'platform' => $order->marketplace,
                'shipping_method' => null,
            ]);

        return [
            'to_pick' => $toPick,
            'to_pack' => $toPack,
            'ready_to_ship' => $readyToShip,
        ];
    }

    protected function getShippingMetrics($organization)
    {
        $orgId = $organization->id;

        $todayShipments = Shipment::where('organization_id', $orgId)
            ->whereDate('shipped_at', today())
            ->count();

        $yesterdayShipments = Shipment::where('organization_id', $orgId)
            ->whereDate('shipped_at', today()->subDay())
            ->count();

        $avgLast7Days = Shipment::where('organization_id', $orgId)
            ->where('shipped_at', '>=', now()->subDays(7))
            ->count() / 7;

        // Shipping methods breakdown (last 30 days)
        $shippingMethods = Shipment::where('organization_id', $orgId)
            ->where('shipped_at', '>=', now()->subDays(30))
            ->whereNotNull('carrier')
            ->select('carrier', 'service', DB::raw('COUNT(*) as count'), DB::raw('AVG(total_shipping_cost) as avg_cost'))
            ->groupBy('carrier', 'service')
            ->get()
            ->mapWithKeys(fn ($row) => [
                strtolower($row->carrier).'_'.str_replace(' ', '_', strtolower($row->service ?? 'standard')) => [
                    'count' => $row->count,
                    'avg_cost' => round((float) ($row->avg_cost ?? 0), 2),
                ],
            ]);

        // Delivery performance
        $delivered = Shipment::where('organization_id', $orgId)
            ->where('status', Shipment::STATUS_DELIVERED)
            ->where('shipped_at', '>=', now()->subDays(30));

        $onTimeCount = (clone $delivered)
            ->whereNotNull('estimated_delivery_date')
            ->whereRaw('actual_delivery_date <= estimated_delivery_date')
            ->count();

        $totalDelivered = (clone $delivered)->count();
        $onTimeRate = $totalDelivered > 0 ? round(($onTimeCount / $totalDelivered) * 100, 1) : 100;

        $avgDeliveryDays = (float) ((clone $delivered)
            ->whereNotNull('actual_delivery_date')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (actual_delivery_date - shipped_at)) / 86400) as avg_days')
            ->value('avg_days') ?? 0);

        $trackingIssues = Shipment::where('organization_id', $orgId)
            ->where('status', Shipment::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'daily_volume' => [
                'today' => $todayShipments,
                'yesterday' => $yesterdayShipments,
                'avg_last_7_days' => round($avgLast7Days, 1),
            ],
            'shipping_methods' => $shippingMethods,
            'delivery_performance' => [
                'on_time_rate' => $onTimeRate,
                'avg_delivery_time' => round($avgDeliveryDays, 1),
                'tracking_issues' => $trackingIssues,
            ],
        ];
    }

    protected function getInventoryLocations($organization)
    {
        return InventoryLocation::where('organization_id', $organization->id)
            ->where('is_active', true)
            ->withCount(['inventoryLevels as product_count' => function ($q) {
                $q->where('quantity_on_hand', '>', 0);
            }])
            ->get()
            ->map(fn ($location) => [
                'name' => $location->name,
                'product_count' => $location->product_count,
                'total_quantity' => $location->inventoryLevels()->sum('quantity_on_hand'),
                'is_ship_from' => $location->is_ship_from,
                'utilization' => min(100, ($location->product_count / max(50, 1)) * 100),
            ]);
    }

    protected function getCarrierPerformance($organization)
    {
        $orgId = $organization->id;

        $carriers = Shipment::where('organization_id', $orgId)
            ->where('shipped_at', '>=', now()->subDays(30))
            ->whereNotNull('carrier')
            ->select(
                'carrier',
                DB::raw('COUNT(*) as packages_shipped'),
                DB::raw('AVG(total_shipping_cost) as avg_cost')
            )
            ->groupBy('carrier')
            ->get();

        $result = [];
        foreach ($carriers as $carrier) {
            $key = strtolower($carrier->carrier);
            $delivered = Shipment::where('organization_id', $orgId)
                ->where('carrier', $carrier->carrier)
                ->where('shipped_at', '>=', now()->subDays(30))
                ->where('status', Shipment::STATUS_DELIVERED)
                ->count();

            $onTimeRate = $carrier->packages_shipped > 0
                ? round(($delivered / $carrier->packages_shipped) * 100, 1)
                : 0;

            $result[$key] = [
                'name' => $carrier->carrier,
                'packages_shipped' => $carrier->packages_shipped,
                'on_time_rate' => $onTimeRate,
                'avg_cost' => round((float) ($carrier->avg_cost ?? 0), 2),
                'rating' => min(5, max(1, round($onTimeRate / 20, 1))),
            ];
        }

        return $result;
    }
}
