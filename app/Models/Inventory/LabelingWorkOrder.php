<?php

namespace App\Models\Inventory;

use App\Models\Organization\Organization;
use App\Models\User\User;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabelingWorkOrder extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PARTIALLY_COMPLETED = 'partially_completed';

    public const SOURCE_PO_RECEIPT = 'po_receipt';

    public const SOURCE_INTAKE_QUEUE = 'intake_queue';

    public const SOURCE_CSV_IMPORT = 'csv_import';

    public const SOURCE_EBAY_IMPORT = 'ebay_import';

    public const SOURCE_SHOPIFY_IMPORT = 'shopify_import';

    public const SOURCE_PART_OUT = 'part_out';

    public const SOURCE_MANUAL = 'manual';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_STANDARD = 'standard';

    protected $fillable = [
        'organization_id',
        'work_order_number',
        'source_type',
        'source_id',
        'location_id',
        'status',
        'priority',
        'assigned_to',
        'notes',
        'total_items',
        'labeled_items',
        'shelved_items',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'total_items' => 'integer',
        'labeled_items' => 'integer',
        'shelved_items' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabelingWorkOrderItem::class, 'work_order_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Status methods

    public function canStart(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function canComplete(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function markInProgress(int $userId): void
    {
        $this->update([
            'status' => self::STATUS_IN_PROGRESS,
            'assigned_to' => $userId,
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function markPartiallyCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_PARTIALLY_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    // Helpers

    public function getProgress(): array
    {
        $items = $this->items;

        return [
            'total' => $items->count(),
            'pending' => $items->where('status', LabelingWorkOrderItem::STATUS_PENDING)->count(),
            'labeled' => $items->where('status', LabelingWorkOrderItem::STATUS_LABEL_PRINTED)->count(),
            'shelved' => $items->where('status', LabelingWorkOrderItem::STATUS_SHELVED)->count(),
            'skipped' => $items->where('status', LabelingWorkOrderItem::STATUS_SKIPPED)->count(),
        ];
    }

    public function recalculateCounts(): void
    {
        $this->update([
            'total_items' => $this->items()->count(),
            'labeled_items' => $this->items()
                ->whereIn('status', [
                    LabelingWorkOrderItem::STATUS_LABEL_PRINTED,
                    LabelingWorkOrderItem::STATUS_SHELVED,
                ])->count(),
            'shelved_items' => $this->items()
                ->where('status', LabelingWorkOrderItem::STATUS_SHELVED)
                ->count(),
        ]);
    }

    // Scopes

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeActionable($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function scopeByLocation($query, string $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
