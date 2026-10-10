<?php

namespace App\Models\Supplier;

use App\Enums\Supplier\SettlementFrequency;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierTier extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'tier_name',
        'tier_code',
        'description',
        'min_performance_score',
        'max_performance_score',
        'commission_adjustment',
        'settlement_frequency',
        'settlement_day',
        'benefits',
        'restrictions',
        'requirements',
        'minimum_volume',
        'evaluation_period_days',
        'color_code',
        'icon',
        'sort_order',
        'is_default',
        'is_active',
        'auto_promotion_enabled',
        'auto_demotion_enabled',
    ];

    protected $casts = [
        'min_performance_score' => 'decimal:2',
        'max_performance_score' => 'decimal:2',
        'commission_adjustment' => 'decimal:2',
        'benefits' => 'array',
        'restrictions' => 'array',
        'requirements' => 'array',
        'minimum_volume' => 'integer',
        'evaluation_period_days' => 'integer',
        'sort_order' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'auto_promotion_enabled' => 'boolean',
        'auto_demotion_enabled' => 'boolean',
    ];

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\Supplier::class, 'current_tier_id');
    }

    public function performanceMetrics(): HasMany
    {
        return $this->hasMany(SupplierPerformanceMetric::class, 'current_tier_id');
    }

    public function tierHistory(): HasMany
    {
        return $this->hasMany(SupplierTierHistory::class, 'to_tier_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    // Methods
    public function getSettlementFrequencyEnum(): SettlementFrequency
    {
        return SettlementFrequency::tryFrom($this->settlement_frequency) ?? SettlementFrequency::MONTHLY;
    }

    public function isScoreInRange(float $score): bool
    {
        return $score >= $this->min_performance_score && $score <= $this->max_performance_score;
    }

    public function getAdjustedCommissionRate(float $baseRate): float
    {
        return $baseRate + ($baseRate * ($this->commission_adjustment / 100));
    }

    public function hasBenefit(string $benefit): bool
    {
        return in_array($benefit, $this->benefits ?? []);
    }

    public function hasRestriction(string $restriction): bool
    {
        return in_array($restriction, $this->restrictions ?? []);
    }

    public function meetsRequirements(array $metrics): bool
    {
        if (empty($this->requirements)) {
            return true;
        }

        foreach ($this->requirements as $requirement => $threshold) {
            if (! isset($metrics[$requirement]) || $metrics[$requirement] < $threshold) {
                return false;
            }
        }

        return true;
    }

    public function getNextTier(): ?self
    {
        return self::where('organization_id', $this->organization_id)
            ->where('sort_order', '>', $this->sort_order)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->first();
    }

    public function getPreviousTier(): ?self
    {
        return self::where('organization_id', $this->organization_id)
            ->where('sort_order', '<', $this->sort_order)
            ->where('is_active', true)
            ->orderBy('sort_order', 'desc')
            ->first();
    }

    // Static Methods
    public static function findByScore(int $organizationId, float $score): ?self
    {
        return self::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where('min_performance_score', '<=', $score)
            ->where('max_performance_score', '>=', $score)
            ->first();
    }

    public static function getDefault(int $organizationId): ?self
    {
        return self::where('organization_id', $organizationId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    public static function getLowest(int $organizationId): ?self
    {
        return self::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('sort_order', 'desc')
            ->first();
    }

    public static function getHighest(int $organizationId): ?self
    {
        return self::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->first();
    }
}
