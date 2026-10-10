<?php

namespace Database\Factories;

use App\Models\User\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'scope' => fake()->randomElement(['platform', 'organization', 'team']),
            'is_system' => false,
            'permissions' => fake()->randomElements([
                'users.view', 'users.create', 'users.update',
                'teams.view', 'teams.create', 'teams.update',
                'inventory.view', 'inventory.create', 'inventory.update',
                'orders.view', 'orders.create', 'orders.process',
                'analytics.view', 'reports.view', 'settings.view',
            ], fake()->numberBetween(3, 8)),
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    public function platform(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'platform',
        ]);
    }

    public function organization(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'organization',
        ]);
    }

    public function team(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'team',
        ]);
    }
}
