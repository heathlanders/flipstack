<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductBundle;
use App\Models\Catalog\ProductBundleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductBundleItemFactory extends Factory
{
    protected $model = ProductBundleItem::class;

    public function definition(): array
    {
        return [
            'bundle_id' => ProductBundle::factory(),
            'component_product_id' => Product::factory(),
            'quantity' => $this->faker->numberBetween(1, 5),
            'sort_order' => $this->faker->numberBetween(0, 10),
            'is_required' => true,
        ];
    }

    public function optional(): static
    {
        return $this->state(['is_required' => false]);
    }
}
