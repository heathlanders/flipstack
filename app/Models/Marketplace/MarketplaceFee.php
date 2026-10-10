<?php

namespace App\Models\Marketplace;

use App\Models\Fulfillment\Order;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MarketplaceFee extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $fillable = [
        'organization_id',
        'order_id',
        'order_item_id',
        'listing_type',
        'listing_id',
        'marketplace',
        'fee_type',
        'amount',
        'currency',
        'description',
        'marketplace_fee_id',
        'fee_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_date' => 'date',
    ];

    // Fee type constants
    public const TYPE_FINAL_VALUE = 'final_value';

    public const TYPE_INSERTION = 'insertion';

    public const TYPE_PROMOTED_LISTING = 'promoted_listing';

    public const TYPE_PAYMENT_PROCESSING = 'payment_processing';

    public const TYPE_SHIPPING_LABEL = 'shipping_label';

    public const TYPE_SUBSCRIPTION = 'subscription';

    public const TYPE_REFUND_CREDIT = 'refund_credit';

    public const TYPE_REFERRAL = 'referral';

    public const TYPE_FBA = 'fba';

    public const TYPE_CLOSING = 'closing';

    public const TYPE_OTHER = 'other';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function listing(): MorphTo
    {
        return $this->morphTo('listing', 'listing_type', 'listing_id');
    }

    // Scopes

    public function scopeForMarketplace($query, string $marketplace)
    {
        return $query->where('marketplace', $marketplace);
    }

    public function scopeForOrder($query, string $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('fee_type', $type);
    }

    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('fee_date', [$start, $end]);
    }
}
