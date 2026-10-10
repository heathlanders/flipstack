<?php

namespace App\Models\Inventory;

use App\Models\Catalog\Product;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBundle extends Model
{
    use HasUuid;

    const UPDATED_AT = null; // Only track created_at

    protected $fillable = [
        'bundle_product_id',
        'component_product_id',
        'component_variation_id',
        'quantity',
        'is_optional',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'is_optional' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function bundleProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundle_product_id');
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    public function componentVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'component_variation_id');
    }

    // Scopes
    public function scopeRequired($query)
    {
        return $query->where('is_optional', false);
    }

    public function scopeOptional($query)
    {
        return $query->where('is_optional', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // Helper methods
    public function getEffectiveProduct()
    {
        return $this->componentVariation ?? $this->componentProduct;
    }

    public function getTotalCost(): float
    {
        $product = $this->getEffectiveProduct();
        $unitCost = $product instanceof ProductVariation
            ? $product->getEffectiveCostPrice()
            : $product->cost_price;

        return $unitCost * $this->quantity;
    }

    public function isInStock(): bool
    {
        $product = $this->getEffectiveProduct();
        $availableStock = $product instanceof ProductVariation
            ? $product->getAvailableStock()
            : $product->getAvailableStock();

        return $availableStock >= $this->quantity;
    }
}
