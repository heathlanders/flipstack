<?php

namespace App\Models\Procurement;

use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\Supplier;
use App\Models\User\User;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, LogsAudit, SoftDeletes;

    protected ?string $auditCategory = 'procurement';

    protected ?string $auditActionPrefix = 'po';

    protected $fillable = [
        'organization_id',
        'supplier_id',
        'location_id',
        'po_number',
        'status',
        'order_date',
        'expected_delivery_date',
        'notes',
        'subtotal',
        'tax_amount',
        'shipping_cost',
        'total_amount',
        'created_by',
        'approved_by',
        'approved_at',
        'submitted_at',
        'received_at',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'submitted_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    public static function validStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_PARTIALLY_RECEIVED,
            self::STATUS_RECEIVED,
            self::STATUS_CANCELLED,
        ];
    }

    // Relationships

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceipt::class);
    }

    // Scopes

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_PARTIALLY_RECEIVED]);
    }

    public function scopeOverdue($query)
    {
        return $query->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_PARTIALLY_RECEIVED])
            ->whereNotNull('expected_delivery_date')
            ->where('expected_delivery_date', '<', now());
    }

    // Status helpers

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->items()->count() > 0;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SUBMITTED]);
    }

    public function canReceiveItems(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_PARTIALLY_RECEIVED]);
    }

    public function isFullyReceived(): bool
    {
        return $this->items->every(fn ($item) => $item->quantity_received >= $item->quantity_ordered);
    }

    // Calculated attributes

    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    public function getTotalQuantityOrderedAttribute(): int
    {
        return $this->items()->sum('quantity_ordered');
    }

    public function getTotalQuantityReceivedAttribute(): int
    {
        return $this->items()->sum('quantity_received');
    }

    public function getReceivingProgressAttribute(): float
    {
        $ordered = $this->total_quantity_ordered;

        return $ordered > 0 ? round(($this->total_quantity_received / $ordered) * 100, 1) : 0;
    }

    public function recalculateTotals(): void
    {
        $this->subtotal = $this->items()->sum('total_cost');
        $this->total_amount = $this->subtotal + $this->tax_amount + $this->shipping_cost;
        $this->saveQuietly();
    }
}
