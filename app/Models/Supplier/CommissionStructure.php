<?php

namespace App\Models\Supplier;

use App\Enums\Supplier\CommissionType;
use App\Models\Catalog\Category;
use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionStructure extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'supplier_id',
        'category_id',
        'name',
        'code',
        'description',
        'type',
        'base_percentage',
        'flat_fee_amount',
        'minimum_commission',
        'maximum_commission',
        'tier_adjustments',
        'category_overrides',
        'volume_bonuses',
        'price_tiers',
        'condition_adjustments',
        'custom_rules',
        'exclusions',
        'is_active',
        'effective_date',
        'expires_at',
        'priority',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => CommissionType::class,
        'base_percentage' => 'decimal:2',
        'flat_fee_amount' => 'decimal:2',
        'minimum_commission' => 'decimal:2',
        'maximum_commission' => 'decimal:2',
        'tier_adjustments' => 'array',
        'category_overrides' => 'array',
        'volume_bonuses' => 'array',
        'price_tiers' => 'array',
        'condition_adjustments' => 'array',
        'custom_rules' => 'array',
        'exclusions' => 'array',
        'is_active' => 'boolean',
        'effective_date' => 'datetime',
        'expires_at' => 'datetime',
        'priority' => 'integer',
    ];

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function settlementItems(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(SupplierContract::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_date')
                    ->orWhere('effective_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeDefault($query)
    {
        return $query->whereNull('supplier_id')->whereNull('category_id');
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    // Methods
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->effective_date && $this->effective_date > $now) {
            return false;
        }

        if ($this->expires_at && $this->expires_at <= $now) {
            return false;
        }

        return true;
    }

    public function calculateCommission(
        float $salePrice,
        ?string $categoryId = null,
        ?string $tierId = null,
        ?string $condition = null,
        ?int $monthlyVolume = null
    ): array {
        $commission = 0;
        $breakdown = [];

        switch ($this->type) {
            case CommissionType::PERCENTAGE:
                $commission = $salePrice * ($this->base_percentage / 100);
                $breakdown['base'] = ['type' => 'percentage', 'rate' => $this->base_percentage, 'amount' => $commission];
                break;

            case CommissionType::FLAT_FEE:
                $commission = $this->flat_fee_amount;
                $breakdown['base'] = ['type' => 'flat_fee', 'amount' => $commission];
                break;

            case CommissionType::HYBRID:
                $percentageAmount = $salePrice * ($this->base_percentage / 100);
                $commission = $percentageAmount + $this->flat_fee_amount;
                $breakdown['percentage'] = ['rate' => $this->base_percentage, 'amount' => $percentageAmount];
                $breakdown['flat_fee'] = ['amount' => $this->flat_fee_amount];
                break;

            case CommissionType::TIERED:
                $commission = $this->calculateTieredCommission($salePrice, $tierId);
                $breakdown['tiered'] = ['tier_id' => $tierId, 'amount' => $commission];
                break;

            case CommissionType::CATEGORY_BASED:
                $commission = $this->calculateCategoryCommission($salePrice, $categoryId);
                $breakdown['category'] = ['category_id' => $categoryId, 'amount' => $commission];
                break;

            case CommissionType::VOLUME_BASED:
                $commission = $this->calculateVolumeCommission($salePrice, $monthlyVolume);
                $breakdown['volume'] = ['monthly_volume' => $monthlyVolume, 'amount' => $commission];
                break;

            case CommissionType::CUSTOM:
                $result = $this->calculateCustomCommission($salePrice, [
                    'category_id' => $categoryId,
                    'tier_id' => $tierId,
                    'condition' => $condition,
                    'monthly_volume' => $monthlyVolume,
                ]);
                $commission = $result['amount'];
                $breakdown['custom'] = $result;
                break;
        }

        // Apply condition adjustments
        if ($condition && ! empty($this->condition_adjustments[$condition])) {
            $adjustment = $this->condition_adjustments[$condition];
            $adjustmentAmount = $commission * ($adjustment / 100);
            $commission += $adjustmentAmount;
            $breakdown['condition_adjustment'] = [
                'condition' => $condition,
                'adjustment' => $adjustment,
                'amount' => $adjustmentAmount,
            ];
        }

        // Apply volume bonuses
        if ($monthlyVolume && ! empty($this->volume_bonuses)) {
            $bonus = $this->calculateVolumeBonus($commission, $monthlyVolume);
            if ($bonus > 0) {
                $commission += $bonus;
                $breakdown['volume_bonus'] = ['monthly_volume' => $monthlyVolume, 'amount' => $bonus];
            }
        }

        // Apply min/max caps
        if ($this->minimum_commission && $commission < $this->minimum_commission) {
            $breakdown['adjusted_to_minimum'] = true;
            $commission = $this->minimum_commission;
        }

        if ($this->maximum_commission && $commission > $this->maximum_commission) {
            $breakdown['adjusted_to_maximum'] = true;
            $commission = $this->maximum_commission;
        }

        return [
            'commission' => round($commission, 2),
            'breakdown' => $breakdown,
            'structure_id' => $this->id,
            'structure_name' => $this->name,
        ];
    }

    protected function calculateTieredCommission(float $salePrice, ?string $tierId): float
    {
        if (! $tierId || empty($this->tier_adjustments)) {
            return $salePrice * ($this->base_percentage / 100);
        }

        $adjustment = $this->tier_adjustments[$tierId] ?? 0;
        $effectiveRate = $this->base_percentage + $adjustment;

        return $salePrice * ($effectiveRate / 100);
    }

    protected function calculateCategoryCommission(float $salePrice, ?string $categoryId): float
    {
        if (! $categoryId || empty($this->category_overrides)) {
            return $salePrice * ($this->base_percentage / 100);
        }

        $rate = $this->category_overrides[$categoryId] ?? $this->base_percentage;

        return $salePrice * ($rate / 100);
    }

    protected function calculateVolumeCommission(float $salePrice, ?int $monthlyVolume): float
    {
        $rate = $this->base_percentage;

        if ($monthlyVolume && ! empty($this->volume_bonuses)) {
            foreach ($this->volume_bonuses as $tier) {
                if ($monthlyVolume >= ($tier['min_volume'] ?? 0)) {
                    $rate = $tier['rate'] ?? $this->base_percentage;
                }
            }
        }

        return $salePrice * ($rate / 100);
    }

    protected function calculateVolumeBonus(float $baseCommission, int $monthlyVolume): float
    {
        if (empty($this->volume_bonuses)) {
            return 0;
        }

        $bonus = 0;
        foreach ($this->volume_bonuses as $tier) {
            if ($monthlyVolume >= ($tier['min_volume'] ?? 0) && isset($tier['bonus_percentage'])) {
                $bonus = $baseCommission * ($tier['bonus_percentage'] / 100);
            }
        }

        return $bonus;
    }

    protected function calculateCustomCommission(float $salePrice, array $context): array
    {
        // Custom rules are defined as JSON and can be extended
        // This is a placeholder for complex custom logic
        $rules = $this->custom_rules ?? [];
        $amount = $salePrice * ($this->base_percentage / 100);

        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $salePrice, $context)) {
                if (isset($rule['rate'])) {
                    $amount = $salePrice * ($rule['rate'] / 100);
                } elseif (isset($rule['amount'])) {
                    $amount = $rule['amount'];
                } elseif (isset($rule['adjustment'])) {
                    $amount += $amount * ($rule['adjustment'] / 100);
                }
            }
        }

        return [
            'amount' => $amount,
            'rules_applied' => count($rules),
        ];
    }

    protected function evaluateRule(array $rule, float $salePrice, array $context): bool
    {
        // Evaluate rule conditions
        if (isset($rule['min_price']) && $salePrice < $rule['min_price']) {
            return false;
        }
        if (isset($rule['max_price']) && $salePrice > $rule['max_price']) {
            return false;
        }
        if (isset($rule['category_id']) && $context['category_id'] !== $rule['category_id']) {
            return false;
        }
        if (isset($rule['tier_id']) && $context['tier_id'] !== $rule['tier_id']) {
            return false;
        }
        if (isset($rule['condition']) && $context['condition'] !== $rule['condition']) {
            return false;
        }

        return true;
    }

    public function isExcluded(?string $categoryId = null, ?string $condition = null): bool
    {
        if (empty($this->exclusions)) {
            return false;
        }

        if ($categoryId && in_array($categoryId, $this->exclusions['categories'] ?? [])) {
            return true;
        }

        if ($condition && in_array($condition, $this->exclusions['conditions'] ?? [])) {
            return true;
        }

        return false;
    }

    // Static Methods
    public static function findApplicable(
        int $organizationId,
        ?string $supplierId = null,
        ?string $categoryId = null
    ): ?self {
        // Priority: Supplier + Category > Supplier > Category > Default
        $query = self::active()
            ->forOrganization($organizationId)
            ->byPriority();

        // Try supplier + category specific
        if ($supplierId && $categoryId) {
            $structure = (clone $query)
                ->where('supplier_id', $supplierId)
                ->where('category_id', $categoryId)
                ->first();

            if ($structure) {
                return $structure;
            }
        }

        // Try supplier specific
        if ($supplierId) {
            $structure = (clone $query)
                ->where('supplier_id', $supplierId)
                ->whereNull('category_id')
                ->first();

            if ($structure) {
                return $structure;
            }
        }

        // Try category specific
        if ($categoryId) {
            $structure = (clone $query)
                ->whereNull('supplier_id')
                ->where('category_id', $categoryId)
                ->first();

            if ($structure) {
                return $structure;
            }
        }

        // Fall back to default
        return $query->default()->first();
    }
}
