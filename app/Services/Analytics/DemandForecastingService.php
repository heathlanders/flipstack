<?php

namespace App\Services\Analytics;

use App\Models\Analytics\DemandForecast;
use App\Models\Analytics\ReorderSuggestion;
use App\Models\Catalog\Product;
use App\Models\Fulfillment\OrderItem;
use Carbon\Carbon;

class DemandForecastingService
{
    /**
     * Calculate the average units sold per day for a product over a given period.
     */
    public function calculateSalesVelocity(string $productId, int $days = 30): float
    {
        $since = now()->subDays($days);

        $totalSold = OrderItem::where('product_id', $productId)
            ->whereHas('order', function ($q) use ($since) {
                $q->where('created_at', '>=', $since)
                    ->whereNotNull('paid_at');
            })
            ->sum('quantity');

        if ($days <= 0) {
            return 0.0;
        }

        return round((float) $totalSold / $days, 2);
    }

    /**
     * Generate a demand forecast for a single product.
     */
    public function generateForecast(string $productId, string $orgId, int $daysAhead = 30): DemandForecast
    {
        $velocity = $this->calculateSalesVelocity($productId, 30);
        $forecastedQty = (int) ceil($velocity * $daysAhead);

        // Determine confidence based on data availability
        $recentOrderCount = OrderItem::where('product_id', $productId)
            ->whereHas('order', function ($q) {
                $q->where('created_at', '>=', now()->subDays(30))
                    ->whereNotNull('paid_at');
            })
            ->count();

        if ($recentOrderCount >= 10) {
            $confidence = DemandForecast::CONFIDENCE_HIGH;
        } elseif ($recentOrderCount >= 3) {
            $confidence = DemandForecast::CONFIDENCE_MEDIUM;
        } else {
            $confidence = DemandForecast::CONFIDENCE_LOW;
        }

        return DemandForecast::create([
            'organization_id' => $orgId,
            'product_id' => $productId,
            'forecast_date' => now()->addDays($daysAhead)->toDateString(),
            'forecasted_quantity' => $forecastedQty,
            'confidence_level' => $confidence,
            'algorithm' => DemandForecast::ALGORITHM_MOVING_AVERAGE,
            'parameters' => [
                'lookback_days' => 30,
                'forecast_days' => $daysAhead,
                'daily_velocity' => $velocity,
                'order_count' => $recentOrderCount,
            ],
            'generated_at' => now(),
        ]);
    }

    /**
     * Generate forecasts for all active products in an organization.
     */
    public function generateForOrganization(string $orgId): int
    {
        $products = Product::where('organization_id', $orgId)
            ->where('status', 'active')
            ->get(['id', 'name', 'available_quantity', 'stock_quantity']);

        $count = 0;
        $notificationService = app(\App\Services\NotificationService::class);
        $warningWindow = (int) config('notifications-events.defaults.stockout_warning_days', 14);

        foreach ($products as $product) {
            $this->generateForecast($product->id, $orgId);
            $count++;

            $velocity = $this->calculateSalesVelocity($product->id, 30);
            if ($velocity <= 0) {
                continue;
            }

            $stock = (int) ($product->available_quantity ?? $product->stock_quantity ?? 0);
            $daysLeft = $stock > 0 ? (int) floor($stock / $velocity) : 0;

            if ($daysLeft <= $warningWindow) {
                $stockoutDate = $stock > 0 ? now()->addDays($daysLeft)->toDateString() : now()->toDateString();

                $notificationService->dispatchEvent(
                    eventKey: 'analytics.stockout.warning',
                    organizationId: (int) $orgId,
                    args: [
                        (string) $product->id,
                        (string) ($product->name ?? 'Unknown'),
                        $stock,
                        $stockoutDate,
                        $daysLeft,
                    ],
                    relatedId: (string) $product->id,
                );
            }
        }

        return $count;
    }

    /**
     * Estimate the date when a product will run out of stock.
     */
    public function getStockoutDate(string $productId): ?Carbon
    {
        $product = Product::find($productId);
        if (! $product) {
            return null;
        }

        $currentStock = (int) ($product->available_quantity ?? $product->stock_quantity ?? 0);
        if ($currentStock <= 0) {
            return now();
        }

        $velocity = $this->calculateSalesVelocity($productId, 30);
        if ($velocity <= 0) {
            return null;
        }

        $daysUntilStockout = (int) floor($currentStock / $velocity);

        return now()->addDays($daysUntilStockout);
    }

    /**
     * Return summary statistics for the forecasting dashboard.
     */
    public function getDashboardData(string $orgId): array
    {
        $products = Product::where('organization_id', $orgId)
            ->where('status', 'active')
            ->get(['id', 'name', 'sku', 'available_quantity', 'stock_quantity']);

        $atRisk = [];
        $totalVelocity = 0.0;
        $velocityCount = 0;

        foreach ($products as $product) {
            $velocity = $this->calculateSalesVelocity($product->id, 30);
            $stock = (int) ($product->available_quantity ?? $product->stock_quantity ?? 0);

            if ($velocity > 0) {
                $totalVelocity += $velocity;
                $velocityCount++;

                $daysLeft = $stock > 0 ? (int) floor($stock / $velocity) : 0;
                $stockoutDate = $stock > 0 ? now()->addDays($daysLeft) : now();

                if ($daysLeft <= 14) {
                    $priority = $daysLeft <= 3 ? 'critical' : ($daysLeft <= 7 ? 'high' : 'medium');
                    $atRisk[] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'current_stock' => $stock,
                        'daily_velocity' => $velocity,
                        'estimated_stockout_date' => $stockoutDate->toDateString(),
                        'days_left' => $daysLeft,
                        'priority' => $priority,
                    ];
                }
            }
        }

        // Sort at-risk by days_left ascending
        usort($atRisk, fn ($a, $b) => $a['days_left'] <=> $b['days_left']);

        $forecastsGenerated = DemandForecast::where('organization_id', $orgId)
            ->where('generated_at', '>=', now()->subDays(7))
            ->count();

        $criticalSuggestions = ReorderSuggestion::where('organization_id', $orgId)
            ->where('status', ReorderSuggestion::STATUS_PENDING)
            ->where('priority', ReorderSuggestion::PRIORITY_CRITICAL)
            ->count();

        return [
            'products_at_risk' => count($atRisk),
            'at_risk_products' => array_slice($atRisk, 0, 50),
            'avg_daily_velocity' => $velocityCount > 0 ? round($totalVelocity / $velocityCount, 2) : 0,
            'forecasts_generated' => $forecastsGenerated,
            'critical_suggestions' => $criticalSuggestions,
        ];
    }
}
