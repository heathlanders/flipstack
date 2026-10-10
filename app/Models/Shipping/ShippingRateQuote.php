<?php

namespace App\Models\Shipping;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRateQuote extends Model
{
    protected $table = 'shipping_rate_quotes';

    protected $fillable = [
        'organization_id',
        'queue_item_id',
        'product_id',
        'carrier',
        'service',
        'service_code',
        'rate',
        'currency',
        'estimated_days',
        'is_guaranteed',
        'weight_lbs',
        'length_inches',
        'width_inches',
        'height_inches',
        'from_postal_code',
        'to_postal_code',
        'insurance_included',
        'insurance_cost',
        'insured_value',
        'confirmation_type',
        'additional_item_cost',
        'provider',
        'expires_at',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'estimated_days' => 'integer',
        'is_guaranteed' => 'boolean',
        'insurance_included' => 'boolean',
        'insurance_cost' => 'decimal:2',
        'insured_value' => 'decimal:2',
        'additional_item_cost' => 'decimal:2',
        'weight_lbs' => 'decimal:2',
        'length_inches' => 'decimal:2',
        'width_inches' => 'decimal:2',
        'height_inches' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    const PROVIDER_EASYPOST = 'easypost';

    const PROVIDER_SHIPSTATION = 'shipstation';

    const PROVIDER_SHIPPINGEASY = 'shippingeasy';

    const CONFIRMATION_NONE = 'none';

    const CONFIRMATION_SIGNATURE = 'signature';

    const CONFIRMATION_ADULT = 'adult_signature';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function queueItem(): BelongsTo
    {
        return $this->belongsTo(ProductIntakeQueue::class, 'queue_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeForQueueItem(Builder $query, int $queueItemId): Builder
    {
        return $query->where('queue_item_id', $queueItemId);
    }

    public function scopeForProduct(Builder $query, string $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeCheapest(Builder $query): Builder
    {
        return $query->orderBy('rate', 'asc');
    }

    public function scopeFastest(Builder $query): Builder
    {
        return $query->orderBy('estimated_days', 'asc')->orderBy('rate', 'asc');
    }

    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    public function scopeByCarrier(Builder $query, string $carrier): Builder
    {
        return $query->where('carrier', $carrier);
    }

    public function isValid(): bool
    {
        return $this->expires_at && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return ! $this->isValid();
    }

    public function getTotalRateAttribute(): float
    {
        return (float) $this->rate + (float) ($this->insurance_cost ?? 0);
    }

    public function getFormattedRateAttribute(): string
    {
        return '$'.number_format($this->rate, 2);
    }

    public function getFormattedTotalRateAttribute(): string
    {
        return '$'.number_format($this->total_rate, 2);
    }

    public function getFormattedAdditionalItemCostAttribute(): ?string
    {
        if ($this->additional_item_cost === null) {
            return null;
        }

        return '$'.number_format($this->additional_item_cost, 2);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->carrier.' - '.$this->service;
    }

    public function getEstimatedDeliveryAttribute(): ?string
    {
        if (! $this->estimated_days) {
            return null;
        }

        if ($this->estimated_days === 1) {
            return '1 business day';
        }

        return $this->estimated_days.' business days';
    }

    public function getDimensionsAttribute(): array
    {
        return [
            'weight' => $this->weight_lbs,
            'length' => $this->length_inches,
            'width' => $this->width_inches,
            'height' => $this->height_inches,
        ];
    }

    public static function getProviders(): array
    {
        return [
            self::PROVIDER_EASYPOST => 'EasyPost',
            self::PROVIDER_SHIPSTATION => 'ShipStation',
            self::PROVIDER_SHIPPINGEASY => 'ShippingEasy',
        ];
    }

    public static function cleanupExpired(): int
    {
        return static::expired()->delete();
    }
}
