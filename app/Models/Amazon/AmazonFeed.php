<?php

namespace App\Models\Amazon;

use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Team;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmazonFeed extends Model
{
    protected $table = 'amazon_feeds';

    protected $fillable = [
        'integration_credential_id',
        'team_id',
        'feed_id',
        'feed_type',
        'status',
        'total_records',
        'processed_records',
        'successful_records',
        'failed_records',
        'error_summary',
        'result_document_id',
        'initiated_by',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'total_records' => 'integer',
        'processed_records' => 'integer',
        'successful_records' => 'integer',
        'failed_records' => 'integer',
        'error_summary' => 'array',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_SUBMITTED = 'submitted';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_DONE = 'done';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_FATAL = 'fatal';

    const TYPE_LISTINGS_FEED = 'JSON_LISTINGS_FEED';

    const TYPE_INVENTORY_FEED = 'POST_INVENTORY_AVAILABILITY_DATA';

    const TYPE_PRICE_FEED = 'POST_PRODUCT_PRICING_DATA';

    // Relationships

    public function integrationCredential(): BelongsTo
    {
        return $this->belongsTo(IntegrationCredential::class);
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

    public function scopeForCredential(Builder $query, int $credentialId): Builder
    {
        return $query->where('integration_credential_id', $credentialId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_IN_PROGRESS]);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DONE);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_CANCELLED, self::STATUS_FATAL]);
    }

    // Status helpers

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_IN_PROGRESS]);
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function hasFailed(): bool
    {
        return in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_FATAL]);
    }

    public function markInProgress(): self
    {
        $this->update(['status' => self::STATUS_IN_PROGRESS]);

        return $this;
    }

    public function markDone(int $successful, int $failed, ?array $errorSummary = null, ?string $resultDocumentId = null): self
    {
        $this->update([
            'status' => self::STATUS_DONE,
            'successful_records' => $successful,
            'failed_records' => $failed,
            'processed_records' => $successful + $failed,
            'error_summary' => $errorSummary,
            'result_document_id' => $resultDocumentId,
            'completed_at' => now(),
        ]);

        return $this;
    }

    public function markFatal(string $error): self
    {
        $this->update([
            'status' => self::STATUS_FATAL,
            'error_summary' => ['fatal_error' => $error],
            'completed_at' => now(),
        ]);

        return $this;
    }
}
