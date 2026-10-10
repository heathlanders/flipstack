<?php

namespace App\Models\Listing;

use App\Models\Catalog\Product;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductListing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'organization_id',
        'platform',
        'marketplace',
        'listing_id',
        'sku',
        'status',
        'published_at',
        'ended_at',
        'title',
        'subtitle',
        'description',
        'features',
        'price',
        'compare_at_price',
        'cost',
        'currency',
        'quantity',
        'auto_renew',
        'duration_days',
        'condition',
        'condition_description',
        'shipping_weight_oz',
        'package_length_in',
        'package_width_in',
        'package_height_in',
        'handling_time_days',
        'shipping_services',
        'shipping_cost',
        'free_shipping',
        'returns_accepted',
        'return_period_days',
        'return_shipping_paid_by',
        'payment_methods',
        'tags',
        'promoted',
        'promotion_rate',
        'platform_data',
        'last_synced_at',
        'sync_errors',
        'auto_sync',
        'template_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'quantity' => 'integer',
        'auto_renew' => 'boolean',
        'duration_days' => 'integer',
        'shipping_weight_oz' => 'decimal:2',
        'package_length_in' => 'decimal:2',
        'package_width_in' => 'decimal:2',
        'package_height_in' => 'decimal:2',
        'handling_time_days' => 'integer',
        'shipping_services' => 'array',
        'shipping_cost' => 'decimal:2',
        'free_shipping' => 'boolean',
        'returns_accepted' => 'boolean',
        'return_period_days' => 'integer',
        'payment_methods' => 'array',
        'tags' => 'array',
        'promoted' => 'boolean',
        'promotion_rate' => 'decimal:2',
        'platform_data' => 'array',
        'last_synced_at' => 'datetime',
        'sync_errors' => 'array',
        'auto_sync' => 'boolean',
        'published_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_SOLD = 'sold';

    public const STATUS_ERROR = 'error';

    // Scopes

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeWithErrors($query)
    {
        return $query->where('status', self::STATUS_ERROR);
    }

    // Relationships

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ListingTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ListingCategory::class, 'listing_id');
    }

    public function itemSpecifics(): HasMany
    {
        return $this->hasMany(ListingItemSpecific::class, 'listing_id');
    }

    // Accessors

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_ENDED => 'Ended',
            self::STATUS_SOLD => 'Sold',
            self::STATUS_ERROR => 'Error',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'green',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_DRAFT => 'gray',
            self::STATUS_ENDED, self::STATUS_SOLD => 'blue',
            self::STATUS_ERROR => 'red',
            default => 'gray',
        };
    }

    // Methods

    public function isLive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }
}
