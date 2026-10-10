<?php

namespace Database\Factories;

use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'organization_id' => Organization::factory(),
            'employee_id' => 'EMP'.fake()->unique()->numberBetween(1000, 9999),
            'phone' => fake()->phoneNumber(),
            'department' => fake()->randomElement(['Sales', 'Operations', 'IT', 'Management', 'Marketing', 'Finance']),
            'job_title' => fake()->jobTitle(),
            'hire_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'preferences' => [
                'theme' => fake()->randomElement(['light', 'dark']),
                'language' => 'en',
                'timezone' => fake()->timezone(),
                'date_format' => fake()->randomElement(['Y-m-d', 'm/d/Y', 'd/m/Y']),
                'time_format' => fake()->randomElement(['12h', '24h']),
                'dashboard_layout' => fake()->randomElement(['default', 'compact', 'detailed']),
            ],
            'notification_settings' => [
                'email' => [
                    'system_updates' => fake()->boolean(80),
                    'order_notifications' => fake()->boolean(90),
                    'inventory_alerts' => fake()->boolean(70),
                    'weekly_reports' => fake()->boolean(50),
                ],
                'browser' => [
                    'enabled' => fake()->boolean(90),
                    'order_notifications' => fake()->boolean(80),
                    'inventory_alerts' => fake()->boolean(60),
                ],
                'mobile' => [
                    'enabled' => fake()->boolean(40),
                    'order_notifications' => fake()->boolean(50),
                    'inventory_alerts' => fake()->boolean(30),
                ],
            ],
            'is_active' => fake()->boolean(95),
            'last_login_at' => fake()->boolean(80) ? fake()->dateTimeBetween('-30 days', 'now') : null,
            'last_login_ip' => fake()->boolean(80) ? fake()->ipv4() : null,
            'mfa_enabled' => fake()->boolean(20),
            'password_expires_at' => fake()->boolean(30) ? fake()->dateTimeBetween('now', '+90 days') : null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withMfa(): static
    {
        return $this->state(fn (array $attributes) => [
            'mfa_enabled' => true,
            'mfa_secret' => encrypt('test-secret'),
            'mfa_recovery_codes' => encrypt(json_encode([
                'code1', 'code2', 'code3', 'code4', 'code5',
            ])),
        ]);
    }

    public function expiredPassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'password_expires_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    public function withPersonalTeam(?callable $callback = null): static
    {
        return $this->afterCreating(function (User $user) use ($callback) {
            $team = Team::forceCreate([
                'name' => $user->name."'s Team",
                'user_id' => $user->id,
                'organization_id' => $user->organization_id,
                'personal_team' => true,
            ]);

            $user->current_team_id = $team->id;
            $user->save();

            if ($callback) {
                $callback($user);
            }
        });
    }
}
