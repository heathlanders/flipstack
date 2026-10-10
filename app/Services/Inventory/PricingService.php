<?php

namespace App\Services\Inventory;

use App\Models\Catalog\Product;
use App\Models\User\User;
use Illuminate\Support\Facades\Log;

class PricingService
{
    /**
     * Preview price changes without applying them.
     */
    public function preview(array $productIds, array $rules): array
    {
        $products = Product::whereIn('id', $productIds)
            ->select(['id', 'name', 'sku', 'cost_price', 'selling_price', 'msrp'])
            ->get();

        $previews = [];

        foreach ($products as $product) {
            $newPrice = $this->calculateNewPrice($product, $rules);

            $previews[] = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'current_price' => $product->selling_price,
                'new_price' => $newPrice,
                'cost_price' => $product->cost_price,
                'margin' => $newPrice > 0 && $product->cost_price
                    ? round((($newPrice - $product->cost_price) / $newPrice) * 100, 1)
                    : null,
                'change' => $product->selling_price > 0
                    ? round((($newPrice - $product->selling_price) / $product->selling_price) * 100, 1)
                    : null,
            ];
        }

        return $previews;
    }

    /**
     * Apply price changes to products.
     */
    public function apply(array $productIds, array $rules, User $user): array
    {
        $products = Product::whereIn('id', $productIds)->get();
        $updated = 0;
        $skipped = 0;

        foreach ($products as $product) {
            $newPrice = $this->calculateNewPrice($product, $rules);
            $oldPrice = $product->selling_price;

            if ($newPrice === $oldPrice) {
                $skipped++;

                continue;
            }

            $product->selling_price = $newPrice;
            $product->save();
            $updated++;

            Log::info('PricingService: price updated', [
                'product_id' => $product->id,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'user_id' => $user->id,
                'rules' => $rules,
            ]);
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => count($productIds),
        ];
    }

    /**
     * Calculate the new price for a product based on pricing rules.
     */
    protected function calculateNewPrice(Product $product, array $rules): float
    {
        $strategy = $rules['strategy'] ?? 'percentage';
        $field = $rules['price_field'] ?? 'selling_price';

        $basePrice = match ($field) {
            'cost_price' => (float) ($product->cost_price ?? 0),
            'msrp' => (float) ($product->msrp ?? 0),
            default => (float) ($product->selling_price ?? 0),
        };

        $newPrice = match ($strategy) {
            'fixed' => (float) ($rules['value'] ?? 0),
            'percentage' => $this->applyPercentage($basePrice, $rules),
            'cost_plus_fixed' => (float) ($product->cost_price ?? 0) + (float) ($rules['value'] ?? 0),
            'cost_plus_percentage' => (float) ($product->cost_price ?? 0) * (1 + (float) ($rules['value'] ?? 0) / 100),
            default => $basePrice,
        };

        // Apply rounding rules
        $newPrice = $this->applyRounding($newPrice, $rules['rounding'] ?? null);

        // Enforce minimum price
        $minPrice = (float) ($rules['min_price'] ?? 0);
        if ($minPrice > 0 && $newPrice < $minPrice) {
            $newPrice = $minPrice;
        }

        // Enforce maximum price
        $maxPrice = (float) ($rules['max_price'] ?? 0);
        if ($maxPrice > 0 && $newPrice > $maxPrice) {
            $newPrice = $maxPrice;
        }

        return max(0, round($newPrice, 2));
    }

    protected function applyPercentage(float $basePrice, array $rules): float
    {
        $value = (float) ($rules['value'] ?? 0);
        $operation = $rules['operation'] ?? 'increase';

        $amount = ($basePrice * $value) / 100;

        return $operation === 'increase'
            ? $basePrice + $amount
            : $basePrice - $amount;
    }

    protected function applyRounding(float $price, ?string $rounding): float
    {
        return match ($rounding) {
            'nearest_99' => floor($price) + 0.99,
            'nearest_95' => floor($price) + 0.95,
            'nearest_dollar' => round($price),
            'up_dollar' => ceil($price),
            default => round($price, 2),
        };
    }
}
