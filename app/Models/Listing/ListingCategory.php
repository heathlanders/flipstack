<?php

namespace App\Models\Listing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingCategory extends Model
{
    protected $fillable = [
        'listing_id',
        'platform',
        'category_id',
        'category_name',
        'category_path',
        'is_primary',
        'sort_order',
        'required_specifics',
        'recommended_specifics',
        'category_fee_percentage',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
        'required_specifics' => 'array',
        'recommended_specifics' => 'array',
        'category_fee_percentage' => 'decimal:2',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ProductListing::class, 'listing_id');
    }
}
