<?php

namespace Database\Factories;

use App\Models\Supplier\SupplierTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier\SupplierTier>
 */
class SupplierTierFactory extends Factory
{
    protected $model = SupplierTier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tiers = ['platinum', 'gold', 'silver', 'bronze', 'probation'];
        $tierIndex = $this->faker->numberBetween(0, 4);
        $tierName = ucfirst($tiers[$tierIndex]);

        $tierConfigs = [
            'platinum' => ['min' => 95, 'max' => 100, 'adjustment' => -5],
            'gold' => ['min' => 85, 'max' => 94.99, 'adjustment' => -2.5],
            'silver' => ['min' => 75, 'max' => 84.99, 'adjustment' => 0],
            'bronze' => ['min' => 65, 'max' => 74.99, 'adjustment' => 2.5],
            'probation' => ['min' => 0, 'max' => 64.99, 'adjustment' => 5],
        ];

        $config = $tierConfigs[$tiers[$tierIndex]];

        return [
            'organization_id' => null,
            'name' => $tierName,
            'slug' => $tiers[$tierIndex],
            'description' => $this->faker->sentence(),
            'min_score' => $config['min'],
            'max_score' => $config['max'],
            'commission_adjustment' => $config['adjustment'],
            'benefits' => [
                'Priority support',
                'Early access to features',
                'Dedicated account manager',
            ],
            'restrictions' => [],
            'requirements' => [
                'min_products' => $this->faker->numberBetween(5, 50),
                'min_sales' => $this->faker->numberBetween(100, 1000),
            ],
            'auto_promote' => true,
            'auto_demote' => true,
            'evaluation_period_days' => 30,
            'is_default' => false,
            'is_active' => true,
            'color' => $this->faker->hexColor(),
            'icon' => 'star',
            'sort_order' => $tierIndex,
        ];
    }

    /**
     * Indicate that this is the platinum tier.
     */
    public function platinum(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Platinum',
            'slug' => 'platinum',
            'min_score' => 95,
            'max_score' => 100,
            'commission_adjustment' => -5,
            'benefits' => [
                'Per-sale settlements',
                'Dedicated account manager',
                '5% commission discount',
                'Priority listing placement',
                'Early access to new features',
            ],
            'color' => '#64748b',
            'sort_order' => 0,
        ]);
    }

    /**
     * Indicate that this is the gold tier.
     */
    public function gold(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Gold',
            'slug' => 'gold',
            'min_score' => 85,
            'max_score' => 94.99,
            'commission_adjustment' => -2.5,
            'benefits' => [
                'Weekly settlements',
                'Priority support',
                '2.5% commission discount',
                'Featured seller badge',
            ],
            'color' => '#eab308',
            'sort_order' => 1,
        ]);
    }

    /**
     * Indicate that this is the silver (default) tier.
     */
    public function silver(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Silver',
            'slug' => 'silver',
            'min_score' => 75,
            'max_score' => 84.99,
            'commission_adjustment' => 0,
            'is_default' => true,
            'benefits' => [
                'Bi-weekly settlements',
                'Standard support',
                'Standard commission rates',
            ],
            'color' => '#9ca3af',
            'sort_order' => 2,
        ]);
    }

    /**
     * Indicate that this is the bronze tier.
     */
    public function bronze(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Bronze',
            'slug' => 'bronze',
            'min_score' => 65,
            'max_score' => 74.99,
            'commission_adjustment' => 2.5,
            'benefits' => [
                'Monthly settlements',
                'Standard support',
            ],
            'restrictions' => [
                'Limited to 100 active listings',
            ],
            'color' => '#ea580c',
            'sort_order' => 3,
        ]);
    }

    /**
     * Indicate that this is the probation tier.
     */
    public function probation(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Probation',
            'slug' => 'probation',
            'min_score' => 0,
            'max_score' => 64.99,
            'commission_adjustment' => 5,
            'benefits' => [
                'Monthly settlements',
            ],
            'restrictions' => [
                'Limited to 50 active listings',
                'Manual review for new products',
                'No promotional features',
            ],
            'color' => '#dc2626',
            'sort_order' => 4,
        ]);
    }

    /**
     * Set as the default tier.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Set as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Assign to an organization.
     */
    public function forOrganization($organizationId): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organizationId,
        ]);
    }
}
