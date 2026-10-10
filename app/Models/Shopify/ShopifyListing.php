<?php

namespace App\Models\Shopify;

use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Team;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopifyListing extends Model
{
    use LogsAudit, SoftDeletes;

    protected ?string $auditCategory = 'marketplace';

    protected ?string $auditActionPrefix = 'listing.shopify';

    protected array $auditExclude = ['last_synced_at', 'last_inventory_update_at'];

    protected $table = 'shopify_listings';

    protected $fillable = [
        'product_id',
        'integration_credential_id',
        'team_id',
        'shopify_product_id',
        'shopify_variant_id',
        'shopify_inventory_item_id',
        'shopify_sku',
        'shopify_handle',
        'status',
        'listed_price',
        'compare_at_price',
        'currency',
        'inventory_quantity',
        'product_data_hash',
        'last_sync_payload',
        'last_shopify_response',
        'last_synced_at',
        'sync_direction_lock',
        'last_error_message',
        'last_error_code',
        'last_error_at',
        'consecutive_errors',
    ];

    protected $casts = [
        'shopify_product_id' => 'integer',
        'shopify_variant_id' => 'integer',
        'shopify_inventory_item_id' => 'integer',
        'listed_price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'inventory_quantity' => 'integer',
        'consecutive_errors' => 'integer',
        'last_synced_at' => 'datetime',
        'last_error_at' => 'datetime',
        'last_sync_payload' => 'array',
        'last_shopify_response' => 'array',
    ];

    const STATUS_PENDING = 'pending';

    const STATUS_CREATED = 'created';

    const STATUS_ACTIVE = 'active';

    const STATUS_DRAFT = 'draft';

    const STATUS_ARCHIVED = 'archived';

    const STATUS_ERROR = 'error';

    const STATUS_DELETED = 'deleted';

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
        return $this->hasMany(ShopifySyncLog::class)->orderBy('created_at', 'desc');
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
            self::STATUS_DRAFT,
        ]);
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
            'last_shopify_response' => $response,
        ]);

        return $this;
    }

    public function generateProductHash(): string
    {
        $product = $this->product;

        // Quantity is excluded: inventory is managed via setInventoryLevel, not product updates
        $data = [
            'name' => $product->name,
            'description' => $product->description,
            'sku' => $product->sku,
            'selling_price' => (string) $product->selling_price,
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

    public function getShopifyUrlAttribute(): ?string
    {
        if (! $this->shopify_product_id) {
            return null;
        }

        $config = $this->integrationCredential?->config;
        $shopDomain = $config['shop_domain'] ?? null;

        if (! $shopDomain) {
            return null;
        }

        return "https://{$shopDomain}/admin/products/{$this->shopify_product_id}";
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CREATED => 'Created',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ARCHIVED => 'Archived',
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
            self::STATUS_DRAFT => 'gray',
            self::STATUS_ARCHIVED => 'gray',
            self::STATUS_ERROR => 'red',
            self::STATUS_DELETED => 'gray',
            default => 'gray',
        };
    }
}
