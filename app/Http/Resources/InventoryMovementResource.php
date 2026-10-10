<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
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
                'stock_quantity' => $this->product->stock_quantity,
            ]),
            'product_variation_id' => $this->product_variation_id,
            'product_variation' => $this->whenLoaded('productVariation', fn () => $this->productVariation ? [
                'id' => $this->productVariation->id,
                'name' => $this->productVariation->name,
                'sku' => $this->productVariation->sku,
            ] : null),
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'name' => $this->location->name,
                'type' => $this->location->type,
            ]),
            'type' => $this->type,
            'type_label' => $this->type_label,
            'quantity' => $this->quantity,
            'net_quantity_change' => $this->net_quantity_change,
            'unit_cost' => $this->unit_cost ? number_format($this->unit_cost, 2) : null,
            'total_cost' => $this->total_cost ? number_format($this->total_cost, 2) : null,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference_number' => $this->reference_number,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'batch_number' => $this->batch_number,
            'lot_number' => $this->lot_number,
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'is_stock_increase' => $this->isStockIncrease(),
            'is_stock_decrease' => $this->isStockDecrease(),
            'is_transfer' => $this->isTransfer(),
            'created_at' => $this->created_at->toIso8601String(),
            'created_at_formatted' => $this->created_at->format('M j, Y g:i A'),
        ];
    }
}
