<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->company(),
            'company_name' => $this->faker->company(),
            'business_type' => $this->faker->randomElement(['manufacturer', 'distributor', 'wholesaler', 'dropshipper']),
            'contact_name' => $this->faker->name(),
            'code' => strtoupper($this->faker->bothify('SUP-???-###')),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'website' => $this->faker->optional()->url(),
            'address_line1' => $this->faker->streetAddress(),
            'address_line2' => $this->faker->optional()->secondaryAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'postal_code' => $this->faker->postcode(),
            'country' => 'US',
            'tax_id' => $this->faker->optional()->numerify('##-#######'),
            'payment_terms' => $this->faker->randomElement([15, 30, 45, 60]),
            'currency' => 'USD',
            'minimum_order_amount' => $this->faker->randomFloat(2, 100, 1000),
            'lead_time_days' => $this->faker->numberBetween(3, 14),
            'performance_rating' => $this->faker->randomFloat(2, 3, 5),
            'total_orders' => $this->faker->numberBetween(0, 500),
            'on_time_delivery_rate' => $this->faker->randomFloat(2, 0.8, 1.0),
            'is_active' => true,
            'is_preferred' => false,
            'portal_enabled' => false,
            'onboarding_complete' => true,
            'created_by' => User::factory(),
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

    public function preferred(): static
    {
        return $this->state(['is_preferred' => true]);
    }

    public function withPortalAccess(): static
    {
        return $this->state([
            'portal_enabled' => true,
            'portal_activated_at' => now(),
        ]);
    }

    public function onboarding(): static
    {
        return $this->state([
            'onboarding_complete' => false,
        ]);
    }
}
