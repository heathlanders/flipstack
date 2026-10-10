<?php

namespace App\Models\Listing;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ListingTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'platform',
        'default_item_specifics',
        'default_categories',
        'description_template',
        'shipping_template',
        'return_policy_template',
        'payment_template',
        'pricing_strategy',
        'markup_percentage',
        'fixed_markup_amount',
        'times_used',
        'last_used_at',
        'created_by',
    ];

    protected $casts = [
        'default_item_specifics' => 'array',
        'default_categories' => 'array',
        'shipping_template' => 'array',
        'return_policy_template' => 'array',
        'payment_template' => 'array',
        'markup_percentage' => 'decimal:2',
        'fixed_markup_amount' => 'decimal:2',
        'times_used' => 'integer',
        'last_used_at' => 'datetime',
    ];

    // Platform constants
    public const PLATFORM_EBAY = 'ebay';

    public const PLATFORM_AMAZON = 'amazon';

    public const PLATFORM_ALL = 'all';

    // Pricing strategy constants
    public const STRATEGY_FIXED_MARKUP = 'fixed_markup';

    public const STRATEGY_PERCENTAGE = 'percentage';

    public const STRATEGY_COMPETITIVE = 'competitive';

    // Scopes

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where(function ($q) use ($platform) {
            $q->where('platform', $platform)
                ->orWhere('platform', self::PLATFORM_ALL);
        });
    }

    // Relationships

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function productListings(): HasMany
    {
        return $this->hasMany(ProductListing::class, 'template_id');
    }

    // Methods

    public function incrementUsage(): void
    {
        $this->increment('times_used');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Calculate price based on this template's pricing strategy.
     */
    public function calculatePrice(float $costPrice): ?float
    {
        if (! $this->pricing_strategy) {
            return null;
        }

        return match ($this->pricing_strategy) {
            self::STRATEGY_FIXED_MARKUP => $costPrice + ($this->fixed_markup_amount ?? 0),
            self::STRATEGY_PERCENTAGE => $costPrice * (1 + ($this->markup_percentage ?? 0) / 100),
            default => null,
        };
    }

    // Accessors

    public function getPricingStrategyLabelAttribute(): ?string
    {
        return match ($this->pricing_strategy) {
            self::STRATEGY_FIXED_MARKUP => 'Fixed Markup',
            self::STRATEGY_PERCENTAGE => 'Percentage Markup',
            self::STRATEGY_COMPETITIVE => 'Competitive',
            default => null,
        };
    }

    public function getPlatformLabelAttribute(): string
    {
        return match ($this->platform) {
            self::PLATFORM_EBAY => 'eBay',
            self::PLATFORM_AMAZON => 'Amazon',
            self::PLATFORM_ALL => 'All Platforms',
            default => ucfirst($this->platform),
        };
    }
}
