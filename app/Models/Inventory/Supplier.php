<?php

namespace App\Models\Inventory;

use App\Enums\Supplier\SettlementFrequency;
use App\Enums\Supplier\SupplierStatus;
use App\Models\Catalog\Product;
use App\Models\Fulfillment\Order;
use App\Models\Organization\Organization;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Supplier\CommissionStructure;
use App\Models\Supplier\Settlement;
use App\Models\Supplier\SupplierNotification;
use App\Models\Supplier\SupplierOnboarding;
use App\Models\Supplier\SupplierPerformanceMetric;
use App\Models\Supplier\SupplierProductSubmission;
use App\Models\Supplier\SupplierTier;
use App\Models\Supplier\SupplierTierHistory;
use App\Models\User\User;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'company_name',
        'business_type',
        'contact_name',
        'code',
        'email',
        'phone',
        'website',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'tax_id',
        'business_registration',
        'payment_terms',
        'currency',
        'minimum_order_amount',
        'lead_time_days',
        'performance_rating',
        'total_orders',
        'on_time_delivery_rate',
        'is_active',
        'is_preferred',
        'supplier_status',
        'status_reason',
        'status_changed_at',
        'status_changed_by',
        'current_tier_id',
        'tier_assigned_at',
        'tier_evaluation_due_at',
        'commission_structure_id',
        'custom_commission_rate',
        'settlement_schedule',
        'settlement_day',
        'next_settlement_date',
        'total_sales_ytd',
        'total_sales_all_time',
        'total_commissions_paid',
        'pending_balance',
        'available_balance',
        'cached_performance_score',
        'cached_quality_score',
        'cached_fulfillment_score',
        'cached_volume_score',
        'performance_calculated_at',
        'user_id',
        'portal_enabled',
        'portal_activated_at',
        'last_portal_login_at',
        'onboarding_complete',
        'onboarding_completed_at',
        'payment_method',
        'payment_info',
        'bank_account_last4',
        'w9_on_file',
        'contract_signed',
        'contract_expires_at',
        'notes',
        'meta_data',
        'created_by',
        'fulfillment_mode',
        'dropship_sla_hours',
    ];

    protected $casts = [
        'minimum_order_amount' => 'decimal:2',
        'performance_rating' => 'decimal:2',
        'on_time_delivery_rate' => 'decimal:2',
        'payment_terms' => 'integer',
        'lead_time_days' => 'integer',
        'total_orders' => 'integer',
        'is_active' => 'boolean',
        'is_preferred' => 'boolean',
        'dropship_sla_hours' => 'integer',
        'supplier_status' => SupplierStatus::class,
        'status_changed_at' => 'datetime',
        'tier_assigned_at' => 'datetime',
        'tier_evaluation_due_at' => 'datetime',
        'custom_commission_rate' => 'decimal:2',
        'settlement_day' => 'integer',
        'next_settlement_date' => 'date',
        'total_sales_ytd' => 'decimal:2',
        'total_sales_all_time' => 'decimal:2',
        'total_commissions_paid' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'available_balance' => 'decimal:2',
        'cached_performance_score' => 'decimal:2',
        'cached_quality_score' => 'integer',
        'cached_fulfillment_score' => 'integer',
        'cached_volume_score' => 'integer',
        'performance_calculated_at' => 'datetime',
        'portal_enabled' => 'boolean',
        'portal_activated_at' => 'datetime',
        'last_portal_login_at' => 'datetime',
        'onboarding_complete' => 'boolean',
        'onboarding_completed_at' => 'datetime',
        'payment_info' => 'array',
        'w9_on_file' => 'boolean',
        'contract_signed' => 'boolean',
        'contract_expires_at' => 'date',
        'meta_data' => 'array',
    ];

    // ============================================
    // Relationships
    // ============================================

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function currentTier(): BelongsTo
    {
        return $this->belongsTo(SupplierTier::class, 'current_tier_id');
    }

    public function commissionStructure(): BelongsTo
    {
        return $this->belongsTo(CommissionStructure::class, 'commission_structure_id');
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'supplier_products');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function performanceMetrics(): HasMany
    {
        return $this->hasMany(SupplierPerformanceMetric::class);
    }

    public function onboarding(): HasOne
    {
        return $this->hasOne(SupplierOnboarding::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(SupplierNotification::class);
    }

    public function tierHistory(): HasMany
    {
        return $this->hasMany(SupplierTierHistory::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function inventoryBatches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function productSubmissions(): HasMany
    {
        return $this->hasMany(SupplierProductSubmission::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ============================================
    // Scopes
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePreferred($query)
    {
        return $query->where('is_preferred', true);
    }

    public function scopeByPerformance($query)
    {
        return $query->orderBy('performance_rating', 'desc');
    }

    public function scopeByStatus($query, SupplierStatus $status)
    {
        return $query->where('supplier_status', $status);
    }

    public function scopeByTier($query, $tierId)
    {
        return $query->where('current_tier_id', $tierId);
    }

    public function scopeNeedsSettlement($query)
    {
        return $query->where('next_settlement_date', '<=', now())
            ->where('supplier_status', SupplierStatus::ACTIVE);
    }

    public function scopeOnboarding($query)
    {
        return $query->where('supplier_status', SupplierStatus::ONBOARDING);
    }

    public function scopePortalEnabled($query)
    {
        return $query->where('portal_enabled', true);
    }

    public function scopeAutoGenerated($query)
    {
        return $query->where('meta_data->auto_generated', true);
    }

    public function scopeNeedsReview($query)
    {
        return $query->autoGenerated();
    }

    // ============================================
    // Helper Methods
    // ============================================

    public function getIsAutoGeneratedAttribute(): bool
    {
        return (bool) ($this->meta_data['auto_generated'] ?? false);
    }

    public function getIsConsignorAttribute(): bool
    {
        return (bool) ($this->meta_data['is_consignor'] ?? false);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->company_name ?: $this->name;
    }

    public function getSettlementFrequencyEnum(): ?SettlementFrequency
    {
        if (! $this->settlement_schedule) {
            return SettlementFrequency::MONTHLY;
        }

        return SettlementFrequency::tryFrom($this->settlement_schedule);
    }

    public function isActive(): bool
    {
        return $this->supplier_status === SupplierStatus::ACTIVE;
    }

    public function canSubmitProducts(): bool
    {
        return $this->supplier_status?->canSubmitProducts() ?? false;
    }

    public function canReceiveSettlements(): bool
    {
        return $this->supplier_status?->canReceiveSettlements() ?? false;
    }

    public function getEffectiveCommissionRate(): float
    {
        if ($this->custom_commission_rate !== null) {
            return (float) $this->custom_commission_rate;
        }

        if ($this->commissionStructure) {
            $rate = (float) $this->commissionStructure->base_percentage;

            if ($this->currentTier && $this->currentTier->commission_adjustment) {
                $rate += (float) $this->currentTier->commission_adjustment;
            }

            return max(0, $rate);
        }

        return 0;
    }

    public function updatePerformanceCache(SupplierPerformanceMetric $metrics): void
    {
        $this->update([
            'cached_performance_score' => $metrics->overall_score,
            'cached_quality_score' => $metrics->quality_score,
            'cached_fulfillment_score' => $metrics->fulfillment_score,
            'cached_volume_score' => $metrics->volume_score,
            'performance_calculated_at' => now(),
        ]);
    }

    public function calculateNextSettlementDate(): ?\DateTime
    {
        $frequency = $this->getSettlementFrequencyEnum();
        if (! $frequency) {
            return null;
        }

        return $frequency->getNextSettlementDate(now(), $this->settlement_day);
    }

    public function updatePerformanceRating(): void
    {
        $latestMetric = $this->performanceMetrics()
            ->finalized()
            ->latest('period_end')
            ->first();

        if ($latestMetric) {
            $this->updatePerformanceCache($latestMetric);
        }
    }

    public function recordPortalLogin(): void
    {
        $this->update([
            'last_portal_login_at' => now(),
        ]);
    }

    public function completeOnboarding(): void
    {
        $this->update([
            'onboarding_complete' => true,
            'onboarding_completed_at' => now(),
            'supplier_status' => SupplierStatus::ACTIVE,
            'status_changed_at' => now(),
        ]);
    }

    public function suspend(?string $reason = null, ?User $changedBy = null): void
    {
        $this->update([
            'supplier_status' => SupplierStatus::SUSPENDED,
            'status_reason' => $reason,
            'status_changed_at' => now(),
            'status_changed_by' => $changedBy?->id,
        ]);
    }

    public function activate(?User $changedBy = null): void
    {
        $this->update([
            'supplier_status' => SupplierStatus::ACTIVE,
            'status_reason' => null,
            'status_changed_at' => now(),
            'status_changed_by' => $changedBy?->id,
        ]);
    }
}
