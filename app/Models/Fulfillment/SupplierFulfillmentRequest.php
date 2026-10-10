<?php

namespace App\Models\Fulfillment;

use App\Models\Inventory\Supplier;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierFulfillmentRequest extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'sla_deadline' => 'datetime',
        'shipped_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_CANCELLED = 'cancelled';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isOverdue(): bool
    {
        return $this->sla_deadline->isPast() && ! in_array($this->status, [self::STATUS_SHIPPED, self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('sla_deadline', '<', now())
            ->whereNotIn('status', [self::STATUS_SHIPPED, self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }
}
