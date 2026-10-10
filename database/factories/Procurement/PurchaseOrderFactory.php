<?php

namespace Database\Factories\Procurement;

use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\Procurement\PurchaseOrder;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 100, 10000);
        $taxAmount = round($subtotal * 0.08, 2);
        $shippingCost = $this->faker->randomFloat(2, 0, 200);

        return [
            'organization_id' => Organization::factory(),
            'supplier_id' => Supplier::factory(),
            'location_id' => InventoryLocation::factory(),
            'po_number' => 'PO-'.strtoupper($this->faker->bothify('???-#####')),
            'status' => PurchaseOrder::STATUS_DRAFT,
            'order_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'expected_delivery_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'notes' => $this->faker->optional()->sentence(),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'shipping_cost' => $shippingCost,
            'total_amount' => $subtotal + $taxAmount + $shippingCost,
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state([
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);
    }

    public function submitted(): static
    {
        return $this->state([
            'status' => PurchaseOrder::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->state([
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'submitted_at' => now()->subDays(7),
            'received_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => PurchaseOrder::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $this->faker->sentence(),
        ]);
    }

    public function approved(): static
    {
        return $this->state([
            'status' => PurchaseOrder::STATUS_SUBMITTED,
            'submitted_at' => now()->subDays(3),
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }
}
