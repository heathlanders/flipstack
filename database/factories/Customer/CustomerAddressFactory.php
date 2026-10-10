<?php

namespace Database\Factories\Customer;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => $this->faker->randomElement(['shipping', 'billing']),
            'is_default' => false,
            'name' => $this->faker->name(),
            'line1' => $this->faker->streetAddress(),
            'line2' => $this->faker->optional()->secondaryAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'postal_code' => $this->faker->postcode(),
            'country' => 'US',
        ];
    }

    public function shipping(): static
    {
        return $this->state(['type' => 'shipping']);
    }

    public function billing(): static
    {
        return $this->state(['type' => 'billing']);
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
