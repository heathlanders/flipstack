<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_product_id' => $this->parent_product_id,
            'sku' => $this->sku,
            'variation_attributes' => $this->variation_attributes,
            'variation_name' => $this->getVariationName(),

            // Pricing (effective from parent if not set)
            'cost_price' => $this->cost_price,
            'selling_price' => $this->selling_price,
            'msrp' => $this->msrp,
            'effective_cost_price' => $this->getEffectiveCostPrice(),
            'effective_selling_price' => $this->getEffectiveSellingPrice(),

            // Physical attributes (effective from parent if not set)
            'weight' => $this->weight,
            'effective_weight' => $this->getEffectiveWeight(),
            'dimensions' => [
                'length' => $this->length,
                'width' => $this->width,
                'height' => $this->height,
            ],

            // Inventory
            'stock_quantity' => $this->stock_quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'available_stock' => $this->getAvailableStock(),
            'is_in_stock' => $this->isInStock(),

            // Media
            'featured_image' => $this->featured_image,
            'gallery_images' => $this->gallery_images,

            // Status
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,

            // Relationships
            'parent_product' => new ProductResource($this->whenLoaded('parentProduct')),
            'inventory_levels' => InventoryLevelResource::collection($this->whenLoaded('inventoryLevels')),
            'supplier_products' => SupplierProductResource::collection($this->whenLoaded('supplierProducts')),

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
