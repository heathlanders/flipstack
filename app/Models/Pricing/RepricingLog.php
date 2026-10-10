<?php

namespace App\Models\Pricing;

use App\Models\Catalog\Product;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepricingLog extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'repricing_rule_id', 'product_id', 'listing_type', 'listing_id',
        'old_price', 'new_price', 'competitor_price', 'reason', 'applied_at',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'competitor_price' => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RepricingRule::class, 'repricing_rule_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getPriceChangeAttribute(): float
    {
        return round($this->new_price - $this->old_price, 2);
    }

    public function getPriceChangePercentAttribute(): float
    {
        return $this->old_price > 0 ? round((($this->new_price - $this->old_price) / $this->old_price) * 100, 2) : 0;
    }
}
