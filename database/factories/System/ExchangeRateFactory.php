<?php

namespace Database\Factories\System;

use App\Models\System\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        return [
            'base_currency' => 'USD',
            'target_currency' => $this->faker->randomElement(['EUR', 'GBP', 'CAD', 'AUD', 'JPY']),
            'rate' => $this->faker->randomFloat(8, 0.5, 150.0),
            'source' => $this->faker->randomElement(['ecb', 'openexchangerates', 'manual']),
            'fetched_at' => now(),
        ];
    }

    public function usdToEur(): static
    {
        return $this->state([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => $this->faker->randomFloat(8, 0.85, 0.95),
        ]);
    }

    public function usdToGbp(): static
    {
        return $this->state([
            'base_currency' => 'USD',
            'target_currency' => 'GBP',
            'rate' => $this->faker->randomFloat(8, 0.75, 0.85),
        ]);
    }

    public function usdToCad(): static
    {
        return $this->state([
            'base_currency' => 'USD',
            'target_currency' => 'CAD',
            'rate' => $this->faker->randomFloat(8, 1.25, 1.40),
        ]);
    }

    public function manual(): static
    {
        return $this->state(['source' => 'manual']);
    }
}
