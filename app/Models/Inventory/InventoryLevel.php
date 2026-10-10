<?php

namespace App\Models\Inventory;

use App\Models\Catalog\Product;
use App\Models\Organization\Organization;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLevel extends Model
{
    use BelongsToOrganization, HasUuid, LogsAudit;

    protected ?string $auditCategory = 'inventory';

    protected ?string $auditActionPrefix = 'inventory.level';

    protected array $auditExclude = ['last_counted_at'];

    protected $fillable = [
        'organization_id',
        'product_id',
        'product_variation_id',
        'location_id',
        'quantity_on_hand',
        'quantity_reserved',
        'reorder_point',
        'reorder_quantity',
        'max_stock_level',
        'average_cost',
        'last_movement_at',
        'last_count_at',
        'bin_id',
    ];

    protected $casts = [
        'quantity_on_hand' => 'integer',
        'quantity_reserved' => 'integer',
        'reorder_point' => 'integer',
        'reorder_quantity' => 'integer',
        'max_stock_level' => 'integer',
        'average_cost' => 'decimal:2',
        'last_movement_at' => 'datetime',
        'last_count_at' => 'datetime',
    ];

    protected $appends = ['quantity_available', 'total_cost'];

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'bin_id');
    }

    // Computed attributes
    public function getQuantityAvailableAttribute(): int
    {
        return $this->quantity_on_hand - $this->quantity_reserved;
    }

    public function getTotalCostAttribute(): float
    {
        return $this->quantity_on_hand * $this->average_cost;
    }

    // Scopes
    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity_on_hand', '<=', 'reorder_point');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('quantity_on_hand', 0);
    }

    public function scopeOverStock($query)
    {
        return $query->whereColumn('quantity_on_hand', '>', 'max_stock_level')
            ->whereNotNull('max_stock_level');
    }

    // Helper methods
    public function isLowStock(): bool
    {
        return $this->quantity_on_hand <= $this->reorder_point;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity_on_hand <= 0;
    }

    public function isOverStock(): bool
    {
        return $this->max_stock_level && $this->quantity_on_hand > $this->max_stock_level;
    }

    public function canReserve(int $quantity): bool
    {
        return $this->quantity_available >= $quantity;
    }

    public function reserve(int $quantity): bool
    {
        if (! $this->canReserve($quantity)) {
            return false;
        }

        $this->increment('quantity_reserved', $quantity);

        return true;
    }

    public function unreserve(int $quantity): bool
    {
        if ($this->quantity_reserved < $quantity) {
            return false;
        }

        $this->decrement('quantity_reserved', $quantity);

        return true;
    }
}
