<?php

namespace Database\Factories\Marketplace;

use App\Models\Catalog\Product;
use App\Models\Marketplace\ScheduledListing;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledListingFactory extends Factory
{
    protected $model = ScheduledListing::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'product_id' => Product::factory(),
            'marketplace' => $this->faker->randomElement(['ebay', 'shopify']),
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+7 days'),
            'status' => ScheduledListing::STATUS_PENDING,
            'listing_data' => [
                'title' => $this->faker->words(5, true),
                'price' => $this->faker->randomFloat(2, 10, 500),
                'quantity' => $this->faker->numberBetween(1, 50),
            ],
            'error_message' => null,
            'published_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => ScheduledListing::STATUS_PENDING,
            'published_at' => null,
            'error_message' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state([
            'status' => ScheduledListing::STATUS_PUBLISHED,
            'published_at' => now(),
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => ScheduledListing::STATUS_FAILED,
            'error_message' => $this->faker->sentence(),
            'published_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => ScheduledListing::STATUS_CANCELLED,
            'published_at' => null,
        ]);
    }

    public function due(): static
    {
        return $this->state([
            'status' => ScheduledListing::STATUS_PENDING,
            'scheduled_at' => now()->subMinutes(5),
        ]);
    }
}
