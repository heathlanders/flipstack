<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variation_id' => $this->product_variation_id,
            'location_id' => $this->location_id,

            // Movement details
            'type' => $this->type,
            'type_label' => $this->getMovementTypeLabel(),
            'quantity' => $this->quantity,
            'display_quantity' => $this->getDisplayQuantity(),
            'is_increase' => $this->isIncrease(),
            'is_decrease' => $this->isDecrease(),

            // Costing
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,

            // Reference
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference_number' => $this->reference_number,

            // Details
            'reason' => $this->reason,
            'notes' => $this->notes,

            // Batch/lot tracking
            'batch_number' => $this->batch_number,
            'lot_number' => $this->lot_number,
            'expiry_date' => $this->expiry_date?->toDateString(),

            // Balance tracking
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,

            // Relationships
            'product' => new ProductResource($this->whenLoaded('product')),
            'product_variation' => new ProductVariationResource($this->whenLoaded('productVariation')),
            'location' => new LocationResource($this->whenLoaded('location')),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),

            // Timestamp
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
