<?php

namespace App\Models\Inventory;

use App\Models\Catalog\Product;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartOutTemplateItem extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'template_id',
        'component_product_id',
        'name',
        'expected_quantity',
        'cost_allocation_pct',
        'sort_order',
        'is_optional',
        'default_condition',
        'part_number',
        'manufacturer',
        'category',
        'suggested_selling_price',
        'market_price_updated_at',
    ];

    protected $casts = [
        'expected_quantity' => 'integer',
        'cost_allocation_pct' => 'decimal:2',
        'sort_order' => 'integer',
        'is_optional' => 'boolean',
        'suggested_selling_price' => 'decimal:2',
        'market_price_updated_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PartOutTemplate::class, 'template_id');
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
