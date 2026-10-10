<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryBatchFactory extends Factory
{
    protected $model = InventoryBatch::class;

    public function definition(): array
    {
        $itemCount = $this->faker->numberBetween(1, 50);
        $acquisitionCost = $this->faker->randomFloat(2, 50, 5000);

        return [
            'organization_id' => Organization::factory(),
            'batch_number' => 'BATCH-'.strtoupper($this->faker->bothify('???-#####')),
            'supplier_id' => Supplier::factory(),
            'name' => $this->faker->words(3, true).' Batch',
            'description' => $this->faker->optional()->sentence(),
            'acquisition_date' => $this->faker->dateTimeBetween('-60 days', 'now'),
            'acquisition_cost' => $acquisitionCost,
            'item_count' => $itemCount,
            'cost_per_item' => round($acquisitionCost / $itemCount, 2),
            'source_description' => $this->faker->optional()->sentence(),
            'notes' => $this->faker->optional()->sentence(),
            'status' => InventoryBatch::STATUS_OPEN,
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => InventoryBatch::STATUS_OPEN]);
    }

    public function closed(): static
    {
        return $this->state(['status' => InventoryBatch::STATUS_CLOSED]);
    }
}
