<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductBundle;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductBundleFactory extends Factory
{
    protected $model = ProductBundle::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'product_id' => Product::factory(),
            'name' => $this->faker->words(3, true).' Bundle',
            'description' => $this->faker->optional()->sentence(),
            'bundle_type' => ProductBundle::TYPE_FIXED,
            'pick_n_quantity' => null,
            'is_active' => true,
        ];
    }

    public function fixed(): static
    {
        return $this->state([
            'bundle_type' => ProductBundle::TYPE_FIXED,
            'pick_n_quantity' => null,
        ]);
    }

    public function pickN(int $quantity = 3): static
    {
        return $this->state([
            'bundle_type' => ProductBundle::TYPE_PICK_N,
            'pick_n_quantity' => $quantity,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
