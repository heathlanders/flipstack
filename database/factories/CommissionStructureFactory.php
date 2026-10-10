<?php

namespace Database\Factories;

use App\Enums\Supplier\CommissionType;
use App\Models\Supplier\CommissionStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier\CommissionStructure>
 */
class CommissionStructureFactory extends Factory
{
    protected $model = CommissionStructure::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => null,
            'name' => $this->faker->words(3, true).' Commission',
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence(),
            'type' => CommissionType::PERCENTAGE,
            'base_rate' => $this->faker->randomFloat(2, 10, 30),
            'min_rate' => null,
            'max_rate' => null,
            'flat_fee' => null,
            'tiers' => null,
            'category_rates' => null,
            'volume_bonuses' => null,
            'time_based_rates' => null,
            'custom_rules' => null,
            'is_default' => false,
            'is_active' => true,
            'effective_from' => now(),
            'effective_until' => null,
        ];
    }

    /**
     * Standard percentage-based commission.
     */
    public function standard(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Standard Commission',
            'slug' => 'standard',
            'type' => CommissionType::PERCENTAGE,
            'base_rate' => 20,
            'is_default' => true,
        ]);
    }

    /**
     * Tiered commission based on item value.
     */
    public function tiered(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Tiered Commission',
            'slug' => 'tiered',
            'type' => CommissionType::TIERED,
            'base_rate' => 18,
            'tiers' => [
                ['min' => 0, 'max' => 50, 'rate' => 22],
                ['min' => 50, 'max' => 100, 'rate' => 20],
                ['min' => 100, 'max' => 250, 'rate' => 18],
                ['min' => 250, 'max' => 500, 'rate' => 15],
                ['min' => 500, 'max' => null, 'rate' => 12],
            ],
        ]);
    }

    /**
     * Category-based commission rates.
     */
    public function categoryBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Category-Based Commission',
            'slug' => 'category-based',
            'type' => CommissionType::CATEGORY_BASED,
            'base_rate' => 20,
            'category_rates' => [
                'electronics' => 15,
                'clothing' => 25,
                'collectibles' => 20,
                'home_goods' => 22,
                'sports' => 18,
            ],
        ]);
    }

    /**
     * Flat fee commission.
     */
    public function flatFee(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Flat Fee Commission',
            'slug' => 'flat-fee',
            'type' => CommissionType::FLAT_FEE,
            'base_rate' => 0,
            'flat_fee' => 3.00,
        ]);
    }

    /**
     * Hybrid commission (percentage + flat fee).
     */
    public function hybrid(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Hybrid Commission',
            'slug' => 'hybrid',
            'type' => CommissionType::HYBRID,
            'base_rate' => 15,
            'flat_fee' => 2.50,
        ]);
    }

    /**
     * Volume-based with bonuses.
     */
    public function volumeBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Volume Seller Commission',
            'slug' => 'volume-seller',
            'type' => CommissionType::PERCENTAGE,
            'base_rate' => 18,
            'volume_bonuses' => [
                ['threshold' => 5000, 'bonus' => -1],
                ['threshold' => 10000, 'bonus' => -2],
                ['threshold' => 25000, 'bonus' => -3],
                ['threshold' => 50000, 'bonus' => -4],
                ['threshold' => 100000, 'bonus' => -5],
            ],
        ]);
    }

    /**
     * Time-limited promotional rate.
     */
    public function promotional(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Promotional Commission',
            'slug' => 'promotional-'.$this->faker->unique()->randomNumber(4),
            'type' => CommissionType::PERCENTAGE,
            'base_rate' => 15,
            'effective_from' => now(),
            'effective_until' => now()->addMonths(3),
        ]);
    }

    /**
     * New seller introductory rate.
     */
    public function newSeller(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'New Seller Intro',
            'slug' => 'new-seller-intro',
            'type' => CommissionType::PERCENTAGE,
            'base_rate' => 15,
            'description' => 'Introductory rate for new sellers during first 90 days',
            'effective_until' => now()->addDays(90),
        ]);
    }

    /**
     * Set as the default structure.
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
     * Set with min/max rate caps.
     */
    public function withRateCaps(float $min, float $max): static
    {
        return $this->state(fn (array $attributes) => [
            'min_rate' => $min,
            'max_rate' => $max,
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
