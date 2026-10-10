<?php

namespace App\Services\Analytics;

use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class TurnoverAnalysisService
{
    protected int $organizationId;

    protected int $analysisPeriodDays = 365;

    /**
     * Set organization context
     */
    public function forOrganization(int $organizationId): self
    {
        $this->organizationId = $organizationId;

        return $this;
    }

    /**
     * Set analysis period in days
     */
    public function setPeriod(int $days): self
    {
        $this->analysisPeriodDays = max(30, min(730, $days)); // 30 days to 2 years

        return $this;
    }

    /**
     * Get comprehensive turnover analysis
     */
    public function analyze(): array
    {
        $cacheKey = "turnover_analysis:{$this->organizationId}:{$this->analysisPeriodDays}";

        return Cache::tags(['analytics', "org:{$this->organizationId}"])
            ->remember($cacheKey, now()->addHours(4), function () {
                return $this->performAnalysis();
            });
    }

    /**
     * Execute the turnover analysis
     */
    protected function performAnalysis(): array
    {
        $startDate = now()->subDays($this->analysisPeriodDays);
        $endDate = now();

        // Get sales data from movements
        $salesData = $this->getSalesData($startDate, $endDate);

        // Get products with turnover calculations
        $products = $this->getProductsWithTurnover($salesData);

        // Calculate overall metrics
        $overallMetrics = $this->calculateOverallMetrics($products, $salesData);

        // Classify products by turnover rate
        $classification = $this->classifyByTurnover($products);

        // Generate recommendations
        $recommendations = $this->generateRecommendations($classification, $overallMetrics);

        return [
            'overall_metrics' => $overallMetrics,
            'classification' => $classification,
            'products' => $products->toArray(),
            'recommendations' => $recommendations,
            'analysis_period' => [
                'days' => $this->analysisPeriodDays,
                'start_date' => $startDate->toIso8601String(),
                'end_date' => $endDate->toIso8601String(),
            ],
            'analyzed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get sales data from inventory movements
     */
    protected function getSalesData(Carbon $startDate, Carbon $endDate): \Illuminate\Support\Collection
    {
        return InventoryMovement::where('organization_id', $this->organizationId)
            ->where('type', 'sale')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                product_id,
                SUM(ABS(quantity)) as units_sold,
                COUNT(*) as sale_count
            ')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');
    }

    /**
     * Get products with calculated turnover metrics
     */
    protected function getProductsWithTurnover(\Illuminate\Support\Collection $salesData): \Illuminate\Support\Collection
    {
        $periodFraction = $this->analysisPeriodDays / 365;

        return Product::where('organization_id', $this->organizationId)
            ->where('manage_stock', true)
            ->with(['category:id,name', 'brand:id,name'])
            ->get()
            ->map(function ($product) use ($salesData, $periodFraction) {
                $sales = $salesData->get($product->id);
                $unitsSold = $sales ? (int) $sales->units_sold : 0;
                $saleCount = $sales ? (int) $sales->sale_count : 0;

                // Calculate Cost of Goods Sold (COGS) for the period
                $cogs = $unitsSold * $product->cost_price;

                // Calculate average inventory value
                // Simplified: using current stock as average (in production, use periodic averages)
                $averageInventoryValue = $product->cost_price * $product->stock_quantity;

                // Calculate inventory turnover ratio
                // Formula: COGS / Average Inventory Value
                $turnoverRatio = $averageInventoryValue > 0
                    ? $cogs / $averageInventoryValue
                    : 0;

                // Annualize if period is less than a year
                $annualizedTurnover = $turnoverRatio / $periodFraction;

                // Calculate Days Sales of Inventory (DSI)
                // Formula: (Average Inventory / COGS) * Days in Period
                $dsi = $cogs > 0
                    ? ($averageInventoryValue / $cogs) * $this->analysisPeriodDays
                    : 999; // High DSI for products with no sales

                // Calculate sell-through rate
                // Simplified: units sold / (units sold + current stock)
                $totalUnits = $unitsSold + $product->stock_quantity;
                $sellThroughRate = $totalUnits > 0
                    ? ($unitsSold / $totalUnits) * 100
                    : 0;

                // Calculate revenue and profit
                $revenue = $unitsSold * $product->selling_price;
                $profit = $revenue - $cogs;
                $profitMargin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name ?? 'Uncategorized',
                    'brand' => $product->brand?->name ?? 'No Brand',
                    'cost_price' => (float) $product->cost_price,
                    'selling_price' => (float) $product->selling_price,
                    'stock_quantity' => (int) $product->stock_quantity,
                    'inventory_value' => round($averageInventoryValue, 2),
                    'units_sold' => $unitsSold,
                    'sale_count' => $saleCount,
                    'cogs' => round($cogs, 2),
                    'revenue' => round($revenue, 2),
                    'profit' => round($profit, 2),
                    'profit_margin' => round($profitMargin, 2),
                    'turnover_ratio' => round($turnoverRatio, 2),
                    'annualized_turnover' => round($annualizedTurnover, 2),
                    'days_sales_inventory' => min(round($dsi, 1), 999),
                    'sell_through_rate' => round($sellThroughRate, 2),
                ];
            })
            ->sortByDesc('turnover_ratio')
            ->values();
    }

    /**
     * Calculate overall turnover metrics
     */
    protected function calculateOverallMetrics(\Illuminate\Support\Collection $products, \Illuminate\Support\Collection $salesData): array
    {
        $totalCogs = $products->sum('cogs');
        $totalRevenue = $products->sum('revenue');
        $totalInventoryValue = $products->sum('inventory_value');
        $totalUnitsSold = $products->sum('units_sold');
        $totalStock = $products->sum('stock_quantity');

        // Overall turnover ratio
        $overallTurnover = $totalInventoryValue > 0
            ? $totalCogs / $totalInventoryValue
            : 0;

        // Annualize
        $periodFraction = $this->analysisPeriodDays / 365;
        $annualizedTurnover = $overallTurnover / $periodFraction;

        // Overall DSI
        $overallDsi = $totalCogs > 0
            ? ($totalInventoryValue / $totalCogs) * $this->analysisPeriodDays
            : 0;

        // GMROI (Gross Margin Return on Inventory Investment)
        $grossMargin = $totalRevenue - $totalCogs;
        $gmroi = $totalInventoryValue > 0
            ? ($grossMargin / $totalInventoryValue) * 100
            : 0;

        return [
            'total_inventory_value' => round($totalInventoryValue, 2),
            'total_cogs' => round($totalCogs, 2),
            'total_revenue' => round($totalRevenue, 2),
            'gross_margin' => round($grossMargin, 2),
            'gross_margin_percentage' => $totalRevenue > 0
                ? round(($grossMargin / $totalRevenue) * 100, 2)
                : 0,
            'total_units_sold' => $totalUnitsSold,
            'total_stock_on_hand' => $totalStock,
            'overall_turnover_ratio' => round($overallTurnover, 2),
            'annualized_turnover' => round($annualizedTurnover, 2),
            'days_sales_inventory' => round($overallDsi, 1),
            'gmroi' => round($gmroi, 2),
            'products_analyzed' => $products->count(),
            'products_with_sales' => $products->where('units_sold', '>', 0)->count(),
            'products_without_sales' => $products->where('units_sold', '<=', 0)->count(),
        ];
    }

    /**
     * Classify products by turnover rate
     */
    protected function classifyByTurnover(\Illuminate\Support\Collection $products): array
    {
        // Define turnover brackets (annualized)
        $brackets = [
            'fast_moving' => ['min' => 8, 'max' => PHP_FLOAT_MAX, 'label' => 'Fast Moving (8+ turns/year)'],
            'normal' => ['min' => 4, 'max' => 8, 'label' => 'Normal (4-8 turns/year)'],
            'slow_moving' => ['min' => 1, 'max' => 4, 'label' => 'Slow Moving (1-4 turns/year)'],
            'dead_stock' => ['min' => 0, 'max' => 1, 'label' => 'Dead Stock (<1 turn/year)'],
        ];

        $classification = [];

        foreach ($brackets as $key => $bracket) {
            $categoryProducts = $products->filter(function ($product) use ($bracket) {
                return $product['annualized_turnover'] >= $bracket['min']
                    && $product['annualized_turnover'] < $bracket['max'];
            });

            $classification[$key] = [
                'label' => $bracket['label'],
                'product_count' => $categoryProducts->count(),
                'percentage' => $products->count() > 0
                    ? round(($categoryProducts->count() / $products->count()) * 100, 2)
                    : 0,
                'total_inventory_value' => round($categoryProducts->sum('inventory_value'), 2),
                'total_revenue' => round($categoryProducts->sum('revenue'), 2),
                'average_turnover' => $categoryProducts->count() > 0
                    ? round($categoryProducts->avg('annualized_turnover'), 2)
                    : 0,
                'average_dsi' => $categoryProducts->count() > 0
                    ? round($categoryProducts->avg('days_sales_inventory'), 1)
                    : 0,
            ];
        }

        return $classification;
    }

    /**
     * Generate recommendations based on turnover analysis
     */
    protected function generateRecommendations(array $classification, array $overallMetrics): array
    {
        $recommendations = [];

        // Dead stock recommendations
        if ($classification['dead_stock']['product_count'] > 0) {
            $deadStockValue = $classification['dead_stock']['total_inventory_value'];
            $deadStockPercentage = $overallMetrics['total_inventory_value'] > 0
                ? round(($deadStockValue / $overallMetrics['total_inventory_value']) * 100, 2)
                : 0;

            $recommendations[] = [
                'priority' => 'high',
                'category' => 'dead_stock',
                'title' => 'Dead Stock Action Required',
                'message' => "You have {$classification['dead_stock']['product_count']} products classified as dead stock, representing \${$deadStockValue} ({$deadStockPercentage}%) of inventory value.",
                'actions' => [
                    'Review products for clearance sale opportunities',
                    'Consider bundling with fast-moving items',
                    'Evaluate for discontinuation',
                    'Check for listing issues on sales channels',
                ],
            ];
        }

        // Slow moving recommendations
        if ($classification['slow_moving']['product_count'] > 0) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'slow_moving',
                'title' => 'Slow Moving Inventory Optimization',
                'message' => "{$classification['slow_moving']['product_count']} products are moving slower than optimal (1-4 turns per year).",
                'actions' => [
                    'Review pricing strategy for these items',
                    'Improve product listings and photos',
                    'Consider promotional campaigns',
                    'Analyze seasonality patterns',
                ],
            ];
        }

        // Overall DSI recommendations
        if ($overallMetrics['days_sales_inventory'] > 90) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'efficiency',
                'title' => 'High Days Sales Inventory',
                'message' => "Overall DSI is {$overallMetrics['days_sales_inventory']} days. Industry benchmark is typically 30-60 days for e-commerce.",
                'actions' => [
                    'Review purchasing patterns and reduce overstock',
                    'Implement just-in-time ordering where possible',
                    'Consider dropshipping for slow movers',
                    'Negotiate better terms with suppliers',
                ],
            ];
        }

        // GMROI recommendations
        if ($overallMetrics['gmroi'] < 100) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'profitability',
                'title' => 'Low Inventory Return',
                'message' => "GMROI is {$overallMetrics['gmroi']}%. A healthy GMROI should be above 200%.",
                'actions' => [
                    'Review pricing to improve margins',
                    'Focus on higher-margin products',
                    'Reduce inventory carrying costs',
                    'Negotiate better purchase prices',
                ],
            ];
        } elseif ($overallMetrics['gmroi'] >= 200) {
            $recommendations[] = [
                'priority' => 'low',
                'category' => 'opportunity',
                'title' => 'Strong Inventory Performance',
                'message' => "GMROI is {$overallMetrics['gmroi']}%, indicating healthy inventory investment returns.",
                'actions' => [
                    'Consider expanding inventory in top performers',
                    'Document successful strategies for replication',
                    'Explore additional product lines',
                ],
            ];
        }

        // Fast-moving stock level check
        if ($classification['fast_moving']['product_count'] > 0) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'fast_moving',
                'title' => 'Fast-Moving Stock Management',
                'message' => "{$classification['fast_moving']['product_count']} products turn over 8+ times per year.",
                'actions' => [
                    'Ensure adequate safety stock levels',
                    'Set up automated reorder alerts',
                    'Consider bulk purchasing for cost savings',
                    'Monitor for stockout risk',
                ],
            ];
        }

        return $recommendations;
    }

    /**
     * Get turnover report for a specific product
     */
    public function getProductTurnover(string $productId): ?array
    {
        $analysis = $this->analyze();

        foreach ($analysis['products'] as $product) {
            if ($product['id'] === $productId) {
                return $product;
            }
        }

        return null;
    }

    /**
     * Get products by turnover classification
     */
    public function getProductsByClassification(string $classification): array
    {
        $analysis = $this->analyze();

        $brackets = [
            'fast_moving' => [8, PHP_FLOAT_MAX],
            'normal' => [4, 8],
            'slow_moving' => [1, 4],
            'dead_stock' => [0, 1],
        ];

        if (! isset($brackets[$classification])) {
            return [];
        }

        [$min, $max] = $brackets[$classification];

        return array_filter($analysis['products'], function ($product) use ($min, $max) {
            return $product['annualized_turnover'] >= $min
                && $product['annualized_turnover'] < $max;
        });
    }

    /**
     * Get turnover trend over time
     */
    public function getTurnoverTrend(int $periods = 6, string $interval = 'month'): array
    {
        $results = [];
        $periodDays = match ($interval) {
            'week' => 7,
            'month' => 30,
            'quarter' => 90,
            default => 30,
        };

        for ($i = $periods - 1; $i >= 0; $i--) {
            $endDate = now()->subDays($i * $periodDays);
            $startDate = $endDate->copy()->subDays($periodDays);

            $salesData = $this->getSalesData($startDate, $endDate);
            $totalCogs = 0;
            $totalInventoryValue = 0;

            Product::where('organization_id', $this->organizationId)
                ->where('manage_stock', true)
                ->chunk(100, function ($products) use ($salesData, &$totalCogs, &$totalInventoryValue) {
                    foreach ($products as $product) {
                        $sales = $salesData->get($product->id);
                        $unitsSold = $sales ? (int) $sales->units_sold : 0;
                        $totalCogs += $unitsSold * $product->cost_price;
                        $totalInventoryValue += $product->cost_price * $product->stock_quantity;
                    }
                });

            $turnover = $totalInventoryValue > 0 ? $totalCogs / $totalInventoryValue : 0;

            $results[] = [
                'period' => $startDate->format('Y-m-d'),
                'period_label' => $startDate->format('M Y'),
                'turnover_ratio' => round($turnover, 2),
                'cogs' => round($totalCogs, 2),
                'inventory_value' => round($totalInventoryValue, 2),
            ];
        }

        return $results;
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        Cache::tags(['analytics', "org:{$this->organizationId}"])->flush();
    }
}
