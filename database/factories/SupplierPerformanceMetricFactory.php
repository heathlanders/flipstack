<?php

namespace Database\Factories;

use App\Models\Supplier\SupplierPerformanceMetric;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier\SupplierPerformanceMetric>
 */
class SupplierPerformanceMetricFactory extends Factory
{
    protected $model = SupplierPerformanceMetric::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = $this->faker->dateTimeBetween('-6 months', '-1 month');
        $periodEnd = (clone $periodStart)->modify('+1 month');

        $itemsSubmitted = $this->faker->numberBetween(10, 200);
        $itemsApproved = (int) ($itemsSubmitted * $this->faker->randomFloat(2, 0.70, 0.98));
        $itemsRejected = $itemsSubmitted - $itemsApproved;

        $itemsSold = (int) ($itemsApproved * $this->faker->randomFloat(2, 0.30, 0.80));
        $itemsReturned = (int) ($itemsSold * $this->faker->randomFloat(2, 0.01, 0.10));

        $totalSales = $itemsSold * $this->faker->randomFloat(2, 20, 150);
        $avgSalePrice = $itemsSold > 0 ? $totalSales / $itemsSold : 0;

        return [
            'organization_id' => null,
            'supplier_id' => User::factory(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'period_type' => 'monthly',

            // Submission metrics
            'items_submitted' => $itemsSubmitted,
            'items_approved' => $itemsApproved,
            'items_rejected' => $itemsRejected,
            'approval_rate' => $itemsSubmitted > 0 ? ($itemsApproved / $itemsSubmitted) * 100 : 0,
            'avg_approval_time_hours' => $this->faker->randomFloat(1, 2, 72),

            // Sales metrics
            'items_sold' => $itemsSold,
            'total_sales' => $totalSales,
            'avg_sale_price' => $avgSalePrice,
            'sell_through_rate' => $itemsApproved > 0 ? ($itemsSold / $itemsApproved) * 100 : 0,
            'avg_days_to_sell' => $this->faker->randomFloat(1, 5, 45),

            // Return metrics
            'items_returned' => $itemsReturned,
            'return_rate' => $itemsSold > 0 ? ($itemsReturned / $itemsSold) * 100 : 0,
            'return_reasons' => [
                'not_as_described' => (int) ($itemsReturned * 0.4),
                'damaged' => (int) ($itemsReturned * 0.3),
                'changed_mind' => (int) ($itemsReturned * 0.2),
                'other' => (int) ($itemsReturned * 0.1),
            ],

            // Quality metrics
            'quality_score' => $this->faker->randomFloat(2, 70, 98),
            'listing_accuracy_score' => $this->faker->randomFloat(2, 75, 100),
            'photo_quality_score' => $this->faker->randomFloat(2, 70, 100),
            'description_quality_score' => $this->faker->randomFloat(2, 75, 100),

            // Compliance metrics
            'policy_violations' => $this->faker->numberBetween(0, 3),
            'late_shipments' => $this->faker->numberBetween(0, 5),
            'compliance_score' => $this->faker->randomFloat(2, 80, 100),

            // Customer satisfaction
            'customer_rating_avg' => $this->faker->randomFloat(2, 3.5, 5.0),
            'customer_rating_count' => $this->faker->numberBetween(5, 100),
            'positive_feedback_rate' => $this->faker->randomFloat(2, 85, 100),
            'response_time_hours' => $this->faker->randomFloat(1, 1, 24),

            // Overall score
            'overall_score' => $this->faker->randomFloat(2, 65, 98),
            'score_breakdown' => [
                'sales' => $this->faker->randomFloat(2, 60, 100),
                'quality' => $this->faker->randomFloat(2, 70, 100),
                'compliance' => $this->faker->randomFloat(2, 75, 100),
                'customer_satisfaction' => $this->faker->randomFloat(2, 80, 100),
            ],

            'calculated_at' => now(),
            'metadata' => [
                'calculation_version' => '1.0',
                'weights' => [
                    'sales' => 0.30,
                    'quality' => 0.25,
                    'compliance' => 0.20,
                    'customer_satisfaction' => 0.25,
                ],
            ],
        ];
    }

    /**
     * Create metrics for a high performer.
     */
    public function highPerformer(): static
    {
        return $this->state(function (array $attributes) {
            $itemsSubmitted = $this->faker->numberBetween(50, 200);
            $itemsApproved = (int) ($itemsSubmitted * 0.95);
            $itemsSold = (int) ($itemsApproved * 0.75);
            $itemsReturned = (int) ($itemsSold * 0.02);
            $totalSales = $itemsSold * $this->faker->randomFloat(2, 50, 200);

            return [
                'items_submitted' => $itemsSubmitted,
                'items_approved' => $itemsApproved,
                'items_rejected' => $itemsSubmitted - $itemsApproved,
                'approval_rate' => 95,
                'items_sold' => $itemsSold,
                'total_sales' => $totalSales,
                'avg_sale_price' => $totalSales / $itemsSold,
                'sell_through_rate' => 75,
                'items_returned' => $itemsReturned,
                'return_rate' => 2,
                'quality_score' => $this->faker->randomFloat(2, 92, 98),
                'compliance_score' => $this->faker->randomFloat(2, 95, 100),
                'customer_rating_avg' => $this->faker->randomFloat(2, 4.7, 5.0),
                'positive_feedback_rate' => $this->faker->randomFloat(2, 95, 100),
                'overall_score' => $this->faker->randomFloat(2, 90, 98),
                'policy_violations' => 0,
                'late_shipments' => 0,
            ];
        });
    }

    /**
     * Create metrics for a low performer.
     */
    public function lowPerformer(): static
    {
        return $this->state(function (array $attributes) {
            $itemsSubmitted = $this->faker->numberBetween(10, 50);
            $itemsApproved = (int) ($itemsSubmitted * 0.60);
            $itemsSold = (int) ($itemsApproved * 0.30);
            $itemsReturned = (int) ($itemsSold * 0.15);
            $totalSales = $itemsSold * $this->faker->randomFloat(2, 15, 50);

            return [
                'items_submitted' => $itemsSubmitted,
                'items_approved' => $itemsApproved,
                'items_rejected' => $itemsSubmitted - $itemsApproved,
                'approval_rate' => 60,
                'items_sold' => $itemsSold,
                'total_sales' => $totalSales,
                'avg_sale_price' => $itemsSold > 0 ? $totalSales / $itemsSold : 0,
                'sell_through_rate' => 30,
                'items_returned' => $itemsReturned,
                'return_rate' => 15,
                'quality_score' => $this->faker->randomFloat(2, 55, 70),
                'compliance_score' => $this->faker->randomFloat(2, 60, 75),
                'customer_rating_avg' => $this->faker->randomFloat(2, 3.0, 3.8),
                'positive_feedback_rate' => $this->faker->randomFloat(2, 70, 80),
                'overall_score' => $this->faker->randomFloat(2, 50, 65),
                'policy_violations' => $this->faker->numberBetween(2, 5),
                'late_shipments' => $this->faker->numberBetween(3, 10),
            ];
        });
    }

    /**
     * Create metrics for a specific period.
     */
    public function forPeriod($start, $end, string $type = 'monthly'): static
    {
        return $this->state(fn (array $attributes) => [
            'period_start' => $start,
            'period_end' => $end,
            'period_type' => $type,
        ]);
    }

    /**
     * Create metrics for the current month.
     */
    public function currentMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'period_type' => 'monthly',
        ]);
    }

    /**
     * Assign to a specific supplier.
     */
    public function forSupplier($supplier): static
    {
        return $this->state(fn (array $attributes) => [
            'supplier_id' => $supplier instanceof User ? $supplier->id : $supplier,
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

    /**
     * Set a specific overall score.
     */
    public function withScore(float $score): static
    {
        return $this->state(fn (array $attributes) => [
            'overall_score' => $score,
        ]);
    }
}
