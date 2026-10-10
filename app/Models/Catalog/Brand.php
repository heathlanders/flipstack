<?php

namespace App\Models\Catalog;

use App\Models\Organization\Organization;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasUuids, LogsAudit;

    protected ?string $auditCategory = 'product';

    protected ?string $auditActionPrefix = 'brand';

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'logo',
        'website',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function activeProducts(): HasMany
    {
        return $this->hasMany(Product::class)->active();
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization(Builder $query, $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
