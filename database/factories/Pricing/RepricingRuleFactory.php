<?php

namespace Database\Factories\Pricing;

use App\Models\Organization\Organization;
use App\Models\Pricing\RepricingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class RepricingRuleFactory extends Factory
{
    protected $model = RepricingRule::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->words(3, true).' Rule',
            'marketplace' => $this->faker->randomElement(['ebay', 'shopify', '*']),
            'scope_type' => $this->faker->randomElement(['all', 'category', 'product']),
            'scope_ids' => [],
            'strategy' => RepricingRule::STRATEGY_COST_PLUS,
            'parameters' => [
                'margin_percent' => $this->faker->numberBetween(10, 50),
                'min_price' => $this->faker->randomFloat(2, 5, 20),
                'max_price' => $this->faker->randomFloat(2, 100, 500),
            ],
            'is_active' => true,
            'priority' => $this->faker->numberBetween(1, 100),
            'last_run_at' => null,
        ];
    }

    public function matchLowest(): static
    {
        return $this->state([
            'strategy' => RepricingRule::STRATEGY_MATCH_LOWEST,
            'parameters' => [
                'min_price' => $this->faker->randomFloat(2, 5, 20),
                'max_price' => $this->faker->randomFloat(2, 100, 500),
            ],
        ]);
    }

    public function beatByAmount(): static
    {
        return $this->state([
            'strategy' => RepricingRule::STRATEGY_BEAT_BY_AMOUNT,
            'parameters' => [
                'beat_amount' => $this->faker->randomFloat(2, 0.50, 5.00),
                'min_price' => $this->faker->randomFloat(2, 5, 20),
                'max_price' => $this->faker->randomFloat(2, 100, 500),
            ],
        ]);
    }

    public function beatByPercent(): static
    {
        return $this->state([
            'strategy' => RepricingRule::STRATEGY_BEAT_BY_PERCENT,
            'parameters' => [
                'beat_percent' => $this->faker->numberBetween(1, 15),
                'min_price' => $this->faker->randomFloat(2, 5, 20),
                'max_price' => $this->faker->randomFloat(2, 100, 500),
            ],
        ]);
    }

    public function costPlus(): static
    {
        return $this->state([
            'strategy' => RepricingRule::STRATEGY_COST_PLUS,
            'parameters' => [
                'margin_percent' => $this->faker->numberBetween(20, 60),
                'min_price' => $this->faker->randomFloat(2, 5, 20),
                'max_price' => $this->faker->randomFloat(2, 100, 500),
            ],
        ]);
    }

    public function mapFloor(): static
    {
        return $this->state([
            'strategy' => RepricingRule::STRATEGY_MAP_FLOOR,
            'parameters' => [
                'min_price' => $this->faker->randomFloat(2, 10, 50),
                'max_price' => $this->faker->randomFloat(2, 100, 500),
            ],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
