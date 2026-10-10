<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'product_id' => $this->product_id,
            'product_variation_id' => $this->product_variation_id,

            // Supplier-specific information
            'supplier_info' => [
                'supplier_sku' => $this->supplier_sku,
                'supplier_name' => $this->supplier_name,
                'supplier_description' => $this->supplier_description,
            ],

            // Pricing from supplier
            'pricing' => [
                'cost_price' => $this->cost_price,
                'currency' => $this->currency,
                'formatted_cost' => $this->currency.' '.number_format($this->cost_price, 2),
                'minimum_order_quantity' => $this->minimum_order_quantity,
                'moq_text' => "MOQ: {$this->minimum_order_quantity} units",
            ],

            // Lead times and availability
            'availability' => [
                'lead_time_days' => $this->lead_time_days,
                'lead_time_text' => "{$this->lead_time_days} business days",
                'is_available' => $this->is_available,
                'availability_date' => $this->availability_date?->toDateString(),
                'estimated_delivery' => $this->getEstimatedDeliveryDate()->toDateString(),
                'is_currently_available' => $this->isCurrentlyAvailable(),
            ],

            // Quality and performance
            'quality' => [
                'quality_rating' => $this->quality_rating,
                'quality_stars' => str_repeat('⭐', (int) round($this->quality_rating)),
                'is_preferred' => $this->is_preferred,
            ],

            // Status
            'is_active' => $this->is_active,
            'notes' => $this->notes,

            // Relationships
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'product_variation' => new ProductVariationResource($this->whenLoaded('productVariation')),

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
