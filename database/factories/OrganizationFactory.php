<?php

namespace Database\Factories;

use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = fake()->company();
        $slug = Str::slug($name).'-'.Str::lower(Str::random(5));

        return [
            'name' => $name,
            'slug' => $slug,
            'subdomain' => $slug,
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'description' => fake()->sentence(),
            'timezone' => fake()->timezone(),
            'currency' => fake()->randomElement(['USD', 'EUR', 'GBP', 'CAD']),
            'status' => 'active',
            'settings' => [],
            'branding' => [],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }
}
