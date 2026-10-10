<?php

namespace App\Models\Amazon;

use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Team;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmazonListing extends Model
{
    use LogsAudit, SoftDeletes;

    protected ?string $auditCategory = 'marketplace';

    protected ?string $auditActionPrefix = 'listing.amazon';

    protected array $auditExclude = ['last_synced_at', 'last_inventory_update_at'];

    protected $table = 'amazon_listings';

    protected $fillable = [
        'product_id',
        'integration_credential_id',
        'team_id',
        'asin',
        'fnsku',
        'amazon_sku',
        'amazon_product_type',
        'asin_match_source',
        'asin_match_confidence',
        'fulfillment_channel',
        'status',
        'amazon_status',
        'suppressed_reason',
        'listed_price',
        'minimum_price',
        'maximum_price',
        'buy_box_price',
        'currency',
        'inventory_quantity',
        'fba_quantity',
        'product_data_hash',
        'last_sync_payload',
        'last_amazon_response',
        'last_feed_id',
        'last_feed_status',
        'last_synced_at',
        'sync_direction_lock',
        'last_error_message',
        'last_error_code',
        'last_error_at',
        'consecutive_errors',
    ];

    protected $casts = [
        'asin_match_confidence' => 'decimal:2',
        'listed_price' => 'decimal:2',
        'minimum_price' => 'decimal:2',
        'maximum_price' => 'decimal:2',
        'buy_box_price' => 'decimal:2',
        'inventory_quantity' => 'integer',
        'fba_quantity' => 'integer',
        'consecutive_errors' => 'integer',
        'last_synced_at' => 'datetime',
        'last_error_at' => 'datetime',
        'last_sync_payload' => 'array',
        'last_amazon_response' => 'array',
    ];

    const STATUS_PENDING = 'pending';

    const STATUS_CREATED = 'created';

    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    const STATUS_INCOMPLETE = 'incomplete';

    const STATUS_ERROR = 'error';

    const STATUS_DELETED = 'deleted';

    const FULFILLMENT_FBM = 'FBM';

    const FULFILLMENT_FBA = 'FBA';

    const MATCH_SOURCE_GTIN = 'gtin';

    const MATCH_SOURCE_SEARCH = 'search';

    const MATCH_SOURCE_MANUAL = 'manual';

    const MATCH_SOURCE_CREATED = 'created';

    // Relationships

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
        return $this->hasMany(AmazonSyncLog::class)->orderBy('created_at', 'desc');
    }

    // Scopes

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
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeWithErrors(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ERROR);
    }

    public function scopeNeedsSync(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_CREATED,
        ]);
    }

    public function scopeRetryable(Builder $query, int $maxErrors = 5): Builder
    {
        return $query->where('status', self::STATUS_ERROR)
            ->where('consecutive_errors', '<', $maxErrors);
    }

    public function scopeStale(Builder $query, int $hours = 24): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) use ($hours) {
                $q->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', now()->subHours($hours));
            });
    }

    public function scopeFbm(Builder $query): Builder
    {
        return $query->where('fulfillment_channel', self::FULFILLMENT_FBM);
    }

    public function scopeFba(Builder $query): Builder
    {
        return $query->where('fulfillment_channel', self::FULFILLMENT_FBA);
    }

    // Status helpers

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
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
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
        ]);
    }

    public function isFba(): bool
    {
        return $this->fulfillment_channel === self::FULFILLMENT_FBA;
    }

    public function isFbm(): bool
    {
        return $this->fulfillment_channel === self::FULFILLMENT_FBM;
    }

    public function updateStatus(string $status, ?string $errorMessage = null, ?string $errorCode = null): self
    {
        $data = ['status' => $status];

        if ($errorMessage || $errorCode) {
            $data['last_error_message'] = $errorMessage;
            $data['last_error_code'] = $errorCode;
            $data['last_error_at'] = now();
            $data['consecutive_errors'] = $this->consecutive_errors + 1;
        } else {
            $data['consecutive_errors'] = 0;
        }

        $this->update($data);

        return $this;
    }

    public function markSynced(): self
    {
        $this->update([
            'last_synced_at' => now(),
            'consecutive_errors' => 0,
        ]);

        return $this;
    }

    public function recordError(string $message, ?string $code = null, ?array $response = null): self
    {
        $this->update([
            'status' => self::STATUS_ERROR,
            'last_error_message' => $message,
            'last_error_code' => $code,
            'last_error_at' => now(),
            'consecutive_errors' => $this->consecutive_errors + 1,
            'last_amazon_response' => $response,
        ]);

        return $this;
    }

    public function generateProductHash(): string
    {
        $product = $this->product;

        $data = [
            'name' => $product->name,
            'description' => $product->description,
            'sku' => $product->sku,
            'selling_price' => (string) $product->selling_price,
            'stock_quantity' => $product->stock_quantity,
            'condition' => $product->condition,
            'weight' => (string) $product->weight,
            'length' => (string) $product->length,
            'width' => (string) $product->width,
            'height' => (string) $product->height,
            'gallery_images' => json_encode($product->gallery_images ?? []),
        ];

        return md5(json_encode($data));
    }

    public function hasProductChanged(): bool
    {
        if (! $this->product_data_hash) {
            return true;
        }

        return $this->product_data_hash !== $this->generateProductHash();
    }

    public function getAmazonUrlAttribute(): ?string
    {
        if (! $this->asin) {
            return null;
        }

        $config = $this->integrationCredential?->config;
        $marketplaceId = $config['amazon_marketplace_id'] ?? 'ATVPDKIKX0DER';

        // Default to US marketplace
        $domain = match ($marketplaceId) {
            'A2EUQ1WTGCTBG2' => 'amazon.ca',
            'A1AM78C64UM0Y8' => 'amazon.com.mx',
            'A1F83G8C2ARO7P' => 'amazon.co.uk',
            'A1PA6795UKMFR9' => 'amazon.de',
            default => 'amazon.com',
        };

        return "https://www.{$domain}/dp/{$this->asin}";
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CREATED => 'Created',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_INCOMPLETE => 'Incomplete',
            self::STATUS_ERROR => 'Error',
            self::STATUS_DELETED => 'Deleted',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_CREATED => 'blue',
            self::STATUS_ACTIVE => 'green',
            self::STATUS_INACTIVE => 'gray',
            self::STATUS_INCOMPLETE => 'orange',
            self::STATUS_ERROR => 'red',
            self::STATUS_DELETED => 'gray',
            default => 'gray',
        };
    }
}
