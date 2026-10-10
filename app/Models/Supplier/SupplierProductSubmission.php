<?php

namespace App\Models\Supplier;

use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Inventory\Supplier;
use App\Models\User\User;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProductSubmission extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, LogsAudit;

    protected ?string $auditCategory = 'supplier';

    protected ?string $auditActionPrefix = 'supplier.submission';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'organization_id',
        'supplier_id',
        'intake_queue_id',
        'status',
        'submitted_data',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'submitted_data' => 'array',
        'reviewed_at' => 'datetime',
    ];

    // -------------------------------------------------------
    // Relationships
    // -------------------------------------------------------

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function intakeQueue(): BelongsTo
    {
        return $this->belongsTo(ProductIntakeQueue::class, 'intake_queue_id');
    }

    // -------------------------------------------------------
    // Scopes
    // -------------------------------------------------------

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_REVIEW);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }
}
