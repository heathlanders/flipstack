<?php

namespace Database\Factories\Fulfillment;

use App\Models\Fulfillment\ShippingRule;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShippingRuleFactory extends Factory
{
    protected $model = ShippingRule::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->words(3, true).' Shipping Rule',
            'priority' => $this->faker->numberBetween(1, 100),
            'conditions' => [
                [
                    'field' => 'weight',
                    'operator' => 'less_than',
                    'value' => $this->faker->randomFloat(2, 1, 20),
                ],
            ],
            'action_carrier' => $this->faker->randomElement([
                ShippingRule::CARRIER_USPS,
                ShippingRule::CARRIER_UPS,
                ShippingRule::CARRIER_FEDEX,
                ShippingRule::CARRIER_DHL,
            ]),
            'action_service' => $this->faker->randomElement(['Ground', 'Priority', 'Express', 'First Class']),
            'action_package_preset_id' => null,
            'is_active' => true,
        ];
    }

    public function usps(): static
    {
        return $this->state([
            'action_carrier' => ShippingRule::CARRIER_USPS,
            'action_service' => $this->faker->randomElement(['First Class', 'Priority', 'Priority Express']),
        ]);
    }

    public function ups(): static
    {
        return $this->state([
            'action_carrier' => ShippingRule::CARRIER_UPS,
            'action_service' => $this->faker->randomElement(['Ground', 'Next Day Air', '2nd Day Air']),
        ]);
    }

    public function fedex(): static
    {
        return $this->state([
            'action_carrier' => ShippingRule::CARRIER_FEDEX,
            'action_service' => $this->faker->randomElement(['Ground', 'Express Saver', 'Overnight']),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
