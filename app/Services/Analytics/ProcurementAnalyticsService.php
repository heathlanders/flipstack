<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Concerns\AnalyticsQueryTrait;
use Illuminate\Support\Facades\DB;

class ProcurementAnalyticsService
{
    use AnalyticsQueryTrait;

    public function getOverview(): array
    {
        return $this->cached('procurement_overview', 900, function () {
            return $this->withComparison(function (string $startDate, string $endDate) {
                $row = DB::table('purchase_orders')
                    ->where('organization_id', $this->organizationId)
                    ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
                    ->whereNull('deleted_at')
                    ->select([
                        DB::raw('COUNT(*) as total_pos'),
                        DB::raw('COALESCE(AVG(EXTRACT(EPOCH FROM (received_at - order_date::timestamp)) / 86400) FILTER (WHERE received_at IS NOT NULL AND order_date IS NOT NULL), 0) as avg_lead_time_days'),
                        DB::raw('COUNT(*) FILTER (WHERE received_at IS NOT NULL AND expected_delivery_date IS NOT NULL AND received_at::date <= expected_delivery_date) as on_time_count'),
                        DB::raw('COUNT(*) FILTER (WHERE received_at IS NOT NULL AND expected_delivery_date IS NOT NULL) as with_expected_count'),
                        DB::raw('COALESCE(SUM(total_amount), 0) as total_spend'),
                    ])
                    ->first();

                $withExpected = (int) $row->with_expected_count;
                $onTimeRate = $withExpected > 0
                    ? round(((int) $row->on_time_count / $withExpected) * 100, 2)
                    : 0;

                // Receiving accuracy
                $accuracy = DB::table('purchase_order_items')
                    ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
                    ->where('purchase_orders.organization_id', $this->organizationId)
                    ->whereBetween('purchase_orders.created_at', [$startDate, $endDate.' 23:59:59'])
                    ->whereNull('purchase_orders.deleted_at')
                    ->where('purchase_order_items.quantity_ordered', '>', 0)
                    ->select([
                        DB::raw('COUNT(*) as total_items'),
                        DB::raw('COUNT(*) FILTER (WHERE purchase_order_items.quantity_received = purchase_order_items.quantity_ordered) as exact_match'),
                    ])
                    ->first();

                $totalItems = (int) $accuracy->total_items;
                $receivingAccuracy = $totalItems > 0
                    ? round(((int) $accuracy->exact_match / $totalItems) * 100, 2)
                    : 0;

                return [
                    'total_pos' => (int) $row->total_pos,
                    'avg_lead_time_days' => round((float) $row->avg_lead_time_days, 2),
                    'on_time_rate' => $onTimeRate,
                    'total_spend' => round((float) $row->total_spend, 2),
                    'receiving_accuracy' => $receivingAccuracy,
                ];
            });
        });
    }

    public function getSupplierLeadTime(): array
    {
        return $this->cached('procurement_supplier_lead_time', 900, function () {
            return DB::table('purchase_orders')
                ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
                ->where('purchase_orders.organization_id', $this->organizationId)
                ->whereBetween('purchase_orders.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('purchase_orders.deleted_at')
                ->whereNotNull('purchase_orders.received_at')
                ->whereNotNull('purchase_orders.order_date')
                ->select([
                    'suppliers.id as supplier_id',
                    'suppliers.name as supplier_name',
                    DB::raw('COALESCE(AVG(EXTRACT(EPOCH FROM (purchase_orders.received_at - purchase_orders.order_date::timestamp)) / 86400), 0) as avg_days'),
                    DB::raw('COUNT(*) as po_count'),
                ])
                ->groupBy('suppliers.id', 'suppliers.name')
                ->orderBy('avg_days')
                ->get()
                ->map(fn ($row) => [
                    'supplier_id' => $row->supplier_id,
                    'supplier_name' => $row->supplier_name,
                    'avg_days' => round((float) $row->avg_days, 2),
                    'po_count' => (int) $row->po_count,
                ])
                ->toArray();
        });
    }

    public function getSupplierOnTimeRate(): array
    {
        return $this->cached('procurement_supplier_on_time', 900, function () {
            return DB::table('purchase_orders')
                ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
                ->where('purchase_orders.organization_id', $this->organizationId)
                ->whereBetween('purchase_orders.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('purchase_orders.deleted_at')
                ->whereNotNull('purchase_orders.received_at')
                ->whereNotNull('purchase_orders.expected_delivery_date')
                ->select([
                    'suppliers.id as supplier_id',
                    'suppliers.name as supplier_name',
                    DB::raw('COUNT(*) as total_pos'),
                    DB::raw('COUNT(*) FILTER (WHERE purchase_orders.received_at::date <= purchase_orders.expected_delivery_date) as on_time_count'),
                ])
                ->groupBy('suppliers.id', 'suppliers.name')
                ->orderByDesc('total_pos')
                ->get()
                ->map(fn ($row) => [
                    'supplier_id' => $row->supplier_id,
                    'supplier_name' => $row->supplier_name,
                    'total_pos' => (int) $row->total_pos,
                    'on_time_count' => (int) $row->on_time_count,
                    'rate' => (int) $row->total_pos > 0
                        ? round(((int) $row->on_time_count / (int) $row->total_pos) * 100, 2)
                        : 0,
                ])
                ->toArray();
        });
    }

    public function getReceivingAccuracy(): array
    {
        return $this->cached('procurement_receiving_accuracy', 900, function () {
            $row = DB::table('purchase_order_items')
                ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
                ->where('purchase_orders.organization_id', $this->organizationId)
                ->whereBetween('purchase_orders.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('purchase_orders.deleted_at')
                ->where('purchase_order_items.quantity_ordered', '>', 0)
                ->select([
                    DB::raw('COUNT(*) as total_items'),
                    DB::raw('COUNT(*) FILTER (WHERE purchase_order_items.quantity_received = purchase_order_items.quantity_ordered) as exact_match'),
                    DB::raw('COUNT(*) FILTER (WHERE purchase_order_items.quantity_received < purchase_order_items.quantity_ordered) as under_received'),
                    DB::raw('COUNT(*) FILTER (WHERE purchase_order_items.quantity_received > purchase_order_items.quantity_ordered) as over_received'),
                ])
                ->first();

            $total = (int) $row->total_items;

            return [
                'total_items' => $total,
                'exact_match' => (int) $row->exact_match,
                'under_received' => (int) $row->under_received,
                'over_received' => (int) $row->over_received,
                'accuracy_pct' => $total > 0 ? round(((int) $row->exact_match / $total) * 100, 2) : 0,
            ];
        });
    }

    public function getCostTrends(): array
    {
        return $this->cached('procurement_cost_trends', 900, function () {
            return DB::table('purchase_order_items')
                ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
                ->where('purchase_orders.organization_id', $this->organizationId)
                ->whereBetween('purchase_orders.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('purchase_orders.deleted_at')
                ->where('purchase_order_items.unit_cost', '>', 0)
                ->select([
                    DB::raw("TO_CHAR(purchase_orders.created_at, 'YYYY-MM') as month"),
                    DB::raw('COALESCE(AVG(purchase_order_items.unit_cost), 0) as avg_unit_cost'),
                    DB::raw('COALESCE(SUM(purchase_order_items.total_cost), 0) as total_cost'),
                ])
                ->groupBy(DB::raw("TO_CHAR(purchase_orders.created_at, 'YYYY-MM')"))
                ->orderBy('month')
                ->get()
                ->map(fn ($row) => [
                    'month' => $row->month,
                    'avg_unit_cost' => round((float) $row->avg_unit_cost, 2),
                    'total_cost' => round((float) $row->total_cost, 2),
                ])
                ->toArray();
        });
    }

    public function getSpendBySupplier(): array
    {
        return $this->cached('procurement_spend_by_supplier', 900, function () {
            return DB::table('purchase_orders')
                ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
                ->where('purchase_orders.organization_id', $this->organizationId)
                ->whereBetween('purchase_orders.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('purchase_orders.deleted_at')
                ->select([
                    'suppliers.id as supplier_id',
                    'suppliers.name as supplier_name',
                    DB::raw('COALESCE(SUM(purchase_orders.total_amount), 0) as total_spend'),
                    DB::raw('COUNT(*) as po_count'),
                ])
                ->groupBy('suppliers.id', 'suppliers.name')
                ->orderByDesc('total_spend')
                ->get()
                ->map(fn ($row) => [
                    'supplier_id' => $row->supplier_id,
                    'supplier_name' => $row->supplier_name,
                    'total_spend' => round((float) $row->total_spend, 2),
                    'po_count' => (int) $row->po_count,
                ])
                ->toArray();
        });
    }

    public function getSupplierRanking(): array
    {
        return $this->cached('procurement_supplier_ranking', 900, function () {
            $leadTimes = collect($this->getSupplierLeadTime())->keyBy('supplier_id');
            $onTimeRates = collect($this->getSupplierOnTimeRate())->keyBy('supplier_id');
            $spendData = collect($this->getSpendBySupplier())->keyBy('supplier_id');

            $supplierIds = $leadTimes->keys()
                ->merge($onTimeRates->keys())
                ->merge($spendData->keys())
                ->unique();

            $rankings = $supplierIds->map(function ($supplierId) use ($leadTimes, $onTimeRates, $spendData) {
                $lt = $leadTimes->get($supplierId);
                $ot = $onTimeRates->get($supplierId);
                $sp = $spendData->get($supplierId);

                $leadTimeScore = 100;
                if ($lt) {
                    $days = $lt['avg_days'];
                    // 3 days -> 100, 14+ days -> 0
                    $leadTimeScore = max(0, min(100, 100 - (($days - 3) / 11) * 100));
                }

                $onTimeScore = $ot ? $ot['rate'] : 50;
                $costScore = 50; // Neutral if no data

                // Composite: lead_time 30%, on_time 40%, cost 30%
                $composite = ($leadTimeScore * 0.3) + ($onTimeScore * 0.4) + ($costScore * 0.3);

                return [
                    'supplier_id' => $supplierId,
                    'supplier_name' => $lt['supplier_name'] ?? $ot['supplier_name'] ?? $sp['supplier_name'] ?? 'Unknown',
                    'lead_time_score' => round($leadTimeScore, 2),
                    'on_time_score' => round($onTimeScore, 2),
                    'cost_score' => round($costScore, 2),
                    'composite_score' => round($composite, 2),
                ];
            })
                ->sortByDesc('composite_score')
                ->values()
                ->toArray();

            return $rankings;
        });
    }
}
