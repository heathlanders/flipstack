<?php

namespace App\Models\Fulfillment;

use App\Models\User\User;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    use BelongsToOrganization, HasUuid, LogsAudit;

    protected ?string $auditCategory = 'fulfillment';

    protected ?string $auditActionPrefix = 'return';

    protected $guarded = [];

    protected $casts = [
        'inspected_at' => 'datetime',
        'received_at' => 'datetime',
        'refunded_at' => 'datetime',
        'quantity' => 'integer',
        'refund_amount' => 'decimal:2',
        'restockable' => 'boolean',
    ];

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SHIPPED_BACK = 'shipped_back';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_INSPECTED = 'inspected';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_DENIED = 'denied';

    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_BUYER = 'buyer';

    public const SOURCE_INTERNAL = 'internal';

    public const SOURCE_MARKETPLACE = 'marketplace';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function inspectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [self::STATUS_REFUNDED, self::STATUS_DENIED, self::STATUS_CANCELLED]);
    }

    public static function generateRmaNumber(): string
    {
        $date = now()->format('Ymd');
        $sequence = static::whereDate('created_at', today())->count() + 1;

        return sprintf('RMA-%s-%04d', $date, $sequence);
    }
}
