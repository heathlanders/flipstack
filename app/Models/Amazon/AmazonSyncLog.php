<?php

namespace App\Models\Amazon;

use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Team;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AmazonSyncLog extends Model
{
    protected $table = 'amazon_sync_logs';

    protected $fillable = [
        'integration_credential_id',
        'amazon_listing_id',
        'product_id',
        'team_id',
        'operation',
        'direction',
        'amazon_sku',
        'asin',
        'feed_id',
        'api_endpoint',
        'http_method',
        'request_payload',
        'http_status_code',
        'response_payload',
        'response_time_ms',
        'status',
        'error_code',
        'error_message',
        'error_details',
        'batch_id',
        'batch_sequence',
        'retry_count',
        'next_retry_at',
        'is_retryable',
        'initiated_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'error_details' => 'array',
        'http_status_code' => 'integer',
        'response_time_ms' => 'integer',
        'batch_sequence' => 'integer',
        'retry_count' => 'integer',
        'is_retryable' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    // Operation constants
    const OP_CREATE_LISTING = 'create_listing';

    const OP_UPDATE_LISTING = 'update_listing';

    const OP_DELETE_LISTING = 'delete_listing';

    const OP_MATCH_ASIN = 'match_asin';

    const OP_SET_INVENTORY = 'set_inventory';

    const OP_UPDATE_PRICE = 'update_price';

    const OP_IMPORT_PRODUCTS = 'import_products';

    const OP_SUBMIT_FEED = 'submit_feed';

    const OP_POLL_FEED = 'poll_feed';

    const OP_GET_ORDERS = 'get_orders';

    const OP_POLL_ORDERS = 'poll_orders';

    const OP_SYNC_FBA_INVENTORY = 'sync_fba_inventory';

    // Status constants
    const STATUS_SUCCESS = 'success';

    const STATUS_ERROR = 'error';

    const STATUS_WARNING = 'warning';

    const STATUS_SKIPPED = 'skipped';

    const STATUS_PENDING = 'pending';

    // Direction constants
    const DIRECTION_OUTBOUND = 'outbound';

    const DIRECTION_INBOUND = 'inbound';

    // Relationships

    public function integrationCredential(): BelongsTo
    {
        return $this->belongsTo(IntegrationCredential::class);
    }

    public function amazonListing(): BelongsTo
    {
        return $this->belongsTo(AmazonListing::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
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

    public function scopeForListing(Builder $query, int $listingId): Builder
    {
        return $query->where('amazon_listing_id', $listingId);
    }

    public function scopeByOperation(Builder $query, string $operation): Builder
    {
        return $query->where('operation', $operation);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ERROR);
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('is_retryable', true)
            ->where('status', self::STATUS_ERROR)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    public function scopeByBatch(Builder $query, string $batchId): Builder
    {
        return $query->where('batch_id', $batchId)
            ->orderBy('batch_sequence');
    }

    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // Status helpers

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    public function canRetry(): bool
    {
        return $this->is_retryable &&
               $this->status === self::STATUS_ERROR &&
               $this->retry_count < 3;
    }

    public static function startOperation(
        int $credentialId,
        int $teamId,
        string $operation,
        array $attributes = []
    ): self {
        return self::create(array_merge([
            'integration_credential_id' => $credentialId,
            'team_id' => $teamId,
            'operation' => $operation,
            'direction' => self::DIRECTION_OUTBOUND,
            'status' => self::STATUS_PENDING,
            'started_at' => now(),
        ], $attributes));
    }

    public function completeSuccess(array $response = [], ?int $statusCode = null): self
    {
        $this->update([
            'status' => self::STATUS_SUCCESS,
            'http_status_code' => $statusCode,
            'response_payload' => $this->sanitizePayload($response),
            'completed_at' => now(),
            'response_time_ms' => $this->started_at
                ? (int) abs(now()->diffInMilliseconds($this->started_at))
                : null,
        ]);

        return $this;
    }

    public function completeError(
        string $message,
        ?string $code = null,
        array $details = [],
        ?int $statusCode = null,
        bool $retryable = false
    ): self {
        $this->update([
            'status' => self::STATUS_ERROR,
            'error_message' => $message,
            'error_code' => $code,
            'error_details' => $details,
            'http_status_code' => $statusCode,
            'completed_at' => now(),
            'is_retryable' => $retryable,
            'next_retry_at' => $retryable ? now()->addMinutes(5 * ($this->retry_count + 1)) : null,
            'response_time_ms' => $this->started_at
                ? (int) abs(now()->diffInMilliseconds($this->started_at))
                : null,
        ]);

        return $this;
    }

    public function setRequest(string $endpoint, string $method, array $payload = []): self
    {
        $this->update([
            'api_endpoint' => $endpoint,
            'http_method' => strtoupper($method),
            'request_payload' => $this->sanitizePayload($payload),
        ]);

        return $this;
    }

    public function incrementRetry(): self
    {
        $this->increment('retry_count');
        $this->update([
            'next_retry_at' => now()->addMinutes(5 * $this->retry_count),
        ]);

        return $this;
    }

    public static function generateBatchId(): string
    {
        return (string) Str::uuid();
    }

    protected function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = [
            'access_token',
            'refresh_token',
            'authorization',
            'client_secret',
            'password',
            'token',
            'api_key',
            'api_secret',
        ];

        array_walk_recursive($payload, function (&$value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $value = '[REDACTED]';
            }
        });

        return $payload;
    }

    public function getOperationLabelAttribute(): string
    {
        $labels = [
            self::OP_CREATE_LISTING => 'Create Listing',
            self::OP_UPDATE_LISTING => 'Update Listing',
            self::OP_DELETE_LISTING => 'Delete Listing',
            self::OP_MATCH_ASIN => 'Match ASIN',
            self::OP_SET_INVENTORY => 'Set Inventory',
            self::OP_UPDATE_PRICE => 'Update Price',
            self::OP_IMPORT_PRODUCTS => 'Import Products',
            self::OP_SUBMIT_FEED => 'Submit Feed',
            self::OP_POLL_FEED => 'Poll Feed',
            self::OP_GET_ORDERS => 'Get Orders',
            self::OP_POLL_ORDERS => 'Poll Orders',
            self::OP_SYNC_FBA_INVENTORY => 'Sync FBA Inventory',
        ];

        return $labels[$this->operation] ?? $this->operation;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'green',
            self::STATUS_ERROR => 'red',
            self::STATUS_WARNING => 'yellow',
            self::STATUS_SKIPPED => 'gray',
            self::STATUS_PENDING => 'blue',
            default => 'gray',
        };
    }

    public function getDurationAttribute(): ?string
    {
        if (! $this->response_time_ms) {
            return null;
        }

        if ($this->response_time_ms < 1000) {
            return $this->response_time_ms.'ms';
        }

        return round($this->response_time_ms / 1000, 2).'s';
    }
}
