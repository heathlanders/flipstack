<?php

namespace Database\Factories\Supplier;

use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\Supplier\SupplierProductSubmission;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierProductSubmissionFactory extends Factory
{
    protected $model = SupplierProductSubmission::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'supplier_id' => Supplier::factory(),
            'intake_queue_id' => null,
            'status' => SupplierProductSubmission::STATUS_PENDING_REVIEW,
            'submitted_data' => [
                'name' => $this->faker->words(4, true),
                'sku' => strtoupper($this->faker->bothify('SUP-???-###')),
                'description' => $this->faker->paragraph(),
                'cost_price' => $this->faker->randomFloat(2, 5, 200),
                'suggested_price' => $this->faker->randomFloat(2, 15, 400),
                'quantity_available' => $this->faker->numberBetween(10, 500),
                'upc' => $this->faker->ean13(),
                'brand' => $this->faker->company(),
                'condition' => $this->faker->randomElement(['NEW', 'USED_LIKE_NEW', 'USED_GOOD']),
            ],
            'review_notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function pendingReview(): static
    {
        return $this->state([
            'status' => SupplierProductSubmission::STATUS_PENDING_REVIEW,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state([
            'status' => SupplierProductSubmission::STATUS_APPROVED,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'review_notes' => 'Approved for listing.',
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => SupplierProductSubmission::STATUS_REJECTED,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'review_notes' => $this->faker->sentence(),
        ]);
    }
}
