<?php

namespace App\Services\Analytics;

use App\Models\Analytics\OrderFinancial;
use App\Models\Inventory\PartOutWorkOrder;
use Illuminate\Support\Facades\DB;

class ProfitLossService
{
    protected string $organizationId;

    protected ?string $startDate = null;

    protected ?string $endDate = null;

    public function forOrganization(string $organizationId): self
    {
        $this->organizationId = $organizationId;

        return $this;
    }

    public function dateRange(string $startDate, string $endDate): self
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;

        return $this;
    }

    protected function baseQuery()
    {
        $query = OrderFinancial::withoutGlobalScopes()
            ->where('order_financials.organization_id', $this->organizationId);

        if ($this->startDate && $this->endDate) {
            $query->whereHas('order', function ($q) {
                $q->whereBetween('orders.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                    ->whereNull('orders.deleted_at');
            });
        }

        return $query;
    }

    public function getSummary(): array
    {
        $query = $this->baseQuery();

        $totals = $query->select([
            DB::raw('COUNT(*) as order_count'),
            DB::raw('COALESCE(SUM(revenue), 0) as total_revenue'),
            DB::raw('COALESCE(SUM(cost_of_goods), 0) as total_cogs'),
            DB::raw('COALESCE(SUM(marketplace_fees), 0) as total_fees'),
            DB::raw('COALESCE(SUM(shipping_label_cost), 0) as total_shipping_cost'),
            DB::raw('COALESCE(SUM(shipping_charged), 0) as total_shipping_charged'),
            DB::raw('COALESCE(SUM(net_profit), 0) as total_net_profit'),
            DB::raw('COALESCE(AVG(profit_margin_pct), 0) as avg_margin'),
        ])->first();

        return [
            'order_count' => (int) $totals->order_count,
            'total_revenue' => round((float) $totals->total_revenue, 2),
            'total_cogs' => round((float) $totals->total_cogs, 2),
            'total_fees' => round((float) $totals->total_fees, 2),
            'total_shipping_cost' => round((float) $totals->total_shipping_cost, 2),
            'total_shipping_charged' => round((float) $totals->total_shipping_charged, 2),
            'total_net_profit' => round((float) $totals->total_net_profit, 2),
            'avg_margin' => round((float) $totals->avg_margin, 2),
            'gross_profit' => round((float) $totals->total_revenue - (float) $totals->total_cogs, 2),
        ];
    }

    public function getByMarketplace(): array
    {
        return $this->baseQuery()
            ->join('orders', 'order_financials.order_id', '=', 'orders.id')
            ->select([
                'orders.marketplace',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('COALESCE(SUM(order_financials.revenue), 0) as total_revenue'),
                DB::raw('COALESCE(SUM(order_financials.cost_of_goods), 0) as total_cogs'),
                DB::raw('COALESCE(SUM(order_financials.marketplace_fees), 0) as total_fees'),
                DB::raw('COALESCE(SUM(order_financials.net_profit), 0) as total_net_profit'),
                DB::raw('COALESCE(AVG(order_financials.profit_margin_pct), 0) as avg_margin'),
            ])
            ->groupBy('orders.marketplace')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn ($row) => [
                'marketplace' => $row->marketplace ?? 'Unknown',
                'order_count' => (int) $row->order_count,
                'total_revenue' => round((float) $row->total_revenue, 2),
                'total_cogs' => round((float) $row->total_cogs, 2),
                'total_fees' => round((float) $row->total_fees, 2),
                'total_net_profit' => round((float) $row->total_net_profit, 2),
                'avg_margin' => round((float) $row->avg_margin, 2),
            ])
            ->toArray();
    }

    public function getDailyTrend(): array
    {
        return $this->baseQuery()
            ->join('orders', 'order_financials.order_id', '=', 'orders.id')
            ->select([
                DB::raw('DATE(orders.created_at) as date'),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('COALESCE(SUM(order_financials.revenue), 0) as revenue'),
                DB::raw('COALESCE(SUM(order_financials.net_profit), 0) as net_profit'),
            ])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'order_count' => (int) $row->order_count,
                'revenue' => round((float) $row->revenue, 2),
                'net_profit' => round((float) $row->net_profit, 2),
            ])
            ->toArray();
    }

    public function getTopProducts(int $limit = 10): array
    {
        return $this->baseQuery()
            ->join('orders', 'order_financials.order_id', '=', 'orders.id')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                'products.sku',
                DB::raw('SUM(order_items.quantity) as units_sold'),
                DB::raw('SUM(order_items.total_price) as total_revenue'),
                DB::raw('SUM(order_items.quantity * products.cost_price) as total_cogs'),
                DB::raw('SUM(order_items.total_price) - SUM(order_items.quantity * products.cost_price) as gross_profit'),
            ])
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('gross_profit')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'sku' => $row->sku,
                'units_sold' => (int) $row->units_sold,
                'total_revenue' => round((float) $row->total_revenue, 2),
                'total_cogs' => round((float) $row->total_cogs, 2),
                'gross_profit' => round((float) $row->gross_profit, 2),
                'margin' => (float) $row->total_revenue > 0 ? round(((float) $row->gross_profit / (float) $row->total_revenue) * 100, 2) : 0,
            ])
            ->toArray();
    }

    /**
     * Part-Out ROI: compare parent product cost vs component product revenue.
     */
    public function getPartOutROI(): array
    {
        $query = PartOutWorkOrder::withoutGlobalScopes()
            ->where('organization_id', $this->organizationId)
            ->where('status', PartOutWorkOrder::STATUS_COMPLETED);

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('completed_at', [$this->startDate, $this->endDate.' 23:59:59']);
        }

        $workOrders = $query->with(['items', 'sourceProduct:id,name,sku'])->get();

        $totalParentCost = 0;
        $totalComponentRevenue = 0;
        $totalComponentsSold = 0;
        $totalWorkOrders = $workOrders->count();
        $totalComponentsCreated = 0;

        foreach ($workOrders as $wo) {
            $totalParentCost += (float) $wo->source_total_cost;
            $totalComponentsCreated += $wo->items->count();

            foreach ($wo->items as $item) {
                if ($item->component_product_id) {
                    $sales = OrderFinancial::withoutGlobalScopes()
                        ->where('organization_id', $this->organizationId)
                        ->whereHas('order.items', fn ($q) => $q->where('product_id', $item->component_product_id))
                        ->sum('revenue');

                    $totalComponentRevenue += (float) $sales;

                    $soldCount = DB::table('order_items')
                        ->where('product_id', $item->component_product_id)
                        ->sum('quantity');
                    $totalComponentsSold += (int) $soldCount;
                }
            }
        }

        $totalProfit = $totalComponentRevenue - $totalParentCost;
        $roi = $totalParentCost > 0 ? round(($totalProfit / $totalParentCost) * 100, 2) : 0;

        return [
            'total_work_orders' => $totalWorkOrders,
            'total_parent_cost' => round($totalParentCost, 2),
            'total_component_revenue' => round($totalComponentRevenue, 2),
            'total_profit' => round($totalProfit, 2),
            'roi_percent' => $roi,
            'total_components_created' => $totalComponentsCreated,
            'total_components_sold' => $totalComponentsSold,
        ];
    }
}
