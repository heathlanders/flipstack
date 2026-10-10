<?php

namespace App\Models\Supplier;

use App\Enums\Supplier\SettlementStatus;
use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\User\User;
use App\Traits\LogsAudit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Settlement extends Model
{
    use HasFactory, HasUuids, LogsAudit, SoftDeletes;

    protected ?string $auditCategory = 'supplier';

    protected ?string $auditActionPrefix = 'supplier.settlement';

    protected $fillable = [
        'organization_id',
        'supplier_id',
        'settlement_number',
        'settlement_period_start',
        'settlement_period_end',
        'period_type',
        'total_sales_count',
        'total_items_sold',
        'gross_sales_amount',
        'returns_amount',
        'net_sales_amount',
        'commission_amount',
        'fees_deducted',
        'adjustments',
        'bonuses',
        'net_amount',
        'tax_withheld',
        'tax_documents',
        'status',
        'payment_method',
        'payment_reference',
        'payment_date',
        'payment_due_date',
        'payment_details',
        'bank_account_last4',
        'payment_destination',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'is_disputed',
        'dispute_reason',
        'dispute_date',
        'dispute_resolved_at',
        'dispute_resolution',
        'notes',
        'internal_notes',
        'attachments',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'settlement_period_start' => 'datetime',
        'settlement_period_end' => 'datetime',
        'total_sales_count' => 'integer',
        'total_items_sold' => 'integer',
        'gross_sales_amount' => 'decimal:2',
        'returns_amount' => 'decimal:2',
        'net_sales_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'fees_deducted' => 'decimal:2',
        'adjustments' => 'decimal:2',
        'bonuses' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'tax_withheld' => 'decimal:2',
        'tax_documents' => 'array',
        'status' => SettlementStatus::class,
        'payment_date' => 'datetime',
        'payment_due_date' => 'datetime',
        'payment_details' => 'array',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'is_disputed' => 'boolean',
        'dispute_date' => 'datetime',
        'dispute_resolved_at' => 'datetime',
        'attachments' => 'array',
        'processed_at' => 'datetime',
    ];

    // Auto-generate settlement number
    protected static function booted(): void
    {
        static::creating(function ($settlement) {
            if (empty($settlement->settlement_number)) {
                $settlement->settlement_number = self::generateSettlementNumber(
                    $settlement->organization_id,
                    $settlement->supplier_id
                );
            }
        });
    }

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Scopes
    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForSupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeStatus($query, SettlementStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', SettlementStatus::pendingStatuses());
    }

    public function scopePaid($query)
    {
        return $query->where('status', SettlementStatus::PAID);
    }

    public function scopeDisputed($query)
    {
        return $query->where('is_disputed', true);
    }

    public function scopeDueSoon($query, int $days = 7)
    {
        return $query->where('payment_due_date', '<=', now()->addDays($days))
            ->whereIn('status', [SettlementStatus::APPROVED, SettlementStatus::PENDING_PAYMENT]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('payment_due_date', '<', now())
            ->whereIn('status', [SettlementStatus::APPROVED, SettlementStatus::PENDING_PAYMENT]);
    }

    public function scopeInPeriod($query, $start, $end)
    {
        return $query->where('settlement_period_start', '>=', $start)
            ->where('settlement_period_end', '<=', $end);
    }

    // Methods
    public function canBeEdited(): bool
    {
        return $this->status->canBeEdited();
    }

    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    public function canBeDisputed(): bool
    {
        return $this->status->canBeDisputed();
    }

    public function isFinalized(): bool
    {
        return $this->status->isFinalized();
    }

    public function markAsReviewed(int $userId): self
    {
        $this->update([
            'status' => SettlementStatus::PENDING_REVIEW,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);

        return $this;
    }

    public function approve(int $userId): self
    {
        $this->update([
            'status' => SettlementStatus::APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $this;
    }

    public function markAsPendingPayment(): self
    {
        $this->update([
            'status' => SettlementStatus::PENDING_PAYMENT,
        ]);

        return $this;
    }

    public function markAsProcessing(): self
    {
        $this->update([
            'status' => SettlementStatus::PROCESSING,
        ]);

        return $this;
    }

    public function markAsPaid(
        string $paymentMethod,
        ?string $paymentReference = null,
        ?array $paymentDetails = null
    ): self {
        $this->update([
            'status' => SettlementStatus::PAID,
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'payment_date' => now(),
            'payment_details' => $paymentDetails,
        ]);

        return $this;
    }

    public function markAsFailed(?string $reason = null): self
    {
        $this->update([
            'status' => SettlementStatus::FAILED,
            'notes' => $reason ? ($this->notes."\nPayment failed: ".$reason) : $this->notes,
        ]);

        return $this;
    }

    public function cancel(int $userId, ?string $reason = null): self
    {
        $this->update([
            'status' => SettlementStatus::CANCELLED,
            'notes' => $reason ? ($this->notes."\nCancelled: ".$reason) : $this->notes,
        ]);

        return $this;
    }

    public function dispute(string $reason): self
    {
        $this->update([
            'status' => SettlementStatus::DISPUTED,
            'is_disputed' => true,
            'dispute_reason' => $reason,
            'dispute_date' => now(),
        ]);

        return $this;
    }

    public function resolveDispute(string $resolution): self
    {
        $this->update([
            'is_disputed' => false,
            'dispute_resolved_at' => now(),
            'dispute_resolution' => $resolution,
            'status' => SettlementStatus::APPROVED,
        ]);

        return $this;
    }

    public function recalculateTotals(): self
    {
        $items = $this->items()->get();

        $grossSales = $items->sum('sale_price');
        $returns = $items->where('is_returned', true)->sum('refund_amount');
        $commission = $items->sum('final_commission');
        $supplierPayout = $items->sum('supplier_payout');

        $this->update([
            'total_sales_count' => $items->count(),
            'total_items_sold' => $items->where('is_returned', false)->count(),
            'gross_sales_amount' => $grossSales,
            'returns_amount' => $returns,
            'net_sales_amount' => $grossSales - $returns,
            'commission_amount' => $commission,
            'net_amount' => $supplierPayout - $this->fees_deducted + $this->adjustments + $this->bonuses - $this->tax_withheld,
        ]);

        return $this;
    }

    public function addItem(array $data): SettlementItem
    {
        $item = $this->items()->create($data);
        $this->recalculateTotals();

        return $item;
    }

    public function removeItem(string $itemId): bool
    {
        $deleted = $this->items()->where('id', $itemId)->delete();

        if ($deleted) {
            $this->recalculateTotals();
        }

        return $deleted > 0;
    }

    public function getSupplierPayoutAttribute(): float
    {
        return $this->net_amount;
    }

    public function getPeriodLabelAttribute(): string
    {
        return $this->settlement_period_start->format('M d, Y').' - '.$this->settlement_period_end->format('M d, Y');
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (! $this->payment_due_date) {
            return null;
        }

        return now()->diffInDays($this->payment_due_date, false);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->payment_due_date && $this->payment_due_date < now() && ! $this->isFinalized();
    }

    // Static Methods
    public static function generateSettlementNumber(int $organizationId, string $supplierId): string
    {
        $prefix = 'SET';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));
        $sequence = self::where('organization_id', $organizationId)
            ->whereDate('created_at', today())
            ->count() + 1;

        return sprintf('%s-%s-%04d-%s', $prefix, $date, $sequence, $random);
    }

    public static function createForPeriod(
        int $organizationId,
        string $supplierId,
        \DateTime $periodStart,
        \DateTime $periodEnd,
        string $periodType = 'monthly'
    ): self {
        return self::create([
            'organization_id' => $organizationId,
            'supplier_id' => $supplierId,
            'settlement_period_start' => $periodStart,
            'settlement_period_end' => $periodEnd,
            'period_type' => $periodType,
            'status' => SettlementStatus::DRAFT,
            'payment_due_date' => $periodEnd->modify('+30 days'),
        ]);
    }
}
