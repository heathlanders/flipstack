<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryLevelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'product_variation_id' => $this->product_variation_id,
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'name' => $this->location->name,
                'type' => $this->location->type,
            ]),
            'quantity_on_hand' => $this->quantity_on_hand,
            'quantity_reserved' => $this->quantity_reserved,
            'quantity_available' => $this->quantity_available,
            'reorder_point' => $this->reorder_point,
            'reorder_quantity' => $this->reorder_quantity,
            'max_stock_level' => $this->max_stock_level,
            'average_cost' => $this->average_cost ? number_format($this->average_cost, 2) : '0.00',
            'total_cost' => $this->total_cost ? number_format($this->total_cost, 2) : '0.00',
            'stock_status' => $this->getStockStatus(),
            'needs_reorder' => $this->needsReorder(),
            'has_available_stock' => $this->hasAvailableStock(),
            'last_movement_at' => $this->last_movement_at?->toIso8601String(),
            'last_movement_at_formatted' => $this->last_movement_at?->format('M j, Y g:i A'),
            'last_count_at' => $this->last_count_at?->toIso8601String(),
            'last_count_at_formatted' => $this->last_count_at?->format('M j, Y g:i A'),
            'days_since_count' => $this->last_count_at
                ? $this->last_count_at->diffInDays(now())
                : null,
            'version' => $this->version,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
