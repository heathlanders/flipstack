<?php

namespace App\Models\Inventory;

use App\Models\Organization\Organization;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseLocation extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'location_code',
        'description',
        'aisle',
        'shelf',
        'bin',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByAisle($query, string $aisle)
    {
        return $query->where('aisle', $aisle);
    }

    // Accessors
    public function getFormattedLocationAttribute(): string
    {
        $parts = array_filter([$this->aisle, $this->shelf, $this->bin]);

        return implode('-', $parts);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->description) {
            return "{$this->location_code} - {$this->description}";
        }

        return $this->location_code;
    }
}
