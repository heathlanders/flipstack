<?php

namespace App\Models;

use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceSyncRule extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'marketplace',
        'action',
        'priority',
        'conditions',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization(Builder $query, int $orgId): Builder
    {
        return $query->where('organization_id', $orgId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->whereIn('marketplace', [$platform, '*']);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority');
    }
}
