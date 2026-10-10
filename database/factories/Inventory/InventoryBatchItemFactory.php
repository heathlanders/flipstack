<?php

namespace Database\Factories\Inventory;

use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\InventoryBatchItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryBatchItemFactory extends Factory
{
    protected $model = InventoryBatchItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 50);
        $unitCost = $this->faker->randomFloat(2, 5, 200);

        return [
            'batch_id' => InventoryBatch::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 2),
        ];
    }
}
