<?php

namespace App\Models\Supplier;

use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierTierHistory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'supplier_tier_history';

    protected $fillable = [
        'supplier_id',
        'organization_id',
        'from_tier_id',
        'to_tier_id',
        'change_type',
        'reason',
        'performance_metric_id',
        'performance_score',
        'metrics_snapshot',
        'is_automated',
        'changed_by',
        'effective_date',
        'notification_sent',
        'notification_sent_at',
    ];

    protected $casts = [
        'performance_score' => 'decimal:2',
        'metrics_snapshot' => 'array',
        'is_automated' => 'boolean',
        'effective_date' => 'datetime',
        'notification_sent' => 'boolean',
        'notification_sent_at' => 'datetime',
    ];

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function fromTier(): BelongsTo
    {
        return $this->belongsTo(SupplierTier::class, 'from_tier_id');
    }

    public function toTier(): BelongsTo
    {
        return $this->belongsTo(SupplierTier::class, 'to_tier_id');
    }

    public function performanceMetric(): BelongsTo
    {
        return $this->belongsTo(SupplierPerformanceMetric::class, 'performance_metric_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // Scopes
    public function scopeForSupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopePromotions($query)
    {
        return $query->where('change_type', 'promotion');
    }

    public function scopeDemotions($query)
    {
        return $query->where('change_type', 'demotion');
    }

    public function scopeAutomated($query)
    {
        return $query->where('is_automated', true);
    }

    public function scopeManual($query)
    {
        return $query->where('is_automated', false);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('effective_date', '>=', now()->subDays($days));
    }

    public function scopePendingNotification($query)
    {
        return $query->where('notification_sent', false);
    }

    // Methods
    public function isPromotion(): bool
    {
        return $this->change_type === 'promotion';
    }

    public function isDemotion(): bool
    {
        return $this->change_type === 'demotion';
    }

    public function markNotificationSent(): self
    {
        $this->update([
            'notification_sent' => true,
            'notification_sent_at' => now(),
        ]);

        return $this;
    }

    public function getChangeDirectionAttribute(): string
    {
        return match ($this->change_type) {
            'promotion' => '↑',
            'demotion' => '↓',
            'manual' => '→',
            'initial' => '•',
            'reset' => '↺',
            default => '-',
        };
    }

    public function getChangeLabelAttribute(): string
    {
        if (! $this->fromTier) {
            return "Assigned to {$this->toTier->tier_name}";
        }

        return match ($this->change_type) {
            'promotion' => "Promoted from {$this->fromTier->tier_name} to {$this->toTier->tier_name}",
            'demotion' => "Demoted from {$this->fromTier->tier_name} to {$this->toTier->tier_name}",
            'manual' => "Changed from {$this->fromTier->tier_name} to {$this->toTier->tier_name}",
            'reset' => "Reset to {$this->toTier->tier_name}",
            default => "Changed to {$this->toTier->tier_name}",
        };
    }

    // Static Methods
    public static function recordChange(
        Supplier $supplier,
        SupplierTier $toTier,
        ?SupplierTier $fromTier = null,
        string $changeType = 'manual',
        ?string $reason = null,
        ?int $changedBy = null,
        ?SupplierPerformanceMetric $performanceMetric = null,
        bool $isAutomated = false
    ): self {
        return self::create([
            'supplier_id' => $supplier->id,
            'organization_id' => $supplier->organization_id,
            'from_tier_id' => $fromTier?->id,
            'to_tier_id' => $toTier->id,
            'change_type' => $changeType,
            'reason' => $reason,
            'performance_metric_id' => $performanceMetric?->id,
            'performance_score' => $performanceMetric?->overall_performance_score,
            'metrics_snapshot' => $performanceMetric ? [
                'quality_score' => $performanceMetric->quality_score,
                'compliance_score' => $performanceMetric->compliance_score,
                'sell_through_rate' => $performanceMetric->sell_through_rate,
                'return_rate' => $performanceMetric->return_rate,
                'customer_rating' => $performanceMetric->customer_rating,
            ] : null,
            'is_automated' => $isAutomated,
            'changed_by' => $changedBy,
            'effective_date' => now(),
            'notification_sent' => false,
        ]);
    }

    public static function getSupplierTimeline(string $supplierId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return self::forSupplier($supplierId)
            ->with(['fromTier', 'toTier', 'changedBy'])
            ->orderBy('effective_date', 'desc')
            ->limit($limit)
            ->get();
    }
}
