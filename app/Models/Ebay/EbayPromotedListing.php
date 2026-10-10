<?php

namespace App\Models\Ebay;

use App\Models\Catalog\Product;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbayPromotedListing extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'ebay_promoted_listings';

    protected $fillable = [
        'campaign_id',
        'ebay_listing_id',
        'product_id',
        'ad_rate_percentage',
        'status',
        'impressions',
        'clicks',
        'sales_count',
        'sales_amount',
        'ad_fees',
        'roas',
        'last_synced_at',
    ];

    protected $casts = [
        'ad_rate_percentage' => 'decimal:2',
        'sales_amount' => 'decimal:2',
        'ad_fees' => 'decimal:2',
        'roas' => 'decimal:2',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'sales_count' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EbayPromotedCampaign::class, 'campaign_id');
    }

    public function ebayListing(): BelongsTo
    {
        return $this->belongsTo(EbayListing::class, 'ebay_listing_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
