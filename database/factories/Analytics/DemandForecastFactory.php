<?php

namespace Database\Factories\Analytics;

use App\Models\Analytics\DemandForecast;
use App\Models\Catalog\Product;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class DemandForecastFactory extends Factory
{
    protected $model = DemandForecast::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'product_id' => Product::factory(),
            'forecast_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'forecasted_quantity' => $this->faker->numberBetween(5, 200),
            'actual_quantity' => null,
            'confidence_level' => $this->faker->randomElement([
                DemandForecast::CONFIDENCE_LOW,
                DemandForecast::CONFIDENCE_MEDIUM,
                DemandForecast::CONFIDENCE_HIGH,
            ]),
            'algorithm' => $this->faker->randomElement([
                DemandForecast::ALGORITHM_MOVING_AVERAGE,
                DemandForecast::ALGORITHM_WEIGHTED_AVERAGE,
                DemandForecast::ALGORITHM_EXPONENTIAL_SMOOTHING,
            ]),
            'parameters' => [
                'window_days' => $this->faker->randomElement([7, 14, 30]),
                'data_points' => $this->faker->numberBetween(10, 100),
            ],
            'generated_at' => now(),
        ];
    }

    public function highConfidence(): static
    {
        return $this->state([
            'confidence_level' => DemandForecast::CONFIDENCE_HIGH,
        ]);
    }

    public function lowConfidence(): static
    {
        return $this->state([
            'confidence_level' => DemandForecast::CONFIDENCE_LOW,
        ]);
    }

    public function withActual(): static
    {
        return $this->state(function (array $attributes) {
            $forecasted = $attributes['forecasted_quantity'] ?? $this->faker->numberBetween(5, 200);

            return [
                'actual_quantity' => $this->faker->numberBetween(
                    max(0, $forecasted - 20),
                    $forecasted + 20
                ),
            ];
        });
    }

    public function movingAverage(): static
    {
        return $this->state([
            'algorithm' => DemandForecast::ALGORITHM_MOVING_AVERAGE,
        ]);
    }
}
