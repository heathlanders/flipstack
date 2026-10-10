<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductBundleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bundle_product_id' => $this->bundle_product_id,
            'component_product_id' => $this->component_product_id,
            'component_variation_id' => $this->component_variation_id,
            'quantity' => $this->quantity,
            'is_optional' => $this->is_optional,
            'sort_order' => $this->sort_order,

            // Component details
            'component_info' => [
                'quantity_text' => "{$this->quantity}x",
                'is_required' => ! $this->is_optional,
                'requirement_text' => $this->is_optional ? 'Optional' : 'Required',
            ],

            // Effective product (variation or base product)
            'effective_product' => $this->when($this->relationLoaded('componentProduct'), function () {
                return $this->componentVariation
                    ? new ProductVariationResource($this->componentVariation)
                    : new ProductResource($this->componentProduct);
            }),

            // Pricing calculations
            'pricing' => $this->when($this->relationLoaded('componentProduct'), function () {
                return [
                    'unit_cost' => $this->getTotalCost() / $this->quantity,
                    'total_cost' => $this->getTotalCost(),
                    'formatted_unit_cost' => '$'.number_format($this->getTotalCost() / $this->quantity, 2),
                    'formatted_total_cost' => '$'.number_format($this->getTotalCost(), 2),
                ];
            }),

            // Stock availability
            'availability' => $this->when($this->relationLoaded('componentProduct'), function () {
                return [
                    'is_in_stock' => $this->isInStock(),
                    'can_fulfill' => $this->isInStock(),
                    'stock_status' => $this->isInStock() ? 'Available' : 'Out of Stock',
                ];
            }),

            // Relationships
            'bundle_product' => new ProductResource($this->whenLoaded('bundleProduct')),
            'component_product' => new ProductResource($this->whenLoaded('componentProduct')),
            'component_variation' => new ProductVariationResource($this->whenLoaded('componentVariation')),

            // Timestamp
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
