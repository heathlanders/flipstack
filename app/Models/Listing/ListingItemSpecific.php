<?php

namespace App\Models\Listing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingItemSpecific extends Model
{
    protected $fillable = [
        'listing_id',
        'name',
        'value',
        'source',
        'required',
        'sort_order',
        'platform_field_id',
        'allowed_values',
    ];

    protected $casts = [
        'required' => 'boolean',
        'sort_order' => 'integer',
        'allowed_values' => 'array',
    ];

    // Source constants
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_AI = 'ai';

    public const SOURCE_TEMPLATE = 'template';

    public const SOURCE_API = 'api';

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ProductListing::class, 'listing_id');
    }
}
