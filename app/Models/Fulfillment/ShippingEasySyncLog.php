<?php

namespace App\Models\Fulfillment;

use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShippingEasySyncLog extends Model
{
    protected $table = 'shipping_easy_sync_logs';

    protected $fillable = [
        'organization_id',
        'integration_credential_id',
        'order_id',
        'shipment_id',
        'shippingeasy_order_id',
        'shippingeasy_shipment_id',
        'operation',
        'direction',
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
    const OP_CREATE_ORDER = 'create_order';

    const OP_PURCHASE_LABEL = 'purchase_label';

    const OP_VOID_LABEL = 'void_label';

    const OP_GET_RATES = 'get_rates';

    const OP_GET_ORDERS = 'get_orders';

    const OP_GET_ORDER = 'get_order';

    const OP_CANCEL_ORDER = 'cancel_order';

    const OP_GET_CARRIERS = 'get_carriers';

    const OP_WEBHOOK_RECEIVED = 'webhook_received';

    const OP_TEST_CONNECTION = 'test_connection';

    // Status constants
    const STATUS_SUCCESS = 'success';

    const STATUS_ERROR = 'error';

    const STATUS_WARNING = 'warning';

    const STATUS_PENDING = 'pending';

    // Direction constants
    const DIRECTION_OUTBOUND = 'outbound';

    const DIRECTION_INBOUND = 'inbound';

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function integrationCredential(): BelongsTo
    {
        return $this->belongsTo(IntegrationCredential::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    // Scopes
    public function scopeForOrganization(Builder $query, int $orgId): Builder
    {
        return $query->where('organization_id', $orgId);
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

    // Factory methods
    public static function startOperation(
        int $organizationId,
        string $operation,
        array $attributes = []
    ): self {
        return self::create(array_merge([
            'organization_id' => $organizationId,
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
                ? max(0, (int) now()->diffInMilliseconds($this->started_at))
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
                ? max(0, (int) now()->diffInMilliseconds($this->started_at))
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

    public static function generateBatchId(): string
    {
        return (string) Str::uuid();
    }

    protected function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = [
            'api_key',
            'api_secret',
            'api_signature',
            'access_token',
            'refresh_token',
            'client_secret',
            'password',
            'token',
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
            self::OP_CREATE_ORDER => 'Create Order',
            self::OP_PURCHASE_LABEL => 'Purchase Label',
            self::OP_VOID_LABEL => 'Void Label',
            self::OP_GET_RATES => 'Get Rates',
            self::OP_GET_ORDERS => 'Get Orders',
            self::OP_GET_ORDER => 'Get Order',
            self::OP_CANCEL_ORDER => 'Cancel Order',
            self::OP_GET_CARRIERS => 'Get Carriers',
            self::OP_WEBHOOK_RECEIVED => 'Webhook Received',
            self::OP_TEST_CONNECTION => 'Test Connection',
        ];

        return $labels[$this->operation] ?? $this->operation;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'green',
            self::STATUS_ERROR => 'red',
            self::STATUS_WARNING => 'yellow',
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
