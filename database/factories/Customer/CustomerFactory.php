<?php

namespace Database\Factories\Customer;

use App\Models\Customer\Customer;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $totalOrders = $this->faker->numberBetween(0, 50);
        $totalSpent = $totalOrders * $this->faker->randomFloat(2, 20, 200);

        return [
            'organization_id' => Organization::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'marketplace_source' => $this->faker->randomElement(['ebay', 'shopify', 'amazon', 'direct']),
            'marketplace_customer_id' => $this->faker->optional()->numerify('MC-########'),
            'first_order_at' => $totalOrders > 0 ? $this->faker->dateTimeBetween('-1 year', '-1 month') : null,
            'last_order_at' => $totalOrders > 0 ? $this->faker->dateTimeBetween('-1 month', 'now') : null,
            'total_orders' => $totalOrders,
            'total_spent' => $totalSpent,
            'average_order_value' => $totalOrders > 0 ? round($totalSpent / $totalOrders, 2) : 0,
            'tags' => $this->faker->optional()->randomElements(['vip', 'wholesale', 'repeat', 'new'], $this->faker->numberBetween(0, 3)),
            'notes' => $this->faker->optional()->sentence(),
            'is_flagged' => false,
            'flag_reason' => null,
        ];
    }

    public function flagged(): static
    {
        return $this->state([
            'is_flagged' => true,
            'flag_reason' => $this->faker->sentence(),
        ]);
    }

    public function repeatBuyer(): static
    {
        return $this->state(function () {
            $orders = $this->faker->numberBetween(3, 50);
            $spent = $orders * $this->faker->randomFloat(2, 30, 200);

            return [
                'total_orders' => $orders,
                'total_spent' => $spent,
                'average_order_value' => round($spent / $orders, 2),
            ];
        });
    }

    public function fromEbay(): static
    {
        return $this->state([
            'marketplace_source' => 'ebay',
            'marketplace_customer_id' => $this->faker->numerify('EBAY-########'),
        ]);
    }

    public function fromShopify(): static
    {
        return $this->state([
            'marketplace_source' => 'shopify',
            'marketplace_customer_id' => $this->faker->numerify('SHOP-########'),
        ]);
    }
}
