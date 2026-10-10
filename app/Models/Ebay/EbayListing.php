<?php

namespace App\Models\Ebay;

use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Team;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EbayListing extends Model
{
    use LogsAudit, SoftDeletes;

    protected ?string $auditCategory = 'marketplace';

    protected ?string $auditActionPrefix = 'listing.ebay';

    protected array $auditExclude = ['last_synced_at', 'last_inventory_update_at', 'aspect_audit'];

    protected $table = 'ebay_listings';

    protected $fillable = [
        'product_id',
        'integration_credential_id',
        'team_id',
        'ebay_sku',
        'ebay_offer_id',
        'ebay_listing_id',
        'status',
        'ebay_category_id',
        'ebay_category_name',
        'listed_price',
        'currency',
        'listed_quantity',
        'available_quantity',
        'sold_quantity',
        'listing_format',
        'condition',
        'condition_description',
        'ebay_listing_url',
        'last_synced_at',
        'last_inventory_update_at',
        'last_price_update_at',
        'listing_start_date',
        'listing_end_date',
        'last_error_message',
        'last_error_code',
        'last_error_at',
        'consecutive_errors',
        'product_data_hash',
        'last_sync_payload',
        'last_ebay_response',
        'aspect_audit',
    ];

    protected $casts = [
        'listed_price' => 'decimal:2',
        'listed_quantity' => 'integer',
        'available_quantity' => 'integer',
        'sold_quantity' => 'integer',
        'consecutive_errors' => 'integer',
        'last_synced_at' => 'datetime',
        'last_inventory_update_at' => 'datetime',
        'last_price_update_at' => 'datetime',
        'listing_start_date' => 'datetime',
        'listing_end_date' => 'datetime',
        'last_error_at' => 'datetime',
        'last_sync_payload' => 'array',
        'last_ebay_response' => 'array',
        'aspect_audit' => 'array',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';

    const STATUS_INVENTORY_CREATED = 'inventory_created';

    const STATUS_OFFER_CREATED = 'offer_created';

    const STATUS_PUBLISHED = 'published';

    const STATUS_ENDED = 'ended';

    const STATUS_OUT_OF_STOCK = 'out_of_stock';

    const STATUS_ERROR = 'error';

    const STATUS_DELETED = 'deleted';

    /**
     * Relationships
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function integrationCredential(): BelongsTo
    {
        return $this->belongsTo(IntegrationCredential::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(EbaySyncLog::class)->orderBy('created_at', 'desc');
    }

    /**
     * Scopes
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForCredential(Builder $query, int $credentialId): Builder
    {
        return $query->where('integration_credential_id', $credentialId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeWithErrors(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ERROR);
    }

    public function scopeNeedsSync(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_INVENTORY_CREATED,
            self::STATUS_OFFER_CREATED,
        ]);
    }

    public function scopeRetryable(Builder $query, int $maxErrors = 5): Builder
    {
        return $query->where('status', self::STATUS_ERROR)
            ->where('consecutive_errors', '<', $maxErrors);
    }

    public function scopeStale(Builder $query, int $hours = 24): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where(function ($q) use ($hours) {
                $q->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', now()->subHours($hours));
            });
    }

    /**
     * Status helpers
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function hasError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    public function canRetry(): bool
    {
        return $this->hasError() && $this->consecutive_errors < 5;
    }

    public function isLive(): bool
    {
        return in_array($this->status, [
            self::STATUS_PUBLISHED,
            self::STATUS_OUT_OF_STOCK,
        ]);
    }

    /**
     * Update status with timestamp
     */
    public function updateStatus(string $status, ?string $errorMessage = null, ?string $errorCode = null): self
    {
        $data = ['status' => $status];

        if ($errorMessage || $errorCode) {
            $data['last_error_message'] = $errorMessage;
            $data['last_error_code'] = $errorCode;
            $data['last_error_at'] = now();
            $data['consecutive_errors'] = $this->consecutive_errors + 1;
        } else {
            // Clear errors on success
            $data['consecutive_errors'] = 0;
        }

        $this->update($data);

        return $this;
    }

    /**
     * Mark as synced
     */
    public function markSynced(): self
    {
        $this->update([
            'last_synced_at' => now(),
            'consecutive_errors' => 0,
        ]);

        return $this;
    }

    /**
     * Record an error
     */
    public function recordError(string $message, ?string $code = null, ?array $response = null): self
    {
        $this->update([
            'status' => self::STATUS_ERROR,
            'last_error_message' => $message,
            'last_error_code' => $code,
            'last_error_at' => now(),
            'consecutive_errors' => $this->consecutive_errors + 1,
            'last_ebay_response' => $response,
        ]);

        return $this;
    }

    /**
     * Generate a hash of the product data for change detection
     */
    public function generateProductHash(): string
    {
        $product = $this->product;

        // Quantity is excluded: inventory is managed via dedicated quantity sync, not listing updates
        $data = [
            'name' => $product->name,
            'description' => $product->description,
            'sku' => $product->sku,
            'selling_price' => (string) $product->selling_price,
            'condition' => $product->condition,
            'condition_description' => $product->condition_description,
            'weight' => (string) $product->weight,
            'length' => (string) $product->length,
            'width' => (string) $product->width,
            'height' => (string) $product->height,
            'gallery_images' => json_encode($product->gallery_images ?? []),
        ];

        return md5(json_encode($data));
    }

    /**
     * Check if product data has changed since last sync
     */
    public function hasProductChanged(): bool
    {
        if (! $this->product_data_hash) {
            return true;
        }

        return $this->product_data_hash !== $this->generateProductHash();
    }

    /**
     * Get the eBay listing URL
     */
    public function getEbayUrlAttribute(): ?string
    {
        if ($this->ebay_listing_url) {
            return $this->ebay_listing_url;
        }

        if ($this->ebay_listing_id) {
            $baseUrl = $this->integrationCredential?->environment === 'sandbox'
                ? 'https://sandbox.ebay.com/itm/'
                : 'https://www.ebay.com/itm/';

            return $baseUrl.$this->ebay_listing_id;
        }

        return null;
    }

    /**
     * Get all valid statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_INVENTORY_CREATED => 'Inventory Created',
            self::STATUS_OFFER_CREATED => 'Offer Created',
            self::STATUS_PUBLISHED => 'Published',
            self::STATUS_ENDED => 'Ended',
            self::STATUS_OUT_OF_STOCK => 'Out of Stock',
            self::STATUS_ERROR => 'Error',
            self::STATUS_DELETED => 'Deleted',
        ];
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_INVENTORY_CREATED => 'blue',
            self::STATUS_OFFER_CREATED => 'indigo',
            self::STATUS_PUBLISHED => 'green',
            self::STATUS_ENDED => 'gray',
            self::STATUS_OUT_OF_STOCK => 'orange',
            self::STATUS_ERROR => 'red',
            self::STATUS_DELETED => 'gray',
            default => 'gray',
        };
    }
}
