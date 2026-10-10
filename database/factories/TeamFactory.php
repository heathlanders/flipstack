<?php

namespace Database\Factories;

use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Team',
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'department' => fake()->randomElement(['Sales', 'Operations', 'IT', 'Management', 'Marketing', 'Finance']),
            'personal_team' => false,
            'settings' => [
                'description' => fake()->sentence(),
                'permissions' => fake()->randomElements([
                    'inventory.view', 'inventory.create', 'inventory.update',
                    'orders.view', 'orders.create', 'orders.process',
                    'analytics.view', 'reports.view',
                ], fake()->numberBetween(2, 6)),
                'goals' => [
                    'monthly_target' => fake()->numberBetween(1000, 10000),
                    'quarterly_target' => fake()->numberBetween(10000, 50000),
                ],
            ],
        ];
    }

    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'personal_team' => true,
            'name' => fake()->name()."'s Team",
            'department' => null,
        ]);
    }
}
