<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\InventoryLocation;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryLocationFactory extends Factory
{
    protected $model = InventoryLocation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->company().' '.$this->faker->randomElement(['Warehouse', 'Store', 'Distribution Center']),
            'code' => strtoupper($this->faker->bothify('LOC-???-###')),
            'type' => $this->faker->randomElement(['warehouse', 'store', 'supplier', 'customer', 'virtual']),
            'address_line1' => $this->faker->streetAddress(),
            'address_line2' => $this->faker->optional()->secondaryAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'postal_code' => $this->faker->postcode(),
            'country' => 'US',
            'contact_person' => $this->faker->name(),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function warehouse(): static
    {
        return $this->state(['type' => 'warehouse']);
    }

    public function storeType(): static
    {
        return $this->state(['type' => 'store']);
    }
}
