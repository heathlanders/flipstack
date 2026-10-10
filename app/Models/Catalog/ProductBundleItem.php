<?php

namespace App\Models\Catalog;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBundleItem extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'product_bundle_items';

    protected $fillable = [
        'bundle_id',
        'component_product_id',
        'quantity',
        'sort_order',
        'is_required',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'sort_order' => 'integer',
        'is_required' => 'boolean',
    ];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'bundle_id');
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
