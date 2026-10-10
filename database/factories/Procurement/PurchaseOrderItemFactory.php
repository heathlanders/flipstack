<?php

namespace Database\Factories\Procurement;

use App\Models\Catalog\Product;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 100);
        $unitCost = $this->faker->randomFloat(2, 5, 500);

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'description' => $this->faker->words(4, true),
            'sku' => 'SKU-'.strtoupper($this->faker->bothify('???-###')),
            'quantity_ordered' => $quantity,
            'quantity_received' => 0,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function fullyReceived(): static
    {
        return $this->state(function (array $attributes) {
            $qty = $attributes['quantity_ordered'] ?? $this->faker->numberBetween(1, 100);

            return [
                'quantity_received' => $qty,
            ];
        });
    }

    public function partiallyReceived(): static
    {
        return $this->state(function (array $attributes) {
            $qty = $attributes['quantity_ordered'] ?? $this->faker->numberBetween(5, 100);

            return [
                'quantity_received' => $this->faker->numberBetween(1, $qty - 1),
            ];
        });
    }
}
