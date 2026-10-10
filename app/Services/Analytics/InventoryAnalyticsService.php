<?php

namespace App\Services\Analytics;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class InventoryAnalyticsService
{
    protected int $organizationId;

    protected ?Carbon $startDate;

    protected ?Carbon $endDate;

    /**
     * Set the organization context for all queries
     */
    public function forOrganization(int $organizationId): self
    {
        $this->organizationId = $organizationId;

        return $this;
    }

    /**
     * Set date range filter
     */
    public function dateRange(?Carbon $startDate, ?Carbon $endDate): self
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;

        return $this;
    }

    /**
     * Get comprehensive dashboard KPIs
     */
    public function getDashboardKPIs(): array
    {
        $cacheKey = "analytics:dashboard:{$this->organizationId}:".
            ($this->startDate?->format('Y-m-d') ?? 'all').':'.
            ($this->endDate?->format('Y-m-d') ?? 'all');

        $build = fn () => [
            'inventory_value' => $this->getTotalInventoryValue(),
            'total_skus' => $this->getTotalSKUs(),
            'active_skus' => $this->getActiveSKUs(),
            'low_stock_count' => $this->getLowStockCount(),
            'out_of_stock_count' => $this->getOutOfStockCount(),
            'stock_health' => $this->getStockHealthMetrics(),
            'movement_summary' => $this->getMovementSummary(),
            'top_performers' => $this->getTopPerformers(10),
            'recent_movements' => $this->getRecentMovements(10),
            'category_distribution' => $this->getCategoryDistribution(),
            'brand_distribution' => $this->getBrandDistribution(),
            'condition_distribution' => $this->getConditionDistribution(),
            'aging_analysis' => $this->getAgingAnalysis(),
        ];

        return $this->cacheRemember($cacheKey, now()->addMinutes(15), $build);
    }

    /**
     * Tag-aware cache wrapper that gracefully degrades when the store
     * (database/file) doesn't support tags.
     */
    protected function cacheRemember(string $key, $ttl, callable $callback)
    {
        $store = Cache::store();

        if (method_exists($store->getStore(), 'tags')) {
            try {
                return $store->tags(['analytics', "org:{$this->organizationId}"])
                    ->remember($key, $ttl, $callback);
            } catch (\BadMethodCallException) {
                // fall through
            }
        }

        return $store->remember($key, $ttl, $callback);
    }

    /**
     * Calculate total inventory value at cost
     */
    public function getTotalInventoryValue(): array
    {
        $result = Product::where('organization_id', $this->organizationId)
            ->where('manage_stock', true)
            ->selectRaw('
                SUM(cost_price * stock_quantity) as total_cost_value,
                SUM(selling_price * stock_quantity) as total_retail_value,
                SUM(stock_quantity) as total_units
            ')
            ->first();

        $costValue = (float) ($result->total_cost_value ?? 0);
        $retailValue = (float) ($result->total_retail_value ?? 0);
        $totalUnits = (int) ($result->total_units ?? 0);

        // Calculate previous period for comparison
        $previousPeriod = $this->getPreviousPeriodValue();

        return [
            'cost_value' => round($costValue, 2),
            'retail_value' => round($retailValue, 2),
            'potential_profit' => round($retailValue - $costValue, 2),
            'profit_margin' => $retailValue > 0 ? round((($retailValue - $costValue) / $retailValue) * 100, 2) : 0,
            'total_units' => $totalUnits,
            'change_percentage' => $previousPeriod > 0
                ? round((($costValue - $previousPeriod) / $previousPeriod) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get previous period inventory value for comparison
     */
    protected function getPreviousPeriodValue(): float
    {
        if (! $this->startDate || ! $this->endDate) {
            // Compare to 30 days ago
            $compareDate = now()->subDays(30);
        } else {
            $periodLength = $this->startDate->diffInDays($this->endDate);
            $compareDate = $this->startDate->copy()->subDays($periodLength);
        }

        // For simplicity, we return current value reduced by typical growth rate
        // In production, you'd track historical snapshots
        return (float) Product::where('organization_id', $this->organizationId)
            ->where('manage_stock', true)
            ->where('created_at', '<=', $compareDate)
            ->selectRaw('SUM(cost_price * stock_quantity) as value')
            ->value('value') ?? 0;
    }

    /**
     * Get total SKU count
     */
    public function getTotalSKUs(): int
    {
        return Product::where('organization_id', $this->organizationId)->count();
    }

    /**
     * Get active SKU count
     */
    public function getActiveSKUs(): int
    {
        return Product::where('organization_id', $this->organizationId)
            ->where('status', 'active')
            ->count();
    }

    /**
     * Get low stock product count
     */
    public function getLowStockCount(): int
    {
        return Product::where('organization_id', $this->organizationId)
            ->where('manage_stock', true)
            ->whereRaw('stock_quantity <= low_stock_threshold')
            ->where('stock_quantity', '>', 0)
            ->count();
    }

    /**
     * Get out of stock product count
     */
    public function getOutOfStockCount(): int
    {
        return Product::where('organization_id', $this->organizationId)
            ->where('manage_stock', true)
            ->where('stock_quantity', '<=', 0)
            ->count();
    }

    /**
     * Get stock health metrics
     */
    public function getStockHealthMetrics(): array
    {
        $total = Product::where('organization_id', $this->organizationId)
            ->where('manage_stock', true)
            ->count();

        if ($total === 0) {
            return [
                'healthy' => ['count' => 0, 'percentage' => 0],
                'low' => ['count' => 0, 'percentage' => 0],
                'critical' => ['count' => 0, 'percentage' => 0],
                'out_of_stock' => ['count' => 0, 'percentage' => 0],
                'overstocked' => ['count' => 0, 'percentage' => 0],
            ];
        }

        $healthy = Product::where('organization_id', $this->organizationId)
            ->where('manage_stock', true)
            ->whereRaw('stock_quantity > low_stock_threshold * 2')
            ->whereRaw('stock_quantity <= low_stock_threshold * 10')
            ->count();

        $low = Product::where('organization_id', $this->organizationId)
            ->where('manage_stock', true)
            ->whereRaw('stock_quantity > low_stock_threshold')
            ->whereRaw('stock_quantity <= low_stock_threshold * 2')
            ->count();

        $critical = Product::where('organization_id', $this->organizationId)
            ->where('manage_stock', true)
            ->whereRaw('stock_quantity <= low_stock_threshold')
            ->where('stock_quantity', '>', 0)
            ->count();

        $outOfStock = Product::where('organization_id', $this->organizationId)
            ->where('manage_stock', true)
            ->where('stock_quantity', '<=', 0)
            ->count();

        $overstocked = Product::where('organization_id', $this->organizationId)
            ->where('manage_stock', true)
            ->whereRaw('stock_quantity > low_stock_threshold * 10')
            ->count();

        return [
            'healthy' => [
                'count' => $healthy,
                'percentage' => round(($healthy / $total) * 100, 1),
            ],
            'low' => [
                'count' => $low,
                'percentage' => round(($low / $total) * 100, 1),
            ],
            'critical' => [
                'count' => $critical,
                'percentage' => round(($critical / $total) * 100, 1),
            ],
            'out_of_stock' => [
                'count' => $outOfStock,
                'percentage' => round(($outOfStock / $total) * 100, 1),
            ],
            'overstocked' => [
                'count' => $overstocked,
                'percentage' => round(($overstocked / $total) * 100, 1),
            ],
        ];
    }

    /**
     * Get movement summary by type
     */
    public function getMovementSummary(): array
    {
        $query = InventoryMovement::where('organization_id', $this->organizationId);

        if ($this->startDate) {
            $query->where('created_at', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->where('created_at', '<=', $this->endDate);
        }

        $movements = $query->selectRaw('
                type,
                COUNT(*) as count,
                SUM(ABS(quantity)) as total_quantity
            ')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        // Movement types per actual schema: purchase, sale, adjustment, transfer_in, transfer_out, return, damaged, lost, found
        $types = ['purchase', 'sale', 'adjustment', 'transfer_in', 'transfer_out', 'return', 'damaged', 'lost', 'found'];
        $result = [];

        foreach ($types as $type) {
            $data = $movements->get($type);
            $result[$type] = [
                'count' => (int) ($data->count ?? 0),
                'quantity' => (int) ($data->total_quantity ?? 0),
            ];
        }

        // Add combined transfer total (in + out)
        $result['transfer'] = [
            'count' => $result['transfer_in']['count'] + $result['transfer_out']['count'],
            'quantity' => $result['transfer_in']['quantity'] + $result['transfer_out']['quantity'],
        ];

        // Add totals
        $result['total'] = [
            'count' => $movements->sum('count'),
            'quantity' => $movements->sum('total_quantity'),
        ];

        return $result;
    }

    /**
     * Get top performing products by sales
     */
    public function getTopPerformers(int $limit = 10, string $metric = 'total_sales'): array
    {
        $validMetrics = ['total_sales', 'view_count', 'stock_quantity'];
        $orderBy = in_array($metric, $validMetrics) ? $metric : 'total_sales';

        return Product::where('organization_id', $this->organizationId)
            ->where('status', 'active')
            ->with(['category:id,name', 'brand:id,name'])
            ->orderByDesc($orderBy)
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                $revenue = $product->selling_price * $product->total_sales;
                $cost = $product->cost_price * $product->total_sales;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name ?? 'Uncategorized',
                    'brand' => $product->brand?->name ?? 'No Brand',
                    'total_sales' => $product->total_sales,
                    'revenue' => round($revenue, 2),
                    'profit' => round($revenue - $cost, 2),
                    'stock_quantity' => $product->stock_quantity,
                    'view_count' => $product->view_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get recent inventory movements
     */
    public function getRecentMovements(int $limit = 10): array
    {
        return InventoryMovement::where('organization_id', $this->organizationId)
            ->with(['product:id,name,sku', 'location:id,name', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'product_name' => $movement->product?->name ?? 'Unknown',
                    'product_sku' => $movement->product?->sku ?? 'N/A',
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'location' => $movement->location?->name,
                    'reason' => $movement->reason,
                    'notes' => $movement->notes,
                    'reference_type' => $movement->reference_type,
                    'reference_number' => $movement->reference_number,
                    'created_by' => $movement->creator?->name ?? 'System',
                    'created_at' => $movement->created_at->toIso8601String(),
                ];
            })
            ->toArray();
    }

    /**
     * Get category distribution
     */
    public function getCategoryDistribution(): array
    {
        return Product::where('products.organization_id', $this->organizationId)
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw('
                categories.name as category_name,
                COUNT(*) as product_count,
                SUM(products.stock_quantity) as total_stock,
                SUM(products.cost_price * products.stock_quantity) as total_value,
                SUM(products.total_sales) as total_sales
            ')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_value')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->category_name,
                    'product_count' => (int) $item->product_count,
                    'total_stock' => (int) $item->total_stock,
                    'total_value' => round((float) $item->total_value, 2),
                    'total_sales' => (int) $item->total_sales,
                ];
            })
            ->toArray();
    }

    /**
     * Get brand distribution
     */
    public function getBrandDistribution(): array
    {
        return Product::where('products.organization_id', $this->organizationId)
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->selectRaw('
                COALESCE(brands.name, \'No Brand\') as brand_name,
                COUNT(*) as product_count,
                SUM(products.stock_quantity) as total_stock,
                SUM(products.cost_price * products.stock_quantity) as total_value
            ')
            ->groupBy('brands.id', 'brands.name')
            ->orderByDesc('total_value')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->brand_name,
                    'product_count' => (int) $item->product_count,
                    'total_stock' => (int) $item->total_stock,
                    'total_value' => round((float) $item->total_value, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get condition distribution
     */
    public function getConditionDistribution(): array
    {
        return Product::where('organization_id', $this->organizationId)
            ->selectRaw('
                condition,
                COUNT(*) as product_count,
                SUM(stock_quantity) as total_stock,
                SUM(cost_price * stock_quantity) as total_value
            ')
            ->groupBy('condition')
            ->orderByDesc('total_value')
            ->get()
            ->map(function ($item) {
                return [
                    'condition' => $item->condition ?? 'Unknown',
                    'product_count' => (int) $item->product_count,
                    'total_stock' => (int) $item->total_stock,
                    'total_value' => round((float) $item->total_value, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get inventory aging analysis
     */
    public function getAgingAnalysis(): array
    {
        $now = now();

        $brackets = [
            '0-30 days' => [0, 30],
            '31-60 days' => [31, 60],
            '61-90 days' => [61, 90],
            '91-180 days' => [91, 180],
            '180+ days' => [181, 9999],
        ];

        $result = [];

        foreach ($brackets as $label => [$min, $max]) {
            $startDate = $now->copy()->subDays($max);
            $endDate = $now->copy()->subDays($min);

            $data = Product::where('organization_id', $this->organizationId)
                ->where('manage_stock', true)
                ->where('stock_quantity', '>', 0)
                ->when($max < 9999, function ($query) use ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                })
                ->where('created_at', '<=', $endDate)
                ->selectRaw('
                    COUNT(*) as product_count,
                    SUM(stock_quantity) as total_units,
                    SUM(cost_price * stock_quantity) as total_value
                ')
                ->first();

            $result[] = [
                'bracket' => $label,
                'product_count' => (int) ($data->product_count ?? 0),
                'total_units' => (int) ($data->total_units ?? 0),
                'total_value' => round((float) ($data->total_value ?? 0), 2),
            ];
        }

        return $result;
    }

    /**
     * Get movement trends over time
     */
    public function getMovementTrends(string $interval = 'day', int $periods = 30): array
    {
        $groupBy = match ($interval) {
            'hour' => "DATE_TRUNC('hour', created_at)",
            'day' => "DATE_TRUNC('day', created_at)",
            'week' => "DATE_TRUNC('week', created_at)",
            'month' => "DATE_TRUNC('month', created_at)",
            default => "DATE_TRUNC('day', created_at)",
        };

        $startDate = match ($interval) {
            'hour' => now()->subHours($periods),
            'day' => now()->subDays($periods),
            'week' => now()->subWeeks($periods),
            'month' => now()->subMonths($periods),
            default => now()->subDays($periods),
        };

        return InventoryMovement::where('organization_id', $this->organizationId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw("
                {$groupBy} as period,
                type,
                COUNT(*) as count,
                SUM(ABS(quantity)) as quantity
            ")
            ->groupByRaw("{$groupBy}, type")
            ->orderBy('period')
            ->get()
            ->groupBy(function ($item) {
                return Carbon::parse($item->period)->toIso8601String();
            })
            ->map(function ($group, $period) {
                $data = ['period' => $period];
                foreach ($group as $item) {
                    $data[$item->type] = [
                        'count' => (int) $item->count,
                        'quantity' => (int) $item->quantity,
                    ];
                }

                return $data;
            })
            ->values()
            ->toArray();
    }

    /**
     * Get location performance metrics
     */
    public function getLocationPerformance(): array
    {
        return InventoryLevel::where('inventory_levels.organization_id', $this->organizationId)
            ->join('inventory_locations', 'inventory_levels.location_id', '=', 'inventory_locations.id')
            ->join('products', 'inventory_levels.product_id', '=', 'products.id')
            ->selectRaw('
                inventory_locations.id as location_id,
                inventory_locations.name as location_name,
                inventory_locations.type as location_type,
                COUNT(DISTINCT inventory_levels.product_id) as unique_products,
                SUM(inventory_levels.quantity_on_hand) as total_quantity,
                SUM(inventory_levels.quantity_reserved) as reserved_quantity,
                SUM(products.cost_price * inventory_levels.quantity_on_hand) as total_value
            ')
            ->groupBy('inventory_locations.id', 'inventory_locations.name', 'inventory_locations.type')
            ->orderByDesc('total_value')
            ->get()
            ->map(function ($item) {
                return [
                    'location_id' => $item->location_id,
                    'location_name' => $item->location_name,
                    'location_type' => $item->location_type,
                    'unique_products' => (int) $item->unique_products,
                    'total_quantity' => (int) $item->total_quantity,
                    'reserved_quantity' => (int) $item->reserved_quantity,
                    'available_quantity' => (int) $item->total_quantity - (int) $item->reserved_quantity,
                    'total_value' => round((float) $item->total_value, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Clear analytics cache for organization
     */
    public function clearCache(): void
    {
        $store = Cache::store();

        if (method_exists($store->getStore(), 'tags')) {
            try {
                $store->tags(['analytics', "org:{$this->organizationId}"])->flush();

                return;
            } catch (\BadMethodCallException) {
                // Stores without tag support can't selectively flush.
            }
        }
    }
}
