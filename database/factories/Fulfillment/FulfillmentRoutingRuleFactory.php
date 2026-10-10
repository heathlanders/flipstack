<?php

namespace Database\Factories\Fulfillment;

use App\Models\Fulfillment\FulfillmentRoutingRule;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class FulfillmentRoutingRuleFactory extends Factory
{
    protected $model = FulfillmentRoutingRule::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->words(3, true).' Routing Rule',
            'priority' => $this->faker->numberBetween(1, 100),
            'rule_type' => $this->faker->randomElement(FulfillmentRoutingRule::RULE_TYPES),
            'parameters' => [],
            'is_active' => true,
        ];
    }

    public function proximity(): static
    {
        return $this->state([
            'rule_type' => FulfillmentRoutingRule::TYPE_PROXIMITY,
            'parameters' => [
                'max_distance_miles' => $this->faker->numberBetween(50, 500),
            ],
        ]);
    }

    public function lowestCost(): static
    {
        return $this->state([
            'rule_type' => FulfillmentRoutingRule::TYPE_LOWEST_COST,
            'parameters' => [],
        ]);
    }

    public function highestStock(): static
    {
        return $this->state([
            'rule_type' => FulfillmentRoutingRule::TYPE_HIGHEST_STOCK,
            'parameters' => [],
        ]);
    }

    public function roundRobin(): static
    {
        return $this->state([
            'rule_type' => FulfillmentRoutingRule::TYPE_ROUND_ROBIN,
            'parameters' => [
                'last_used_index' => 0,
            ],
        ]);
    }

    public function specificLocation(?int $locationId = null): static
    {
        return $this->state([
            'rule_type' => FulfillmentRoutingRule::TYPE_SPECIFIC_LOCATION,
            'parameters' => [
                'location_id' => $locationId ?? $this->faker->numberBetween(1, 100),
            ],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
