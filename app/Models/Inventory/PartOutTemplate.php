<?php

namespace App\Models\Inventory;

use App\Models\Catalog\Product;
use App\Models\Organization\Organization;
use App\Models\User\User;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartOutTemplate extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'source_product_id',
        'cost_allocation_method',
        'is_active',
        'times_used',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'times_used' => 'integer',
    ];

    public const METHOD_MARKET_VALUE = 'market_value';

    public const METHOD_PERCENTAGE = 'percentage';

    public const METHOD_EVEN = 'even';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sourceProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'source_product_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PartOutTemplateItem::class, 'template_id')->orderBy('sort_order');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(PartOutWorkOrder::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForProduct($query, string $productId)
    {
        return $query->where('source_product_id', $productId);
    }
}
