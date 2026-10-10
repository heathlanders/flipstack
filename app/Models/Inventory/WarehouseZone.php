<?php

namespace App\Models\Inventory;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseZone extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, LogsAudit;

    protected ?string $auditCategory = 'inventory';

    protected ?string $auditActionPrefix = 'warehouse_zone';

    protected $fillable = [
        'organization_id', 'location_id', 'name', 'code', 'type', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public const TYPE_RECEIVING = 'receiving';

    public const TYPE_STORAGE = 'storage';

    public const TYPE_PICKING = 'picking';

    public const TYPE_SHIPPING = 'shipping';

    public const TYPE_RETURNS = 'returns';

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function bins(): HasMany
    {
        return $this->hasMany(WarehouseBin::class, 'zone_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForLocation($query, string $locationId)
    {
        return $query->where('location_id', $locationId);
    }
}
