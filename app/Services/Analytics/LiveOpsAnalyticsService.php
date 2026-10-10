<?php

namespace App\Services\Analytics;

use App\Models\Fulfillment\FulfillmentLog;
use App\Models\Fulfillment\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LiveOpsAnalyticsService
{
    protected string $organizationId;

    public function forOrganization(string $organizationId): self
    {
        $this->organizationId = $organizationId;

        return $this;
    }

    public function getTodaySummary(): array
    {
        $start = Carbon::today();
        $end = Carbon::tomorrow();

        $rows = DB::table('fulfillment_logs')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('created_at', [$start, $end])
            ->select('action', DB::raw('COUNT(*) as cnt'))
            ->groupBy('action')
            ->pluck('cnt', 'action');

        $get = fn (string $a) => (int) ($rows[$a] ?? 0);

        return [
            'orders_picked' => $get(FulfillmentLog::ACTION_PICK_COMPLETED),
            'items_picked' => $get(FulfillmentLog::ACTION_ITEM_PICKED),
            'pick_errors' => $get(FulfillmentLog::ACTION_PICK_SHORTED),
            'orders_packed' => $get(FulfillmentLog::ACTION_PACK_COMPLETED),
            'items_packed' => $get(FulfillmentLog::ACTION_ITEM_PACKED),
            'pack_errors' => $get(FulfillmentLog::ACTION_LABEL_VOIDED),
            'labels_purchased' => $get(FulfillmentLog::ACTION_LABEL_PURCHASED),
            'shipments_created' => $get(FulfillmentLog::ACTION_SHIPMENT_CREATED),
            'returns_processed' => $get(FulfillmentLog::ACTION_RETURN_RECEIVED),
            'orders_imported' => $get(FulfillmentLog::ACTION_ORDER_IMPORTED),
            'tracking_uploaded' => $get(FulfillmentLog::ACTION_TRACKING_UPLOADED),
            'as_of' => now()->toIso8601String(),
        ];
    }

    public function getOrdersInFlight(): array
    {
        $rows = DB::table('orders')
            ->where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->whereIn('status', [
                Order::STATUS_PENDING,
                Order::STATUS_ASSIGNED,
                Order::STATUS_PICKING,
                Order::STATUS_PICKED,
                Order::STATUS_PACKING,
                Order::STATUS_PACKED,
                Order::STATUS_ON_HOLD,
            ])
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $shippedToday = DB::table('orders')
            ->where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->whereNotNull('shipped_at')
            ->whereBetween('shipped_at', [Carbon::today(), Carbon::tomorrow()])
            ->count();

        return [
            ['status' => 'pending', 'label' => 'Pending', 'count' => (int) ($rows[Order::STATUS_PENDING] ?? 0)],
            ['status' => 'assigned', 'label' => 'Assigned', 'count' => (int) ($rows[Order::STATUS_ASSIGNED] ?? 0)],
            ['status' => 'picking', 'label' => 'Picking', 'count' => (int) ($rows[Order::STATUS_PICKING] ?? 0)],
            ['status' => 'picked', 'label' => 'Picked', 'count' => (int) ($rows[Order::STATUS_PICKED] ?? 0)],
            ['status' => 'packing', 'label' => 'Packing', 'count' => (int) ($rows[Order::STATUS_PACKING] ?? 0)],
            ['status' => 'packed', 'label' => 'Packed', 'count' => (int) ($rows[Order::STATUS_PACKED] ?? 0)],
            ['status' => 'on_hold', 'label' => 'On hold', 'count' => (int) ($rows[Order::STATUS_ON_HOLD] ?? 0)],
            ['status' => 'shipped_today', 'label' => 'Shipped today', 'count' => (int) $shippedToday],
        ];
    }

    public function getHourlyTrend(): array
    {
        $start = Carbon::today();
        $end = Carbon::tomorrow();

        $rows = DB::table('fulfillment_logs')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('action', [
                FulfillmentLog::ACTION_ITEM_PICKED,
                FulfillmentLog::ACTION_ITEM_PACKED,
                FulfillmentLog::ACTION_SHIPMENT_CREATED,
            ])
            ->select(
                DB::raw('EXTRACT(HOUR FROM created_at)::int as hour'),
                'action',
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('hour', 'action')
            ->get();

        $buckets = [];
        for ($h = 0; $h < 24; $h++) {
            $buckets[$h] = [
                'hour' => sprintf('%02d:00', $h),
                'items_picked' => 0,
                'items_packed' => 0,
                'shipments_created' => 0,
            ];
        }

        foreach ($rows as $row) {
            $h = (int) $row->hour;
            if (! isset($buckets[$h])) {
                continue;
            }
            $key = match ($row->action) {
                FulfillmentLog::ACTION_ITEM_PICKED => 'items_picked',
                FulfillmentLog::ACTION_ITEM_PACKED => 'items_packed',
                FulfillmentLog::ACTION_SHIPMENT_CREATED => 'shipments_created',
                default => null,
            };
            if ($key) {
                $buckets[$h][$key] += (int) $row->cnt;
            }
        }

        return array_values($buckets);
    }

    public function getActiveUsers(int $minutesWindow = 60): array
    {
        $since = now()->subMinutes($minutesWindow);

        return DB::table('fulfillment_logs')
            ->join('users', 'fulfillment_logs.user_id', '=', 'users.id')
            ->where('fulfillment_logs.organization_id', $this->organizationId)
            ->where('fulfillment_logs.created_at', '>=', $since)
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                DB::raw('COUNT(*) as actions'),
                DB::raw('MAX(fulfillment_logs.created_at) as last_action_at')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('actions')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'user_name' => $row->user_name,
                'actions' => (int) $row->actions,
                'last_action_at' => $row->last_action_at,
            ])
            ->toArray();
    }

    public function getPerUserToday(): array
    {
        $start = Carbon::today();
        $end = Carbon::tomorrow();

        return DB::table('fulfillment_logs')
            ->join('users', 'fulfillment_logs.user_id', '=', 'users.id')
            ->where('fulfillment_logs.organization_id', $this->organizationId)
            ->whereBetween('fulfillment_logs.created_at', [$start, $end])
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                DB::raw("SUM(CASE WHEN action = 'item_picked' THEN 1 ELSE 0 END) as items_picked"),
                DB::raw("SUM(CASE WHEN action = 'pick_completed' THEN 1 ELSE 0 END) as orders_picked"),
                DB::raw("SUM(CASE WHEN action = 'item_packed' THEN 1 ELSE 0 END) as items_packed"),
                DB::raw("SUM(CASE WHEN action = 'pack_completed' THEN 1 ELSE 0 END) as orders_packed"),
                DB::raw("SUM(CASE WHEN action = 'shipment_created' THEN 1 ELSE 0 END) as shipments_created"),
                DB::raw("SUM(CASE WHEN action = 'pick_shorted' THEN 1 ELSE 0 END) as pick_errors"),
                DB::raw("SUM(CASE WHEN action = 'label_voided' THEN 1 ELSE 0 END) as pack_errors")
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc(DB::raw("SUM(CASE WHEN action = 'item_picked' THEN 1 ELSE 0 END) + SUM(CASE WHEN action = 'item_packed' THEN 1 ELSE 0 END) + SUM(CASE WHEN action = 'shipment_created' THEN 1 ELSE 0 END)"))
            ->get()
            ->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'user_name' => $row->user_name,
                'items_picked' => (int) $row->items_picked,
                'orders_picked' => (int) $row->orders_picked,
                'items_packed' => (int) $row->items_packed,
                'orders_packed' => (int) $row->orders_packed,
                'shipments_created' => (int) $row->shipments_created,
                'pick_errors' => (int) $row->pick_errors,
                'pack_errors' => (int) $row->pack_errors,
            ])
            ->toArray();
    }

    public function getRecentActivity(int $limit = 50): array
    {
        return DB::table('fulfillment_logs')
            ->leftJoin('users', 'fulfillment_logs.user_id', '=', 'users.id')
            ->leftJoin('orders', 'fulfillment_logs.order_id', '=', 'orders.id')
            ->where('fulfillment_logs.organization_id', $this->organizationId)
            ->orderByDesc('fulfillment_logs.created_at')
            ->limit($limit)
            ->select(
                'fulfillment_logs.id',
                'fulfillment_logs.action',
                'fulfillment_logs.created_at',
                'fulfillment_logs.metadata',
                'users.id as user_id',
                'users.name as user_name',
                'orders.id as order_id',
                'orders.marketplace_order_id',
                'orders.marketplace'
            )
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'action' => $row->action,
                'action_label' => $this->actionLabel($row->action),
                'user_id' => $row->user_id ? (int) $row->user_id : null,
                'user_name' => $row->user_name ?? 'System',
                'order_id' => $row->order_id,
                'marketplace_order_id' => $row->marketplace_order_id,
                'marketplace' => $row->marketplace,
                'created_at' => $row->created_at,
            ])
            ->toArray();
    }

    protected function actionLabel(string $action): string
    {
        return match ($action) {
            FulfillmentLog::ACTION_ORDER_IMPORTED => 'Order imported',
            FulfillmentLog::ACTION_ORDER_ASSIGNED => 'Order assigned',
            FulfillmentLog::ACTION_ORDER_HELD => 'Order on hold',
            FulfillmentLog::ACTION_ORDER_RELEASED => 'Order released',
            FulfillmentLog::ACTION_ORDER_CANCELLED => 'Order cancelled',
            FulfillmentLog::ACTION_PICK_STARTED => 'Pick started',
            FulfillmentLog::ACTION_ITEM_PICKED => 'Item picked',
            FulfillmentLog::ACTION_PICK_COMPLETED => 'Pick completed',
            FulfillmentLog::ACTION_PICK_SHORTED => 'Item shorted',
            FulfillmentLog::ACTION_PACK_STARTED => 'Pack started',
            FulfillmentLog::ACTION_RATE_SHOPPED => 'Rates fetched',
            FulfillmentLog::ACTION_LABEL_PURCHASED => 'Label purchased',
            FulfillmentLog::ACTION_LABEL_VOIDED => 'Label voided',
            FulfillmentLog::ACTION_ITEM_PACKED => 'Item packed',
            FulfillmentLog::ACTION_PACK_COMPLETED => 'Pack completed',
            FulfillmentLog::ACTION_SHIPMENT_CREATED => 'Shipment created',
            FulfillmentLog::ACTION_TRACKING_UPLOADED => 'Tracking uploaded',
            FulfillmentLog::ACTION_TRACKING_UPDATED => 'Tracking updated',
            FulfillmentLog::ACTION_DELIVERY_CONFIRMED => 'Delivery confirmed',
            FulfillmentLog::ACTION_RETURN_REQUESTED => 'Return requested',
            FulfillmentLog::ACTION_RETURN_RECEIVED => 'Return received',
            FulfillmentLog::ACTION_RETURN_INSPECTED => 'Return inspected',
            FulfillmentLog::ACTION_REFUND_PROCESSED => 'Refund processed',
            default => str_replace('_', ' ', ucfirst($action)),
        };
    }
}
