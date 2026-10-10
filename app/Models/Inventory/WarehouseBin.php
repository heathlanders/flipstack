<?php

namespace App\Models\Inventory;

use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseBin extends Model
{
    use HasFactory, HasUuid, LogsAudit;

    protected ?string $auditCategory = 'inventory';

    protected ?string $auditActionPrefix = 'warehouse_bin';

    protected $fillable = [
        'zone_id', 'code', 'label', 'aisle', 'rack', 'shelf',
        'bin_position', 'max_capacity', 'current_count', 'is_active', 'barcode',
    ];

    protected $casts = [
        'max_capacity' => 'integer',
        'current_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class, 'zone_id');
    }

    public function inventoryLevels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class, 'bin_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByBarcode($query, string $barcode)
    {
        return $query->where('barcode', $barcode);
    }

    public function isAtCapacity(): bool
    {
        return $this->max_capacity !== null && $this->current_count >= $this->max_capacity;
    }

    public function getFullCodeAttribute(): string
    {
        return $this->zone?->code.'-'.$this->code;
    }
}
