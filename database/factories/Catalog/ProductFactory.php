<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'category_id' => Category::factory(),
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->slug(),
            'short_description' => $this->faker->sentence(),
            'description' => $this->faker->paragraphs(3, true),
            'sku' => 'PRD-'.strtoupper($this->faker->bothify('?????')),
            'barcode' => $this->faker->ean13(),
            'type' => $this->faker->randomElement(['simple', 'variable', 'bundle']),
            'status' => $this->faker->randomElement(['active', 'inactive', 'draft']),
            'cost_price' => $this->faker->randomFloat(2, 10, 500),
            'selling_price' => function (array $attributes) {
                return $attributes['cost_price'] * $this->faker->randomFloat(2, 1.2, 3.0);
            },
            'currency' => 'USD',
            'weight' => $this->faker->randomFloat(3, 0.1, 10),
            'length' => $this->faker->randomFloat(2, 5, 50),
            'width' => $this->faker->randomFloat(2, 5, 50),
            'height' => $this->faker->randomFloat(2, 1, 20),
            'track_inventory' => true,
            'manage_stock' => true,
            'stock_quantity' => $this->faker->numberBetween(0, 100),
            'low_stock_threshold' => $this->faker->numberBetween(5, 20),
            'requires_shipping' => true,
            'attributes' => [
                'brand' => $this->faker->company(),
                'color' => $this->faker->colorName(),
                'material' => $this->faker->randomElement(['Cotton', 'Polyester', 'Metal', 'Plastic']),
            ],
            'featured_image' => $this->faker->imageUrl(800, 600, 'products'),
            'gallery_images' => [
                $this->faker->imageUrl(800, 600, 'products'),
                $this->faker->imageUrl(800, 600, 'products'),
            ],
            'search_keywords' => implode(', ', $this->faker->words(5)),
            'created_by' => User::factory(),
            'published_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function variable(): static
    {
        return $this->state(['type' => 'variable']);
    }

    public function inStock(): static
    {
        return $this->state(['stock_quantity' => $this->faker->numberBetween(50, 200)]);
    }

    public function lowStock(): static
    {
        return $this->state(function (array $attributes) {
            $threshold = $this->faker->numberBetween(10, 20);

            return [
                'low_stock_threshold' => $threshold,
                'stock_quantity' => $this->faker->numberBetween(1, $threshold - 1),
            ];
        });
    }
}
