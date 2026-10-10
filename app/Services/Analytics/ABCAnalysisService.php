<?php

namespace App\Services\Analytics;

use App\Models\Catalog\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ABCAnalysisService
{
    protected int $organizationId;

    protected float $aThreshold = 80; // Top 80% of value = Class A

    protected float $bThreshold = 95; // Next 15% = Class B, remaining 5% = Class C

    /**
     * Set organization context
     */
    public function forOrganization(int $organizationId): self
    {
        $this->organizationId = $organizationId;

        return $this;
    }

    /**
     * Customize classification thresholds
     */
    public function setThresholds(float $aThreshold, float $bThreshold): self
    {
        $this->aThreshold = $aThreshold;
        $this->bThreshold = $bThreshold;

        return $this;
    }

    /**
     * Perform ABC Analysis on inventory
     *
     * @param  string  $metric  'revenue', 'sales_volume', 'inventory_value'
     */
    public function analyze(string $metric = 'revenue'): array
    {
        $cacheKey = "abc_analysis:{$this->organizationId}:{$metric}";

        return Cache::tags(['analytics', "org:{$this->organizationId}"])
            ->remember($cacheKey, now()->addHours(4), function () use ($metric) {
                return $this->performAnalysis($metric);
            });
    }

    /**
     * Execute the ABC analysis calculation
     */
    protected function performAnalysis(string $metric): array
    {
        // Get products with calculated values
        $products = $this->getProductsWithValues($metric);

        if ($products->isEmpty()) {
            return [
                'summary' => $this->getEmptySummary(),
                'products' => [],
                'recommendations' => [],
            ];
        }

        $totalValue = $products->sum('metric_value');

        if ($totalValue <= 0) {
            return [
                'summary' => $this->getEmptySummary(),
                'products' => $products->toArray(),
                'recommendations' => [],
            ];
        }

        // Calculate cumulative percentages and assign classes
        $runningTotal = 0;
        $classifiedProducts = $products->map(function ($product) use ($totalValue, &$runningTotal) {
            $runningTotal += $product['metric_value'];
            $cumulativePercentage = ($runningTotal / $totalValue) * 100;

            $class = 'C';
            if ($cumulativePercentage <= $this->aThreshold) {
                $class = 'A';
            } elseif ($cumulativePercentage <= $this->bThreshold) {
                $class = 'B';
            }

            return array_merge($product, [
                'percentage_of_total' => round(($product['metric_value'] / $totalValue) * 100, 2),
                'cumulative_percentage' => round($cumulativePercentage, 2),
                'abc_class' => $class,
            ]);
        });

        // Generate summary statistics
        $summary = $this->generateSummary($classifiedProducts, $totalValue);

        // Generate recommendations
        $recommendations = $this->generateRecommendations($summary);

        return [
            'summary' => $summary,
            'products' => $classifiedProducts->toArray(),
            'recommendations' => $recommendations,
            'thresholds' => [
                'a_threshold' => $this->aThreshold,
                'b_threshold' => $this->bThreshold,
            ],
            'metric' => $metric,
            'analyzed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get products with calculated metric values
     */
    protected function getProductsWithValues(string $metric): \Illuminate\Support\Collection
    {
        $valueExpression = match ($metric) {
            'revenue' => 'selling_price * total_sales',
            'sales_volume' => 'total_sales',
            'inventory_value' => 'cost_price * stock_quantity',
            default => 'selling_price * total_sales',
        };

        return Product::where('organization_id', $this->organizationId)
            ->where('status', 'active')
            ->where('manage_stock', true)
            ->with(['category:id,name', 'brand:id,name'])
            ->selectRaw("
                id, name, sku, 
                cost_price, selling_price, 
                stock_quantity, total_sales, view_count,
                category_id, brand_id,
                ({$valueExpression}) as metric_value
            ")
            ->orderByDesc('metric_value')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name ?? 'Uncategorized',
                    'brand' => $product->brand?->name ?? 'No Brand',
                    'cost_price' => (float) $product->cost_price,
                    'selling_price' => (float) $product->selling_price,
                    'stock_quantity' => (int) $product->stock_quantity,
                    'total_sales' => (int) $product->total_sales,
                    'view_count' => (int) $product->view_count,
                    'metric_value' => (float) $product->metric_value,
                ];
            });
    }

    /**
     * Generate summary statistics by class
     */
    protected function generateSummary(\Illuminate\Support\Collection $products, float $totalValue): array
    {
        $classes = ['A', 'B', 'C'];
        $summary = [];

        foreach ($classes as $class) {
            $classProducts = $products->where('abc_class', $class);
            $classValue = $classProducts->sum('metric_value');
            $classStock = $classProducts->sum('stock_quantity');

            $summary[$class] = [
                'product_count' => $classProducts->count(),
                'percentage_of_products' => $products->count() > 0
                    ? round(($classProducts->count() / $products->count()) * 100, 2)
                    : 0,
                'total_value' => round($classValue, 2),
                'percentage_of_value' => round(($classValue / $totalValue) * 100, 2),
                'total_stock' => $classStock,
                'average_value_per_product' => $classProducts->count() > 0
                    ? round($classValue / $classProducts->count(), 2)
                    : 0,
            ];
        }

        $summary['totals'] = [
            'total_products' => $products->count(),
            'total_value' => round($totalValue, 2),
            'total_stock' => $products->sum('stock_quantity'),
        ];

        return $summary;
    }

    /**
     * Get empty summary structure
     */
    protected function getEmptySummary(): array
    {
        $emptyClass = [
            'product_count' => 0,
            'percentage_of_products' => 0,
            'total_value' => 0,
            'percentage_of_value' => 0,
            'total_stock' => 0,
            'average_value_per_product' => 0,
        ];

        return [
            'A' => $emptyClass,
            'B' => $emptyClass,
            'C' => $emptyClass,
            'totals' => [
                'total_products' => 0,
                'total_value' => 0,
                'total_stock' => 0,
            ],
        ];
    }

    /**
     * Generate actionable recommendations based on analysis
     */
    protected function generateRecommendations(array $summary): array
    {
        $recommendations = [];

        // Class A recommendations
        if ($summary['A']['product_count'] > 0) {
            $aPercentage = $summary['A']['percentage_of_products'];

            if ($aPercentage < 15) {
                $recommendations[] = [
                    'priority' => 'high',
                    'class' => 'A',
                    'type' => 'focus',
                    'message' => "Class A items represent only {$aPercentage}% of products but drive most value. Ensure these have priority stock levels and visibility.",
                    'action' => 'Review reorder points and safety stock levels for Class A items',
                ];
            }

            $recommendations[] = [
                'priority' => 'medium',
                'class' => 'A',
                'type' => 'optimization',
                'message' => 'Monitor Class A items closely - consider premium storage locations and expedited restocking procedures.',
                'action' => 'Set up automated low-stock alerts for Class A products',
            ];
        }

        // Class B recommendations
        if ($summary['B']['product_count'] > 0) {
            $recommendations[] = [
                'priority' => 'medium',
                'class' => 'B',
                'type' => 'review',
                'message' => 'Review Class B items quarterly for potential promotion to Class A or demotion to Class C.',
                'action' => 'Schedule quarterly ABC analysis review',
            ];
        }

        // Class C recommendations
        if ($summary['C']['product_count'] > 0) {
            $cPercentage = $summary['C']['percentage_of_products'];
            $cValuePercentage = $summary['C']['percentage_of_value'];

            if ($cPercentage > 50 && $cValuePercentage < 10) {
                $recommendations[] = [
                    'priority' => 'high',
                    'class' => 'C',
                    'type' => 'cleanup',
                    'message' => "Class C items make up {$cPercentage}% of products but only {$cValuePercentage}% of value. Consider discontinuing slow-moving items.",
                    'action' => 'Review Class C items for potential discontinuation or clearance sale',
                ];
            }

            $recommendations[] = [
                'priority' => 'low',
                'class' => 'C',
                'type' => 'efficiency',
                'message' => 'Minimize handling costs for Class C items - consider bulk storage and less frequent counts.',
                'action' => 'Move Class C items to secondary storage locations',
            ];
        }

        return $recommendations;
    }

    /**
     * Get ABC classification for a single product
     */
    public function classifyProduct(string $productId, string $metric = 'revenue'): ?array
    {
        $analysis = $this->analyze($metric);

        foreach ($analysis['products'] as $product) {
            if ($product['id'] === $productId) {
                return $product;
            }
        }

        return null;
    }

    /**
     * Get products by class
     */
    public function getProductsByClass(string $class, string $metric = 'revenue'): array
    {
        $analysis = $this->analyze($metric);

        return array_filter($analysis['products'], function ($product) use ($class) {
            return $product['abc_class'] === strtoupper($class);
        });
    }

    /**
     * Compare ABC analysis over different time periods
     */
    public function compareAnalysis(string $metric = 'revenue', ?Carbon $compareDate = null): array
    {
        // Current analysis
        $current = $this->analyze($metric);

        // For comparison, we'd need historical data
        // This is a simplified version - in production you'd store periodic snapshots
        return [
            'current' => $current,
            'comparison_available' => false,
            'message' => 'Historical comparison requires periodic snapshot data',
        ];
    }

    /**
     * Clear cached analysis
     */
    public function clearCache(): void
    {
        Cache::tags(['analytics', "org:{$this->organizationId}"])->flush();
    }
}
