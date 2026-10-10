<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EbayCategory extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'parent_id',
        'level',
        'is_leaf',
        'category_path',
        'keywords',
        'item_count',
        'metadata',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_leaf' => 'boolean',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(EbayCategory::class, 'parent_id', 'category_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(EbayCategory::class, 'parent_id', 'category_id');
    }

    public function leafChildren(): HasMany
    {
        return $this->children()->where('is_leaf', true);
    }

    /**
     * Scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLeaf(Builder $query): Builder
    {
        return $query->where('is_leaf', true);
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeLevel(Builder $query, int $level): Builder
    {
        return $query->where('level', $level);
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query->orderBy('item_count', 'desc');
    }

    public function scopeByKeyword(Builder $query, string $keyword): Builder
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('name', 'LIKE', "%{$keyword}%")
                ->orWhere('keywords', 'LIKE', "%{$keyword}%")
                ->orWhere('category_path', 'LIKE', "%{$keyword}%");
        });
    }

    /**
     * Helper Methods
     */
    public function getFullPath(): string
    {
        return $this->category_path ?? $this->name;
    }

    public function getBreadcrumbs(): array
    {
        if (! $this->category_path) {
            return [$this->name];
        }

        return explode(' > ', $this->category_path);
    }

    public function hasChildren(): bool
    {
        return $this->children()->count() > 0;
    }

    public function getDepth(): int
    {
        return $this->level;
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isLeaf(): bool
    {
        return $this->is_leaf;
    }

    public function canListItems(): bool
    {
        return $this->is_leaf && $this->is_active;
    }

    /**
     * Get all ancestors (parent chain)
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $current = $this;

        while ($current->parent_id) {
            $parent = static::where('category_id', $current->parent_id)->first();
            if (! $parent) {
                break;
            }

            array_unshift($ancestors, $parent);
            $current = $parent;
        }

        return $ancestors;
    }

    /**
     * Get all descendants (children tree)
     */
    public function getDescendants(): array
    {
        $descendants = [];

        foreach ($this->children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->getDescendants());
        }

        return $descendants;
    }

    /**
     * Get category tree as array (for dropdowns, etc.)
     */
    public static function getTree(?string $parentId = null, int $maxDepth = 5): array
    {
        $categories = static::where('parent_id', $parentId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $tree = [];

        foreach ($categories as $category) {
            $node = [
                'id' => $category->category_id,
                'name' => $category->name,
                'full_path' => $category->getFullPath(),
                'is_leaf' => $category->is_leaf,
                'level' => $category->level,
                'item_count' => $category->item_count ?? 0,
            ];

            if (! $category->is_leaf && $category->level < $maxDepth) {
                $node['children'] = static::getTree($category->category_id, $maxDepth);
            }

            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * Search categories by text with ranking
     */
    public static function search(string $query, int $limit = 20): array
    {
        $results = static::active()
            ->leaf()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('keywords', 'LIKE', "%{$query}%")
                    ->orWhere('category_path', 'LIKE', "%{$query}%");
            })
            ->orderByRaw("
                CASE 
                    WHEN name LIKE '{$query}%' THEN 1
                    WHEN name LIKE '%{$query}%' THEN 2
                    WHEN category_path LIKE '%{$query}%' THEN 3
                    WHEN keywords LIKE '%{$query}%' THEN 4
                    ELSE 5
                END, item_count DESC
            ")
            ->limit($limit)
            ->get();

        return $results->map(function ($category) {
            return [
                'category_id' => $category->category_id,
                'name' => $category->name,
                'path' => $category->getFullPath(),
                'level' => $category->level,
                'item_count' => $category->item_count ?? 0,
                'can_list' => $category->canListItems(),
            ];
        })->toArray();
    }

    /**
     * Get popular categories for a specific brand/product type
     */
    public static function getPopularForBrand(string $brand, int $limit = 10): array
    {
        return static::active()
            ->leaf()
            ->byKeyword($brand)
            ->popular()
            ->limit($limit)
            ->get()
            ->map(function ($category) {
                return [
                    'category_id' => $category->category_id,
                    'name' => $category->name,
                    'path' => $category->getFullPath(),
                    'item_count' => $category->item_count ?? 0,
                ];
            })
            ->toArray();
    }

    /**
     * Get suggested categories based on similar products
     */
    public function getSimilarCategories(int $limit = 5): array
    {
        // Get categories at the same level with similar keywords
        $keywords = explode(' ', $this->keywords ?? '');

        if (empty($keywords)) {
            return [];
        }

        $query = static::active()
            ->leaf()
            ->where('category_id', '!=', $this->category_id)
            ->where('level', $this->level);

        foreach ($keywords as $keyword) {
            if (strlen($keyword) > 2) {
                $query->where('keywords', 'LIKE', "%{$keyword}%");
            }
        }

        return $query->popular()
            ->limit($limit)
            ->get()
            ->map(function ($category) {
                return [
                    'category_id' => $category->category_id,
                    'name' => $category->name,
                    'path' => $category->getFullPath(),
                    'item_count' => $category->item_count ?? 0,
                ];
            })
            ->toArray();
    }

    /**
     * Check if category needs sync (older than 7 days)
     */
    public static function needsSync(): bool
    {
        $lastSync = static::max('last_synced_at');

        if (! $lastSync) {
            return true;
        }

        return $lastSync < now()->subDays(7);
    }
}
