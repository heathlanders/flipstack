<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Concerns\AnalyticsQueryTrait;
use Illuminate\Support\Facades\DB;

class FulfillmentAnalyticsService
{
    use AnalyticsQueryTrait;

    public function getOverview(): array
    {
        return $this->cached('fulfillment_overview', 900, function () {
            return $this->withComparison(function (string $startDate, string $endDate) {
                $orders = DB::table('orders')
                    ->where('organization_id', $this->organizationId)
                    ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
                    ->whereNull('deleted_at')
                    ->select([
                        DB::raw('COUNT(*) as total_orders'),
                        DB::raw("COUNT(*) FILTER (WHERE status IN ('shipped', 'delivered')) as orders_shipped"),
                        DB::raw("COUNT(*) FILTER (WHERE status IN ('pending', 'assigned', 'picking', 'packing')) as orders_pending"),
                        DB::raw('COALESCE(AVG(EXTRACT(EPOCH FROM (shipped_at - paid_at)) / 3600) FILTER (WHERE shipped_at IS NOT NULL AND paid_at IS NOT NULL), 0) as avg_processing_hours'),
                        DB::raw('COUNT(*) FILTER (WHERE shipped_at IS NOT NULL AND ship_by_date IS NOT NULL AND shipped_at <= ship_by_date) as shipped_on_time'),
                        DB::raw('COUNT(*) FILTER (WHERE ship_by_date IS NOT NULL AND shipped_at IS NOT NULL) as with_ship_by'),
                        DB::raw('COUNT(*) FILTER (WHERE shipped_at IS NOT NULL AND paid_at IS NOT NULL AND DATE(shipped_at) = DATE(paid_at)) as same_day_shipped'),
                    ])
                    ->first();

                $shipmentCost = DB::table('shipments')
                    ->where('organization_id', $this->organizationId)
                    ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
                    ->where('total_shipping_cost', '>', 0)
                    ->select([
                        DB::raw('COALESCE(AVG(total_shipping_cost), 0) as avg_cost_per_order'),
                    ])
                    ->first();

                $pickAccuracy = DB::table('order_items')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->where('orders.organization_id', $this->organizationId)
                    ->whereBetween('orders.created_at', [$startDate, $endDate.' 23:59:59'])
                    ->whereNull('orders.deleted_at')
                    ->select([
                        DB::raw('COUNT(*) as total_items'),
                        DB::raw("COUNT(*) FILTER (WHERE order_items.pick_status = 'picked') as picked_items"),
                        DB::raw("COUNT(*) FILTER (WHERE order_items.pick_status = 'short') as shorted_items"),
                    ])
                    ->first();

                $totalItems = (int) $pickAccuracy->total_items;
                $pickedItems = (int) $pickAccuracy->picked_items;
                $onTimeRate = (int) $orders->with_ship_by > 0
                    ? round(((int) $orders->shipped_on_time / (int) $orders->with_ship_by) * 100, 2)
                    : 0;
                $sameDayRate = (int) $orders->orders_shipped > 0
                    ? round(((int) $orders->same_day_shipped / (int) $orders->orders_shipped) * 100, 2)
                    : 0;

                return [
                    'total_orders' => (int) $orders->total_orders,
                    'orders_shipped' => (int) $orders->orders_shipped,
                    'orders_pending' => (int) $orders->orders_pending,
                    'avg_processing_hours' => round((float) $orders->avg_processing_hours, 2),
                    'on_time_rate' => $onTimeRate,
                    'same_day_rate' => $sameDayRate,
                    'pick_accuracy' => $totalItems > 0 ? round(($pickedItems / $totalItems) * 100, 2) : 100,
                    'avg_cost_per_order' => round((float) $shipmentCost->avg_cost_per_order, 2),
                ];
            });
        });
    }

    public function getOrderToShipTime(): array
    {
        return $this->cached('fulfillment_order_to_ship', 900, function () {
            $row = DB::table('orders')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('deleted_at')
                ->whereIn('status', ['shipped', 'delivered'])
                ->whereNotNull('paid_at')
                ->whereNotNull('shipped_at')
                ->select([
                    DB::raw('COALESCE(AVG(EXTRACT(EPOCH FROM (shipped_at - paid_at)) / 3600), 0) as avg_hours'),
                    DB::raw('COALESCE(PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (shipped_at - paid_at)) / 3600), 0) as median_hours'),
                    DB::raw('COALESCE(PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (shipped_at - paid_at)) / 3600), 0) as p95_hours'),
                ])
                ->first();

            return [
                'avg_hours' => round((float) $row->avg_hours, 2),
                'median_hours' => round((float) $row->median_hours, 2),
                'p95_hours' => round((float) $row->p95_hours, 2),
            ];
        });
    }

    public function getOnTimeDeliveryRate(): array
    {
        return $this->cached('fulfillment_on_time_rate', 900, function () {
            $row = DB::table('orders')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('deleted_at')
                ->whereNotNull('ship_by_date')
                ->whereNotNull('shipped_at')
                ->select([
                    DB::raw('COUNT(*) as total_with_ship_by'),
                    DB::raw('COUNT(*) FILTER (WHERE shipped_at <= ship_by_date) as shipped_on_time'),
                ])
                ->first();

            $total = (int) $row->total_with_ship_by;
            $onTime = (int) $row->shipped_on_time;

            return [
                'total_with_ship_by' => $total,
                'shipped_on_time' => $onTime,
                'rate' => $total > 0 ? round(($onTime / $total) * 100, 2) : 0,
            ];
        });
    }

    public function getPickAccuracy(): array
    {
        return $this->cached('fulfillment_pick_accuracy', 900, function () {
            $row = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.organization_id', $this->organizationId)
                ->whereBetween('orders.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('orders.deleted_at')
                ->select([
                    DB::raw('COUNT(*) as total_items'),
                    DB::raw("COUNT(*) FILTER (WHERE order_items.pick_status = 'picked') as picked_items"),
                    DB::raw("COUNT(*) FILTER (WHERE order_items.pick_status = 'short') as shorted_items"),
                ])
                ->first();

            $total = (int) $row->total_items;
            $picked = (int) $row->picked_items;

            return [
                'total_items' => $total,
                'picked_items' => $picked,
                'shorted_items' => (int) $row->shorted_items,
                'accuracy_pct' => $total > 0 ? round(($picked / $total) * 100, 2) : 100,
            ];
        });
    }

    public function getFulfillmentCostPerOrder(): array
    {
        return $this->cached('fulfillment_cost_per_order', 900, function () {
            $row = DB::table('shipments')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->where('total_shipping_cost', '>', 0)
                ->select([
                    DB::raw('COALESCE(AVG(total_shipping_cost), 0) as avg_cost'),
                    DB::raw('COALESCE(MIN(total_shipping_cost), 0) as min_cost'),
                    DB::raw('COALESCE(MAX(total_shipping_cost), 0) as max_cost'),
                ])
                ->first();

            return [
                'avg' => round((float) $row->avg_cost, 2),
                'min' => round((float) $row->min_cost, 2),
                'max' => round((float) $row->max_cost, 2),
            ];
        });
    }

    public function getBacklogAge(): array
    {
        return $this->cached('fulfillment_backlog_age', 900, function () {
            $now = now();
            $row = DB::table('orders')
                ->where('organization_id', $this->organizationId)
                ->whereIn('status', ['pending', 'assigned', 'picking', 'packing'])
                ->whereNull('deleted_at')
                ->select([
                    DB::raw("COUNT(*) FILTER (WHERE EXTRACT(EPOCH FROM ('{$now}'::timestamp - created_at)) / 86400 <= 1) as bucket_0_1"),
                    DB::raw("COUNT(*) FILTER (WHERE EXTRACT(EPOCH FROM ('{$now}'::timestamp - created_at)) / 86400 > 1 AND EXTRACT(EPOCH FROM ('{$now}'::timestamp - created_at)) / 86400 <= 3) as bucket_1_3"),
                    DB::raw("COUNT(*) FILTER (WHERE EXTRACT(EPOCH FROM ('{$now}'::timestamp - created_at)) / 86400 > 3 AND EXTRACT(EPOCH FROM ('{$now}'::timestamp - created_at)) / 86400 <= 7) as bucket_3_7"),
                    DB::raw("COUNT(*) FILTER (WHERE EXTRACT(EPOCH FROM ('{$now}'::timestamp - created_at)) / 86400 > 7) as bucket_7_plus"),
                ])
                ->first();

            return [
                '0-1_days' => (int) $row->bucket_0_1,
                '1-3_days' => (int) $row->bucket_1_3,
                '3-7_days' => (int) $row->bucket_3_7,
                '7+_days' => (int) $row->bucket_7_plus,
            ];
        });
    }

    public function getReturnMetrics(): array
    {
        return $this->cached('fulfillment_returns', 900, function () {
            $returns = DB::table('return_requests')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->select([
                    DB::raw('COUNT(*) as total_returns'),
                    DB::raw('COALESCE(AVG(EXTRACT(EPOCH FROM (refunded_at - created_at)) / 3600) FILTER (WHERE refunded_at IS NOT NULL), 0) as avg_refund_time_hours'),
                    DB::raw('COUNT(*) FILTER (WHERE restockable = true) as restockable_count'),
                ])
                ->first();

            $orderCount = DB::table('orders')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('deleted_at')
                ->count();

            $reasons = DB::table('return_requests')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->select([
                    'reason',
                    DB::raw('COUNT(*) as count'),
                ])
                ->groupBy('reason')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => [
                    'reason' => $row->reason,
                    'count' => (int) $row->count,
                ])
                ->toArray();

            $totalReturns = (int) $returns->total_returns;

            return [
                'total_returns' => $totalReturns,
                'return_rate' => $orderCount > 0 ? round(($totalReturns / $orderCount) * 100, 2) : 0,
                'avg_refund_time_hours' => round((float) $returns->avg_refund_time_hours, 2),
                'restocking_rate' => $totalReturns > 0
                    ? round(((int) $returns->restockable_count / $totalReturns) * 100, 2)
                    : 0,
                'reasons' => $reasons,
            ];
        });
    }

    public function getCarrierPerformance(): array
    {
        return $this->cached('fulfillment_carrier_perf', 900, function () {
            return DB::table('shipments')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNotNull('carrier')
                ->select([
                    'carrier',
                    DB::raw('COUNT(*) as volume'),
                    DB::raw('COALESCE(AVG(total_shipping_cost), 0) as avg_cost'),
                    DB::raw('COALESCE(AVG(actual_delivery_date - shipped_at::date) FILTER (WHERE actual_delivery_date IS NOT NULL AND shipped_at IS NOT NULL), 0) as avg_transit_days'),
                ])
                ->groupBy('carrier')
                ->orderByDesc('volume')
                ->get()
                ->map(fn ($row) => [
                    'carrier' => $row->carrier,
                    'volume' => (int) $row->volume,
                    'avg_cost' => round((float) $row->avg_cost, 2),
                    'avg_transit_days' => round((float) $row->avg_transit_days, 2),
                ])
                ->toArray();
        });
    }

    public function getTimeInStage(): array
    {
        return $this->cached('fulfillment_time_in_stage', 900, function () {
            $stages = DB::select("
                WITH order_stages AS (
                    SELECT
                        fl.order_id,
                        MIN(fl.created_at) FILTER (WHERE fl.action = 'order_imported') as order_imported_at,
                        MIN(fl.created_at) FILTER (WHERE fl.action = 'pick_started') as pick_started_at,
                        MIN(fl.created_at) FILTER (WHERE fl.action = 'pick_completed') as pick_completed_at,
                        MIN(fl.created_at) FILTER (WHERE fl.action = 'pack_started') as pack_started_at,
                        MIN(fl.created_at) FILTER (WHERE fl.action = 'pack_completed') as pack_completed_at,
                        MIN(fl.created_at) FILTER (WHERE fl.action = 'shipment_created') as shipment_created_at
                    FROM fulfillment_logs fl
                    WHERE fl.organization_id = ?
                        AND fl.created_at BETWEEN ? AND (? || ' 23:59:59')::timestamp
                        AND fl.order_id IS NOT NULL
                    GROUP BY fl.order_id
                )
                SELECT
                    COALESCE(AVG(EXTRACT(EPOCH FROM (pick_started_at - order_imported_at)) / 3600) FILTER (WHERE pick_started_at IS NOT NULL AND order_imported_at IS NOT NULL), 0) as avg_pending_to_picking_hours,
                    COALESCE(AVG(EXTRACT(EPOCH FROM (pick_completed_at - pick_started_at)) / 3600) FILTER (WHERE pick_completed_at IS NOT NULL AND pick_started_at IS NOT NULL), 0) as avg_picking_hours,
                    COALESCE(AVG(EXTRACT(EPOCH FROM (pack_completed_at - pack_started_at)) / 3600) FILTER (WHERE pack_completed_at IS NOT NULL AND pack_started_at IS NOT NULL), 0) as avg_packing_hours,
                    COALESCE(AVG(EXTRACT(EPOCH FROM (shipment_created_at - pack_completed_at)) / 3600) FILTER (WHERE shipment_created_at IS NOT NULL AND pack_completed_at IS NOT NULL), 0) as avg_to_ship_hours
                FROM order_stages
            ", [$this->organizationId, $this->startDate, $this->endDate]);

            $row = $stages[0] ?? null;

            return [
                'pending_to_picking_hours' => round((float) ($row->avg_pending_to_picking_hours ?? 0), 2),
                'picking_hours' => round((float) ($row->avg_picking_hours ?? 0), 2),
                'packing_hours' => round((float) ($row->avg_packing_hours ?? 0), 2),
                'to_ship_hours' => round((float) ($row->avg_to_ship_hours ?? 0), 2),
            ];
        });
    }

    public function getProcessingTrend(): array
    {
        return $this->cached('fulfillment_processing_trend', 900, function () {
            return DB::table('orders')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('deleted_at')
                ->whereIn('status', ['shipped', 'delivered'])
                ->whereNotNull('shipped_at')
                ->select([
                    DB::raw('DATE(shipped_at) as date'),
                    DB::raw('COUNT(*) as orders_shipped'),
                    DB::raw('COALESCE(AVG(EXTRACT(EPOCH FROM (shipped_at - paid_at)) / 3600) FILTER (WHERE paid_at IS NOT NULL), 0) as avg_processing_hours'),
                ])
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'orders_shipped' => (int) $row->orders_shipped,
                    'avg_processing_hours' => round((float) $row->avg_processing_hours, 2),
                ])
                ->toArray();
        });
    }
}
