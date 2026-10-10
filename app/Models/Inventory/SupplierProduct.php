<?php

namespace App\Models\Inventory;

use App\Models\Catalog\Product;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Model
{
    use HasUuid;

    protected $fillable = [
        'supplier_id',
        'product_id',
        'product_variation_id',
        'supplier_sku',
        'supplier_name',
        'supplier_description',
        'cost_price',
        'currency',
        'minimum_order_quantity',
        'lead_time_days',
        'is_available',
        'availability_date',
        'quality_rating',
        'is_preferred',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'minimum_order_quantity' => 'integer',
        'lead_time_days' => 'integer',
        'is_available' => 'boolean',
        'availability_date' => 'date',
        'quality_rating' => 'decimal:2',
        'is_preferred' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopePreferred($query)
    {
        return $query->where('is_preferred', true);
    }

    public function scopeByQuality($query)
    {
        return $query->orderBy('quality_rating', 'desc');
    }

    public function scopeByCost($query)
    {
        return $query->orderBy('cost_price', 'asc');
    }

    // Helper methods
    public function getEstimatedDeliveryDate(): \Carbon\Carbon
    {
        return now()->addDays($this->lead_time_days);
    }

    public function isCurrentlyAvailable(): bool
    {
        if (! $this->is_available) {
            return false;
        }

        if ($this->availability_date && $this->availability_date->isFuture()) {
            return false;
        }

        return true;
    }
}
