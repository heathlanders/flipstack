<?php

namespace Database\Factories;

use App\Models\Supplier\Settlement;
use App\Models\Supplier\SettlementItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier\SettlementItem>
 */
class SettlementItemFactory extends Factory
{
    protected $model = SettlementItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $salePrice = $this->faker->randomFloat(2, 10, 500);
        $commissionRate = $this->faker->randomFloat(2, 0.15, 0.25);
        $commissionAmount = $salePrice * $commissionRate;
        $netAmount = $salePrice - $commissionAmount;

        return [
            'settlement_id' => Settlement::factory(),
            'product_id' => null,
            'order_id' => null,
            'order_item_id' => null,
            'sku' => strtoupper($this->faker->bothify('SKU-????-####')),
            'product_name' => $this->faker->words(3, true),
            'sale_date' => $this->faker->dateTimeBetween('-2 months', '-1 day'),
            'sale_price' => $salePrice,
            'commission_rate' => $commissionRate * 100,
            'commission_amount' => $commissionAmount,
            'net_amount' => $netAmount,
            'adjustments' => 0,
            'adjustment_reason' => null,
            'is_return' => false,
            'return_date' => null,
            'return_reason' => null,
            'metadata' => [
                'category' => $this->faker->randomElement(['Electronics', 'Clothing', 'Home', 'Sports', 'Collectibles']),
                'condition' => $this->faker->randomElement(['new', 'like_new', 'good', 'fair']),
            ],
        ];
    }

    /**
     * Indicate that this item is a return.
     */
    public function return(): static
    {
        return $this->state(function (array $attributes) {
            $returnAmount = -abs($attributes['sale_price']);
            $returnCommission = -abs($attributes['commission_amount']);

            return [
                'is_return' => true,
                'return_date' => $this->faker->dateTimeBetween($attributes['sale_date'], 'now'),
                'return_reason' => $this->faker->randomElement([
                    'Not as described',
                    'Damaged in shipping',
                    'Changed mind',
                    'Wrong item received',
                    'Defective',
                ]),
                'sale_price' => $returnAmount,
                'commission_amount' => $returnCommission,
                'net_amount' => $returnAmount - $returnCommission,
            ];
        });
    }

    /**
     * Create an item with adjustments.
     */
    public function withAdjustment(float $amount, ?string $reason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'adjustments' => $amount,
            'adjustment_reason' => $reason ?? 'Manual adjustment',
            'net_amount' => $attributes['net_amount'] + $amount,
        ]);
    }

    /**
     * Create a high-value item.
     */
    public function highValue(): static
    {
        return $this->state(function (array $attributes) {
            $salePrice = $this->faker->randomFloat(2, 500, 5000);
            $commissionRate = 0.12; // Lower rate for high-value items
            $commissionAmount = $salePrice * $commissionRate;

            return [
                'sale_price' => $salePrice,
                'commission_rate' => $commissionRate * 100,
                'commission_amount' => $commissionAmount,
                'net_amount' => $salePrice - $commissionAmount,
            ];
        });
    }

    /**
     * Create a low-value item.
     */
    public function lowValue(): static
    {
        return $this->state(function (array $attributes) {
            $salePrice = $this->faker->randomFloat(2, 5, 25);
            $commissionRate = 0.25; // Higher rate for low-value items
            $commissionAmount = $salePrice * $commissionRate;

            return [
                'sale_price' => $salePrice,
                'commission_rate' => $commissionRate * 100,
                'commission_amount' => $commissionAmount,
                'net_amount' => $salePrice - $commissionAmount,
            ];
        });
    }

    /**
     * Create an item in a specific category.
     */
    public function inCategory(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'category' => $category,
            ]),
        ]);
    }

    /**
     * Assign to a specific settlement.
     */
    public function forSettlement($settlement): static
    {
        return $this->state(fn (array $attributes) => [
            'settlement_id' => $settlement instanceof Settlement ? $settlement->id : $settlement,
        ]);
    }

    /**
     * Set a specific sale date.
     */
    public function soldOn($date): static
    {
        return $this->state(fn (array $attributes) => [
            'sale_date' => $date,
        ]);
    }

    /**
     * Link to a product.
     */
    public function forProduct($productId): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $productId,
        ]);
    }

    /**
     * Link to an order.
     */
    public function forOrder($orderId, $orderItemId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
        ]);
    }
}
