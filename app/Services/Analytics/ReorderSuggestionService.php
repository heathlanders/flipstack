<?php

namespace App\Services\Analytics;

use App\Models\Analytics\ReorderSuggestion;
use App\Models\Catalog\Product;
use Illuminate\Support\Collection;

class ReorderSuggestionService
{
    public function __construct(
        protected DemandForecastingService $forecastingService
    ) {}

    /**
     * Generate reorder suggestions for products at risk of stockout.
     */
    public function generateSuggestions(string $orgId): int
    {
        $products = Product::where('organization_id', $orgId)
            ->where('status', 'active')
            ->get(['id', 'available_quantity', 'stock_quantity']);

        $count = 0;
        $highPriorityCount = 0;
        $leadTimeDays = 14;
        $safetyStockDays = 7;

        foreach ($products as $product) {
            $velocity = $this->forecastingService->calculateSalesVelocity($product->id, 30);
            if ($velocity <= 0) {
                continue;
            }

            $stock = (int) ($product->available_quantity ?? $product->stock_quantity ?? 0);
            $daysLeft = $stock > 0 ? (int) floor($stock / $velocity) : 0;

            // Only suggest reorder if stockout is within lead time + safety buffer
            if ($daysLeft > ($leadTimeDays + $safetyStockDays)) {
                continue;
            }

            // Skip if there is already a pending suggestion for this product
            $existingPending = ReorderSuggestion::withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->where('product_id', $product->id)
                ->where('status', ReorderSuggestion::STATUS_PENDING)
                ->exists();

            if ($existingPending) {
                continue;
            }

            $suggestedQty = $this->calculateReorderQuantity($product->id, $leadTimeDays, $safetyStockDays);
            if ($suggestedQty <= 0) {
                continue;
            }

            $stockoutDate = $stock > 0 ? now()->addDays($daysLeft) : now();

            // Determine priority
            if ($daysLeft <= 3) {
                $priority = ReorderSuggestion::PRIORITY_CRITICAL;
            } elseif ($daysLeft <= 7) {
                $priority = ReorderSuggestion::PRIORITY_HIGH;
            } elseif ($daysLeft <= 14) {
                $priority = ReorderSuggestion::PRIORITY_MEDIUM;
            } else {
                $priority = ReorderSuggestion::PRIORITY_LOW;
            }

            ReorderSuggestion::create([
                'organization_id' => $orgId,
                'product_id' => $product->id,
                'suggested_quantity' => $suggestedQty,
                'reason' => ReorderSuggestion::REASON_LOW_STOCK_FORECAST,
                'estimated_stockout_date' => $stockoutDate->toDateString(),
                'priority' => $priority,
                'status' => ReorderSuggestion::STATUS_PENDING,
            ]);

            $count++;

            if (in_array($priority, [ReorderSuggestion::PRIORITY_CRITICAL, ReorderSuggestion::PRIORITY_HIGH], true)) {
                $highPriorityCount++;
            }
        }

        if ($count > 0) {
            app(\App\Services\NotificationService::class)->dispatchEvent(
                eventKey: 'analytics.reorder.suggestions',
                organizationId: (int) $orgId,
                args: [$count, $highPriorityCount],
                relatedId: now()->toDateString(),
            );
        }

        return $count;
    }

    /**
     * Mark a suggestion as accepted.
     */
    public function accept(ReorderSuggestion $suggestion): void
    {
        $suggestion->update([
            'status' => ReorderSuggestion::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Mark a suggestion as dismissed.
     */
    public function dismiss(ReorderSuggestion $suggestion): void
    {
        $suggestion->update([
            'status' => ReorderSuggestion::STATUS_DISMISSED,
        ]);
    }

    /**
     * Get all pending suggestions for an organization, ordered by priority.
     */
    public function getPendingSuggestions(string $orgId): Collection
    {
        return ReorderSuggestion::where('organization_id', $orgId)
            ->where('status', ReorderSuggestion::STATUS_PENDING)
            ->byPriority()
            ->with(['product:id,name,sku', 'supplier:id,name'])
            ->get();
    }

    /**
     * Calculate how many units to reorder based on velocity and lead time.
     */
    public function calculateReorderQuantity(string $productId, int $leadTimeDays = 14, int $safetyStockDays = 7): int
    {
        $velocity = $this->forecastingService->calculateSalesVelocity($productId, 30);
        if ($velocity <= 0) {
            return 0;
        }

        $product = Product::find($productId);
        if (! $product) {
            return 0;
        }

        $currentStock = (int) ($product->available_quantity ?? $product->stock_quantity ?? 0);

        // Reorder quantity = demand during (lead time + review period) + safety stock - current stock
        $demandDuringLead = $velocity * $leadTimeDays;
        $safetyStock = $velocity * $safetyStockDays;
        $reorderQty = (int) ceil($demandDuringLead + $safetyStock - $currentStock);

        return max($reorderQty, 0);
    }
}
