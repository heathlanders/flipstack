<?php

namespace App\Models\Shopify;

use App\Models\Catalog\Category;
use App\Models\Integration\IntegrationCredential;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyCollectionMapping extends Model
{
    protected $table = 'shopify_collection_mappings';

    protected $fillable = [
        'integration_credential_id',
        'category_id',
        'shopify_collection_id',
        'shopify_collection_title',
        'collection_type',
        'is_default',
    ];

    protected $casts = [
        'shopify_collection_id' => 'integer',
        'is_default' => 'boolean',
    ];

    public function integrationCredential(): BelongsTo
    {
        return $this->belongsTo(IntegrationCredential::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeForCredential(Builder $query, int $credentialId): Builder
    {
        return $query->where('integration_credential_id', $credentialId);
    }

    public function scopeForCategory(Builder $query, string $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeDefaults(Builder $query): Builder
    {
        return $query->whereNull('category_id')->where('is_default', true);
    }
}
