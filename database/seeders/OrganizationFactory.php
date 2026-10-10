<?php

namespace Database\Factories;

use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'subdomain' => \Illuminate\Support\Str::slug($name),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'description' => $this->faker->paragraph(),
            'settings' => [
                'features' => [
                    'inventory_management' => true,
                    'order_processing' => true,
                    'multi_platform_sync' => $this->faker->boolean(70),
                    'analytics' => true,
                    'api_access' => $this->faker->boolean(30),
                ],
                'limits' => [
                    'max_users' => $this->faker->randomElement([10, 25, 50, 100]),
                    'max_products' => $this->faker->randomElement([1000, 5000, 10000, 50000]),
                    'max_orders_per_month' => $this->faker->randomElement([100, 500, 1000, 5000]),
                ],
                'security' => [
                    'require_mfa' => $this->faker->boolean(30),
                    'password_expiry_days' => $this->faker->randomElement([30, 60, 90, 180]),
                    'max_login_attempts' => 5,
                    'session_timeout_minutes' => $this->faker->randomElement([120, 240, 480, 720]),
                ],
                'notifications' => [
                    'email_enabled' => true,
                    'sms_enabled' => $this->faker->boolean(20),
                    'slack_webhook' => $this->faker->boolean(40) ? $this->faker->url() : null,
                ],
            ],
            'branding' => [
                'logo_url' => $this->faker->boolean(30) ? $this->faker->imageUrl(200, 80) : null,
                'primary_color' => $this->faker->hexColor(),
                'secondary_color' => $this->faker->hexColor(),
                'accent_color' => $this->faker->hexColor(),
            ],
            'timezone' => $this->faker->timezone(),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'CAD', 'AUD']),
            'status' => $this->faker->randomElement(['active', 'trial', 'suspended']),
            'trial_ends_at' => $this->faker->boolean(20) ? $this->faker->dateTimeBetween('now', '+30 days') : null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'trial_ends_at' => null,
        ]);
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trial',
            'trial_ends_at' => $this->faker->dateTimeBetween('now', '+30 days'),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
            'suspended_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }
}
