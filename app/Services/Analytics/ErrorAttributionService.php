<?php

namespace App\Services\Analytics;

use App\Models\Fulfillment\ReturnRequest;
use App\Services\Analytics\Concerns\AnalyticsQueryTrait;
use Illuminate\Support\Facades\DB;

/**
 * Per-user error attribution analytics for KPIs #10, #11, #12.
 *
 * Reads directly from orders/order_items/products/return_requests since
 * the per-user error counts in workforce_performance_daily only cover
 * pick/pack errors, not downstream returns/cancellations/refunds.
 */
class ErrorAttributionService
{
    use AnalyticsQueryTrait;

    /**
     * Returns attributed to each packer (KPI #10). Packed orders = orders
     * the user packed in the period; returns = those orders that ended up
     * with a return_request (any status).
     */
    public function getReturnRateByPacker(): array
    {
        return $this->cached('attr_returns_by_packer', 900, function () {
            return DB::table('orders')
                ->join('users', 'orders.assigned_packer_id', '=', 'users.id')
                ->leftJoin('return_requests', 'return_requests.order_id', '=', 'orders.id')
                ->where('orders.organization_id', $this->organizationId)
                ->whereBetween('orders.created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
                ->whereNotNull('orders.assigned_packer_id')
                ->whereNull('orders.deleted_at')
                ->select([
                    'users.id as user_id',
                    'users.name as user_name',
                    DB::raw('COUNT(DISTINCT orders.id) as orders_packed'),
                    DB::raw('COUNT(DISTINCT return_requests.id) as returns_filed'),
                    DB::raw("COUNT(DISTINCT CASE WHEN return_requests.status = '".ReturnRequest::STATUS_REFUNDED."' THEN return_requests.id END) as returns_refunded"),
                ])
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('returns_filed')
                ->get()
                ->map(function ($row) {
                    $packed = (int) $row->orders_packed;
                    $returns = (int) $row->returns_filed;

                    return [
                        'user_id' => (int) $row->user_id,
                        'user_name' => $row->user_name,
                        'orders_packed' => $packed,
                        'returns_filed' => $returns,
                        'returns_refunded' => (int) $row->returns_refunded,
                        'return_rate' => $packed > 0 ? round(($returns / $packed) * 100, 2) : 0,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Cancellation rate per handler (KPI #11). "Handler" splits by role:
     * picker vs packer. Returns one row per user with both sides.
     */
    public function getCancellationRateByHandler(): array
    {
        return $this->cached('attr_cancellation_by_handler', 900, function () {
            $base = DB::table('orders')
                ->where('orders.organization_id', $this->organizationId)
                ->whereBetween('orders.created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
                ->whereNull('orders.deleted_at');

            $pickerStats = (clone $base)
                ->join('users', 'orders.assigned_picker_id', '=', 'users.id')
                ->whereNotNull('orders.assigned_picker_id')
                ->select([
                    'users.id as user_id',
                    'users.name as user_name',
                    DB::raw('COUNT(*) as handled'),
                    DB::raw("COUNT(*) FILTER (WHERE orders.status = 'cancelled') as cancelled"),
                ])
                ->groupBy('users.id', 'users.name')
                ->get()
                ->keyBy('user_id');

            $packerStats = (clone $base)
                ->join('users', 'orders.assigned_packer_id', '=', 'users.id')
                ->whereNotNull('orders.assigned_packer_id')
                ->select([
                    'users.id as user_id',
                    'users.name as user_name',
                    DB::raw('COUNT(*) as handled'),
                    DB::raw("COUNT(*) FILTER (WHERE orders.status = 'cancelled') as cancelled"),
                ])
                ->groupBy('users.id', 'users.name')
                ->get()
                ->keyBy('user_id');

            $allUserIds = $pickerStats->keys()->merge($packerStats->keys())->unique();
            $rows = [];

            foreach ($allUserIds as $userId) {
                $picker = $pickerStats->get($userId);
                $packer = $packerStats->get($userId);
                $name = $picker?->user_name ?? $packer?->user_name;

                $pickerHandled = (int) ($picker->handled ?? 0);
                $pickerCancelled = (int) ($picker->cancelled ?? 0);
                $packerHandled = (int) ($packer->handled ?? 0);
                $packerCancelled = (int) ($packer->cancelled ?? 0);

                $rows[] = [
                    'user_id' => (int) $userId,
                    'user_name' => $name,
                    'picker_handled' => $pickerHandled,
                    'picker_cancelled' => $pickerCancelled,
                    'picker_cancel_rate' => $pickerHandled > 0 ? round(($pickerCancelled / $pickerHandled) * 100, 2) : 0,
                    'packer_handled' => $packerHandled,
                    'packer_cancelled' => $packerCancelled,
                    'packer_cancel_rate' => $packerHandled > 0 ? round(($packerCancelled / $packerHandled) * 100, 2) : 0,
                    'total_cancelled' => $pickerCancelled + $packerCancelled,
                ];
            }

            usort($rows, fn ($a, $b) => $b['total_cancelled'] <=> $a['total_cancelled']);

            return $rows;
        });
    }

    /**
     * Refund rate per lister (KPI #12). Lister attribution = products.created_by.
     * Sold items = order_items shipped/delivered for products created by user X
     * in the period; refunded items = those with a refunded return_request.
     */
    public function getRefundRateByLister(): array
    {
        return $this->cached('attr_refunds_by_lister', 900, function () {
            return DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('users', 'products.created_by', '=', 'users.id')
                ->leftJoin('return_requests', function ($join) {
                    $join->on('return_requests.order_item_id', '=', 'order_items.id')
                        ->where('return_requests.status', '=', ReturnRequest::STATUS_REFUNDED);
                })
                ->where('orders.organization_id', $this->organizationId)
                ->whereBetween('orders.created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
                ->whereNull('orders.deleted_at')
                ->whereNotNull('products.created_by')
                ->select([
                    'users.id as user_id',
                    'users.name as user_name',
                    DB::raw('COUNT(DISTINCT order_items.id) as items_sold'),
                    DB::raw('COUNT(DISTINCT return_requests.id) as items_refunded'),
                    DB::raw('COALESCE(SUM(return_requests.refund_amount), 0) as refund_total'),
                ])
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('items_refunded')
                ->get()
                ->map(function ($row) {
                    $sold = (int) $row->items_sold;
                    $refunded = (int) $row->items_refunded;

                    return [
                        'user_id' => (int) $row->user_id,
                        'user_name' => $row->user_name,
                        'items_sold' => $sold,
                        'items_refunded' => $refunded,
                        'refund_total' => round((float) $row->refund_total, 2),
                        'refund_rate' => $sold > 0 ? round(($refunded / $sold) * 100, 2) : 0,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Org-wide totals for the attribution dashboard header.
     */
    public function getOverview(): array
    {
        return $this->cached('attr_overview', 900, function () {
            $orderStats = DB::table('orders')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
                ->whereNull('deleted_at')
                ->select([
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw("COUNT(*) FILTER (WHERE status = 'cancelled') as cancelled_orders"),
                ])
                ->first();

            $returnStats = DB::table('return_requests')
                ->join('orders', 'return_requests.order_id', '=', 'orders.id')
                ->where('orders.organization_id', $this->organizationId)
                ->whereBetween('return_requests.created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
                ->select([
                    DB::raw('COUNT(*) as total_returns'),
                    DB::raw("COUNT(*) FILTER (WHERE return_requests.status = '".ReturnRequest::STATUS_REFUNDED."') as refunded_count"),
                    DB::raw('COALESCE(SUM(return_requests.refund_amount), 0) as refund_total'),
                ])
                ->first();

            $totalOrders = (int) ($orderStats->total_orders ?? 0);
            $cancelled = (int) ($orderStats->cancelled_orders ?? 0);
            $returns = (int) ($returnStats->total_returns ?? 0);

            return [
                'total_orders' => $totalOrders,
                'cancelled_orders' => $cancelled,
                'cancel_rate' => $totalOrders > 0 ? round(($cancelled / $totalOrders) * 100, 2) : 0,
                'total_returns' => $returns,
                'return_rate' => $totalOrders > 0 ? round(($returns / $totalOrders) * 100, 2) : 0,
                'refunded_count' => (int) ($returnStats->refunded_count ?? 0),
                'refund_total' => round((float) ($returnStats->refund_total ?? 0), 2),
            ];
        });
    }
}
