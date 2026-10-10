<?php

namespace Database\Factories;

use App\Enums\Supplier\SettlementStatus;
use App\Models\Supplier\Settlement;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier\Settlement>
 */
class SettlementFactory extends Factory
{
    protected $model = Settlement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = $this->faker->dateTimeBetween('-3 months', '-1 month');
        $periodEnd = (clone $periodStart)->modify('+2 weeks');

        $grossAmount = $this->faker->randomFloat(2, 100, 10000);
        $commissionAmount = $grossAmount * ($this->faker->randomFloat(2, 0.15, 0.25));
        $adjustments = $this->faker->randomFloat(2, -50, 50);
        $netAmount = $grossAmount - $commissionAmount + $adjustments;

        return [
            'organization_id' => null,
            'supplier_id' => User::factory(),
            'settlement_number' => 'STL-'.strtoupper($this->faker->unique()->bothify('??####')),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'gross_amount' => $grossAmount,
            'commission_amount' => $commissionAmount,
            'adjustments' => $adjustments,
            'net_amount' => $netAmount,
            'items_count' => $this->faker->numberBetween(1, 50),
            'status' => SettlementStatus::PENDING,
            'payment_method' => $this->faker->randomElement(['bank_transfer', 'paypal', 'check']),
            'payment_reference' => null,
            'payment_date' => null,
            'notes' => $this->faker->optional()->sentence(),
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'version' => '1.0',
            ],
            'generated_at' => now(),
            'approved_at' => null,
            'approved_by' => null,
            'processed_at' => null,
            'processed_by' => null,
        ];
    }

    /**
     * Indicate that the settlement is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SettlementStatus::PENDING,
            'approved_at' => null,
            'approved_by' => null,
            'processed_at' => null,
            'processed_by' => null,
            'payment_reference' => null,
            'payment_date' => null,
        ]);
    }

    /**
     * Indicate that the settlement is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SettlementStatus::APPROVED,
            'approved_at' => now(),
            'approved_by' => User::factory(),
            'processed_at' => null,
            'processed_by' => null,
        ]);
    }

    /**
     * Indicate that the settlement is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SettlementStatus::PROCESSING,
            'approved_at' => now()->subHours(2),
            'approved_by' => User::factory(),
            'processed_at' => null,
            'processed_by' => null,
        ]);
    }

    /**
     * Indicate that the settlement is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SettlementStatus::PAID,
            'approved_at' => now()->subDays(3),
            'approved_by' => User::factory(),
            'processed_at' => now()->subDay(),
            'processed_by' => User::factory(),
            'payment_reference' => 'PAY-'.strtoupper($this->faker->bothify('??######')),
            'payment_date' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the settlement has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SettlementStatus::FAILED,
            'approved_at' => now()->subDays(2),
            'approved_by' => User::factory(),
            'processed_at' => now()->subDay(),
            'processed_by' => User::factory(),
            'notes' => 'Payment failed: '.$this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the settlement is disputed.
     */
    public function disputed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SettlementStatus::DISPUTED,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'dispute' => [
                    'reason' => $this->faker->sentence(),
                    'opened_at' => now()->toIso8601String(),
                    'items_disputed' => $this->faker->numberBetween(1, 5),
                ],
            ]),
        ]);
    }

    /**
     * Indicate that the settlement is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SettlementStatus::CANCELLED,
            'notes' => 'Settlement cancelled: '.$this->faker->sentence(),
        ]);
    }

    /**
     * Create a settlement with a specific gross amount.
     */
    public function withAmount(float $grossAmount, float $commissionRate = 0.20): static
    {
        $commissionAmount = $grossAmount * $commissionRate;

        return $this->state(fn (array $attributes) => [
            'gross_amount' => $grossAmount,
            'commission_amount' => $commissionAmount,
            'net_amount' => $grossAmount - $commissionAmount + ($attributes['adjustments'] ?? 0),
        ]);
    }

    /**
     * Create a settlement for a specific period.
     */
    public function forPeriod($start, $end): static
    {
        return $this->state(fn (array $attributes) => [
            'period_start' => $start,
            'period_end' => $end,
        ]);
    }

    /**
     * Create a settlement for the current month.
     */
    public function currentMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ]);
    }

    /**
     * Create a settlement for the previous month.
     */
    public function previousMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
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
     * Set a specific payment method.
     */
    public function paymentMethod(string $method): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => $method,
        ]);
    }

    /**
     * Add adjustments to the settlement.
     */
    public function withAdjustments(float $amount, ?string $reason = null): static
    {
        return $this->state(function (array $attributes) use ($amount, $reason) {
            $netAmount = $attributes['gross_amount'] - $attributes['commission_amount'] + $amount;

            return [
                'adjustments' => $amount,
                'net_amount' => $netAmount,
                'notes' => $reason ?? $attributes['notes'],
            ];
        });
    }
}
