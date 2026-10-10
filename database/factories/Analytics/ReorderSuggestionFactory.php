<?php

namespace Database\Factories\Analytics;

use App\Models\Analytics\ReorderSuggestion;
use App\Models\Catalog\Product;
use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReorderSuggestionFactory extends Factory
{
    protected $model = ReorderSuggestion::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'product_id' => Product::factory(),
            'supplier_id' => Supplier::factory(),
            'suggested_quantity' => $this->faker->numberBetween(10, 200),
            'reason' => $this->faker->randomElement([
                ReorderSuggestion::REASON_LOW_STOCK_FORECAST,
                ReorderSuggestion::REASON_SEASONAL_TREND,
                ReorderSuggestion::REASON_VELOCITY_INCREASE,
            ]),
            'estimated_stockout_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'priority' => $this->faker->randomElement([
                ReorderSuggestion::PRIORITY_LOW,
                ReorderSuggestion::PRIORITY_MEDIUM,
                ReorderSuggestion::PRIORITY_HIGH,
                ReorderSuggestion::PRIORITY_CRITICAL,
            ]),
            'status' => ReorderSuggestion::STATUS_PENDING,
            'accepted_at' => null,
            'purchase_order_id' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => ReorderSuggestion::STATUS_PENDING,
            'accepted_at' => null,
        ]);
    }

    public function accepted(): static
    {
        return $this->state([
            'status' => ReorderSuggestion::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state([
            'status' => ReorderSuggestion::STATUS_DISMISSED,
        ]);
    }

    public function critical(): static
    {
        return $this->state([
            'priority' => ReorderSuggestion::PRIORITY_CRITICAL,
            'estimated_stockout_date' => now()->addDays(3),
        ]);
    }

    public function highPriority(): static
    {
        return $this->state([
            'priority' => ReorderSuggestion::PRIORITY_HIGH,
        ]);
    }
}
