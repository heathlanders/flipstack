<?php

namespace App\Models\Inventory;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariation;
use App\Models\User\User;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabelingWorkOrderItem extends Model
{
    use HasFactory, HasUuid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_LABEL_PRINTED = 'label_printed';

    public const STATUS_SHELVED = 'shelved';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'work_order_id',
        'product_id',
        'product_variation_id',
        'quantity',
        'status',
        'bin_id',
        'label_printed_at',
        'labeled_by',
        'shelved_at',
        'shelved_by',
        'bin_verified',
        'skip_reason',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'bin_verified' => 'boolean',
        'label_printed_at' => 'datetime',
        'shelved_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    // Relationships

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(LabelingWorkOrder::class, 'work_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'bin_id');
    }

    public function labeledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'labeled_by');
    }

    public function shelvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shelved_by');
    }

    // Status transitions

    public function markLabelPrinted(int $userId): void
    {
        $this->update([
            'status' => self::STATUS_LABEL_PRINTED,
            'label_printed_at' => now(),
            'labeled_by' => $userId,
        ]);

        $this->workOrder->recalculateCounts();
    }

    public function markShelved(string $binId, int $userId, bool $verified = false): void
    {
        $this->update([
            'status' => self::STATUS_SHELVED,
            'bin_id' => $binId,
            'shelved_at' => now(),
            'shelved_by' => $userId,
            'bin_verified' => $verified,
        ]);

        $this->workOrder->recalculateCounts();
    }

    public function markSkipped(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'skip_reason' => $reason,
        ]);

        $this->workOrder->recalculateCounts();
    }

    // Status checks

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isLabeled(): bool
    {
        return $this->status === self::STATUS_LABEL_PRINTED;
    }

    public function isShelved(): bool
    {
        return $this->status === self::STATUS_SHELVED;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    public function canTransitionTo(string $status): bool
    {
        $allowed = [
            self::STATUS_PENDING => [self::STATUS_LABEL_PRINTED, self::STATUS_SHELVED, self::STATUS_SKIPPED],
            self::STATUS_LABEL_PRINTED => [self::STATUS_SHELVED, self::STATUS_SKIPPED],
            self::STATUS_SHELVED => [],
            self::STATUS_SKIPPED => [],
        ];

        return in_array($status, $allowed[$this->status] ?? []);
    }
}
