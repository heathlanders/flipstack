<?php

namespace App\Models\System;

use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkOperationJob extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'operation_type', 'status',
        'total_items', 'processed_items', 'failed_items',
        'parameters', 'errors', 'file_path', 'result_file_path',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'parameters' => 'array', 'errors' => 'array',
        'started_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';

    const STATUS_PROCESSING = 'processing';

    const STATUS_COMPLETED = 'completed';

    const STATUS_FAILED = 'failed';

    const STATUS_CANCELLED = 'cancelled';

    const TYPE_BULK_UPDATE = 'bulk_update';

    const TYPE_BULK_DELETE = 'bulk_delete';

    const TYPE_STOCK_ADJUSTMENT = 'stock_adjustment';

    const TYPE_CATEGORY_UPDATE = 'category_update';

    const TYPE_LOCATION_TRANSFER = 'location_transfer';

    const TYPE_IMPORT = 'import';

    const TYPE_EXPORT = 'export';

    const TYPE_EBAY_IMPORT = 'ebay_import';

    const TYPE_EBAY_CSV_IMPORT = 'ebay_csv_import';

    const TYPE_SHOPIFY_MATCHING = 'shopify_matching';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING, 'started_at' => now()]);
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED, 'completed_at' => now()]);
    }

    public function markAsFailed(?string $error = null): void
    {
        $errors = $this->errors ?? [];
        if ($error) {
            $errors[] = ['message' => $error, 'time' => now()->toISOString()];
        }
        $this->update(['status' => self::STATUS_FAILED, 'errors' => $errors, 'completed_at' => now()]);
    }

    public function markAsCancelled(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED, 'completed_at' => now()]);
    }

    public function incrementProcessed(int $count = 1): void
    {
        $this->increment('processed_items', $count);
    }

    public function incrementFailed(int $count = 1, ?array $error = null): void
    {
        $this->increment('failed_items', $count);
        if ($error) {
            $errors = $this->errors ?? [];
            $errors[] = $error;
            if (count($errors) > 100) {
                $errors = array_slice($errors, -100);
            }
            $this->update(['errors' => $errors]);
        }
    }

    public function getProgressPercentAttribute(): float
    {
        return $this->total_items === 0 ? 0 : round(($this->processed_items / $this->total_items) * 100, 1);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    public function isRetryable(): bool
    {
        return $this->status === self::STATUS_FAILED && $this->failed_items > 0;
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }

    public function getOperationTypeLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->operation_type));
    }
}
