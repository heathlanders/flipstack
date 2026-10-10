<?php

namespace App\Models\Catalog;

use App\Models\Organization\Organization;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, HasUuids, LogsAudit;

    protected ?string $auditCategory = 'product';

    protected ?string $auditActionPrefix = 'category';

    protected $fillable = [
        'organization_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'color',
        'icon',
        'path',
        'lft',
        'rgt',
        'depth',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'depth' => 'integer',
        'lft' => 'integer',
        'rgt' => 'integer',
    ];

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
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

    public function scopeRootCategories(Builder $query): Builder
    {
        return $query->whereNull('parent_id')->orWhere('depth', 0);
    }

    public function scopeForOrganization(Builder $query, $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    // Methods
    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }
}
