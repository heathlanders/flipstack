<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variation_id' => $this->product_variation_id,
            'location_id' => $this->location_id,

            // Stock levels
            'quantity_on_hand' => $this->quantity_on_hand,
            'quantity_reserved' => $this->quantity_reserved,
            'quantity_available' => $this->quantity_available,

            // Reorder management
            'reorder_point' => $this->reorder_point,
            'reorder_quantity' => $this->reorder_quantity,
            'max_stock_level' => $this->max_stock_level,

            // Costing
            'average_cost' => $this->average_cost,
            'total_cost' => $this->total_cost,

            // Status checks
            'is_low_stock' => $this->isLowStock(),
            'is_out_of_stock' => $this->isOutOfStock(),
            'is_over_stock' => $this->isOverStock(),

            // Relationships
            'product' => new ProductResource($this->whenLoaded('product')),
            'product_variation' => new ProductVariationResource($this->whenLoaded('productVariation')),
            'location' => new LocationResource($this->whenLoaded('location')),

            // Timestamps
            'last_movement_at' => $this->last_movement_at?->toISOString(),
            'last_count_at' => $this->last_count_at?->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
