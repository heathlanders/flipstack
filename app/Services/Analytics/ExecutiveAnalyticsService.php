<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Concerns\AnalyticsQueryTrait;
use Illuminate\Support\Facades\DB;

class ExecutiveAnalyticsService
{
    use AnalyticsQueryTrait;

    public function getOverview(): array
    {
        return $this->cached('executive_overview', 900, function () {
            return $this->withComparison(function (string $startDate, string $endDate) {
                $row = DB::table('order_financials')
                    ->join('orders', 'order_financials.order_id', '=', 'orders.id')
                    ->where('order_financials.organization_id', $this->organizationId)
                    ->whereBetween('orders.created_at', [$startDate, $endDate.' 23:59:59'])
                    ->whereNull('orders.deleted_at')
                    ->select([
                        DB::raw('COALESCE(SUM(order_financials.revenue), 0) as revenue'),
                        DB::raw('COUNT(*) as orders'),
                        DB::raw('COALESCE(SUM(order_financials.net_profit), 0) as net_profit'),
                        DB::raw('COALESCE(AVG(order_financials.profit_margin_pct), 0) as avg_margin'),
                        DB::raw('COALESCE(SUM(order_financials.revenue - order_financials.cost_of_goods), 0) as gross_profit'),
                        DB::raw('COALESCE(SUM(order_financials.marketplace_fees), 0) as total_fees'),
                        DB::raw('COALESCE(SUM(order_financials.shipping_label_cost), 0) as total_shipping'),
                    ])
                    ->first();

                return [
                    'revenue' => round((float) $row->revenue, 2),
                    'orders' => (int) $row->orders,
                    'net_profit' => round((float) $row->net_profit, 2),
                    'avg_margin' => round((float) $row->avg_margin, 2),
                    'gross_profit' => round((float) $row->gross_profit, 2),
                    'total_fees' => round((float) $row->total_fees, 2),
                    'total_shipping' => round((float) $row->total_shipping, 2),
                ];
            });
        });
    }

    public function getRevenueByMarketplace(): array
    {
        return $this->cached('executive_revenue_marketplace', 900, function () {
            return DB::table('order_financials')
                ->join('orders', 'order_financials.order_id', '=', 'orders.id')
                ->where('order_financials.organization_id', $this->organizationId)
                ->whereBetween('orders.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('orders.deleted_at')
                ->select([
                    'orders.marketplace',
                    DB::raw('COALESCE(SUM(order_financials.revenue), 0) as revenue'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('COALESCE(SUM(order_financials.net_profit), 0) as profit'),
                ])
                ->groupBy('orders.marketplace')
                ->orderByDesc('revenue')
                ->get()
                ->map(fn ($row) => [
                    'marketplace' => $row->marketplace ?? 'Unknown',
                    'revenue' => round((float) $row->revenue, 2),
                    'orders' => (int) $row->orders,
                    'profit' => round((float) $row->profit, 2),
                ])
                ->toArray();
        });
    }

    public function getRevenueByCategory(): array
    {
        return $this->cached('executive_revenue_category', 900, function () {
            return DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->where('orders.organization_id', $this->organizationId)
                ->whereBetween('orders.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('orders.deleted_at')
                ->select([
                    DB::raw("COALESCE(categories.name, 'Uncategorized') as category_name"),
                    DB::raw('COALESCE(SUM(order_items.total_price), 0) as revenue'),
                    DB::raw('COUNT(DISTINCT orders.id) as orders'),
                ])
                ->groupBy('categories.name')
                ->orderByDesc('revenue')
                ->get()
                ->map(fn ($row) => [
                    'category_name' => $row->category_name,
                    'revenue' => round((float) $row->revenue, 2),
                    'orders' => (int) $row->orders,
                ])
                ->toArray();
        });
    }

    public function getRevenueTrend(): array
    {
        return $this->cached('executive_revenue_trend', 900, function () {
            return DB::table('order_financials')
                ->join('orders', 'order_financials.order_id', '=', 'orders.id')
                ->where('order_financials.organization_id', $this->organizationId)
                ->whereBetween('orders.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('orders.deleted_at')
                ->select([
                    DB::raw('DATE(orders.created_at) as date'),
                    DB::raw('COALESCE(SUM(order_financials.revenue), 0) as revenue'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('COALESCE(SUM(order_financials.net_profit), 0) as net_profit'),
                ])
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'revenue' => round((float) $row->revenue, 2),
                    'orders' => (int) $row->orders,
                    'net_profit' => round((float) $row->net_profit, 2),
                ])
                ->toArray();
        });
    }

    public function getOrderVolumeTrend(): array
    {
        return $this->cached('executive_order_volume_trend', 900, function () {
            return DB::table('orders')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('deleted_at')
                ->select([
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as order_count'),
                ])
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'order_count' => (int) $row->order_count,
                ])
                ->toArray();
        });
    }

    public function getTopProducts(int $limit = 10): array
    {
        return $this->cached("executive_top_products_{$limit}", 900, function () use ($limit) {
            return DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.organization_id', $this->organizationId)
                ->whereBetween('orders.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNull('orders.deleted_at')
                ->select([
                    'products.name as product_name',
                    'products.sku',
                    DB::raw('SUM(order_items.quantity) as units_sold'),
                    DB::raw('COALESCE(SUM(order_items.total_price), 0) as revenue'),
                    DB::raw('COALESCE(SUM(order_items.quantity * products.cost_price), 0) as cogs'),
                    DB::raw('COALESCE(SUM(order_items.total_price) - SUM(order_items.quantity * products.cost_price), 0) as gross_profit'),
                ])
                ->groupBy('products.id', 'products.name', 'products.sku')
                ->orderByDesc('gross_profit')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'product_name' => $row->product_name,
                    'sku' => $row->sku,
                    'units_sold' => (int) $row->units_sold,
                    'revenue' => round((float) $row->revenue, 2),
                    'cogs' => round((float) $row->cogs, 2),
                    'gross_profit' => round((float) $row->gross_profit, 2),
                    'margin' => (float) $row->revenue > 0
                        ? round(((float) $row->gross_profit / (float) $row->revenue) * 100, 2)
                        : 0,
                ])
                ->toArray();
        });
    }
}
