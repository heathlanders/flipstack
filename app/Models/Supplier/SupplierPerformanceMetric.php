<?php

namespace App\Models\Supplier;

use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPerformanceMetric extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'supplier_id',
        'organization_id',
        'evaluation_period_start',
        'evaluation_period_end',
        'period_type',
        'total_submissions',
        'approved_submissions',
        'rejected_submissions',
        'pending_submissions',
        'approval_rate',
        'total_items_listed',
        'total_items_sold',
        'sell_through_rate',
        'total_revenue',
        'average_sale_price',
        'average_days_to_sell',
        'total_returns',
        'return_rate',
        'return_value',
        'quality_score',
        'accuracy_score',
        'condition_accuracy',
        'packaging_score',
        'compliance_score',
        'policy_violations',
        'late_submissions',
        'documentation_issues',
        'customer_rating',
        'customer_complaints',
        'positive_feedback_count',
        'negative_feedback_count',
        'overall_performance_score',
        'score_breakdown',
        'score_weights',
        'current_tier_id',
        'previous_tier_id',
        'tier_change_date',
        'tier_change_reason',
        'tier_change_pending',
        'improvement_recommendations',
        'strengths',
        'weaknesses',
        'status',
        'calculated_by',
        'calculated_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'evaluation_period_start' => 'datetime',
        'evaluation_period_end' => 'datetime',
        'total_submissions' => 'integer',
        'approved_submissions' => 'integer',
        'rejected_submissions' => 'integer',
        'pending_submissions' => 'integer',
        'approval_rate' => 'decimal:2',
        'total_items_listed' => 'integer',
        'total_items_sold' => 'integer',
        'sell_through_rate' => 'decimal:2',
        'total_revenue' => 'decimal:2',
        'average_sale_price' => 'decimal:2',
        'average_days_to_sell' => 'decimal:2',
        'total_returns' => 'integer',
        'return_rate' => 'decimal:2',
        'return_value' => 'decimal:2',
        'quality_score' => 'decimal:2',
        'accuracy_score' => 'decimal:2',
        'condition_accuracy' => 'decimal:2',
        'packaging_score' => 'decimal:2',
        'compliance_score' => 'decimal:2',
        'policy_violations' => 'integer',
        'late_submissions' => 'integer',
        'documentation_issues' => 'integer',
        'customer_rating' => 'decimal:2',
        'customer_complaints' => 'integer',
        'positive_feedback_count' => 'integer',
        'negative_feedback_count' => 'integer',
        'overall_performance_score' => 'decimal:2',
        'score_breakdown' => 'array',
        'score_weights' => 'array',
        'tier_change_date' => 'datetime',
        'tier_change_pending' => 'boolean',
        'improvement_recommendations' => 'array',
        'strengths' => 'array',
        'weaknesses' => 'array',
        'calculated_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // Default weights for score calculation
    public const DEFAULT_WEIGHTS = [
        'quality_score' => 0.25,
        'sales_performance' => 0.25,
        'compliance_score' => 0.20,
        'customer_satisfaction' => 0.15,
        'accuracy_score' => 0.15,
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

    public function currentTier(): BelongsTo
    {
        return $this->belongsTo(SupplierTier::class, 'current_tier_id');
    }

    public function previousTier(): BelongsTo
    {
        return $this->belongsTo(SupplierTier::class, 'previous_tier_id');
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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

    public function scopeForPeriod($query, $start, $end)
    {
        return $query->where('evaluation_period_start', '>=', $start)
            ->where('evaluation_period_end', '<=', $end);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('evaluation_period_end', 'desc');
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeFinalized($query)
    {
        return $query->where('status', 'finalized');
    }

    // Methods
    public function calculateOverallScore(?array $weights = null): float
    {
        $weights = $weights ?? $this->score_weights ?? self::DEFAULT_WEIGHTS;

        $scores = [
            'quality_score' => $this->quality_score ?? 0,
            'sales_performance' => $this->calculateSalesPerformanceScore(),
            'compliance_score' => $this->compliance_score ?? 0,
            'customer_satisfaction' => $this->calculateCustomerSatisfactionScore(),
            'accuracy_score' => $this->accuracy_score ?? 0,
        ];

        $totalWeight = array_sum($weights);
        $weightedScore = 0;

        foreach ($scores as $metric => $score) {
            $weight = $weights[$metric] ?? 0;
            $weightedScore += ($score * $weight);
        }

        $overallScore = $totalWeight > 0 ? $weightedScore / $totalWeight : 0;

        $this->score_breakdown = [
            'individual_scores' => $scores,
            'weights' => $weights,
            'weighted_contributions' => array_map(
                fn ($score, $weight) => $score * $weight,
                $scores,
                $weights
            ),
        ];

        return round($overallScore, 2);
    }

    public function calculateSalesPerformanceScore(): float
    {
        $score = 0;
        $components = 0;

        // Sell-through rate (0-100)
        if ($this->sell_through_rate !== null) {
            $score += min($this->sell_through_rate, 100);
            $components++;
        }

        // Return rate inverse (lower is better)
        if ($this->return_rate !== null) {
            $returnScore = max(0, 100 - ($this->return_rate * 5)); // 20% returns = 0 score
            $score += $returnScore;
            $components++;
        }

        // Approval rate
        if ($this->approval_rate !== null) {
            $score += $this->approval_rate;
            $components++;
        }

        return $components > 0 ? round($score / $components, 2) : 0;
    }

    public function calculateCustomerSatisfactionScore(): float
    {
        $score = 0;
        $components = 0;

        // Customer rating (convert to 0-100)
        if ($this->customer_rating !== null && $this->customer_rating > 0) {
            $score += ($this->customer_rating / 5) * 100;
            $components++;
        }

        // Feedback ratio
        $totalFeedback = $this->positive_feedback_count + $this->negative_feedback_count;
        if ($totalFeedback > 0) {
            $feedbackScore = ($this->positive_feedback_count / $totalFeedback) * 100;
            $score += $feedbackScore;
            $components++;
        }

        // Complaint penalty
        if ($this->customer_complaints > 0) {
            $complaintPenalty = min($this->customer_complaints * 5, 50); // Max 50 point penalty
            $score -= $complaintPenalty;
        }

        return $components > 0 ? max(0, round($score / $components, 2)) : 0;
    }

    public function recalculate(): self
    {
        $this->overall_performance_score = $this->calculateOverallScore();
        $this->identifyStrengthsAndWeaknesses();
        $this->generateRecommendations();
        $this->calculated_at = now();
        $this->status = 'calculated';
        $this->save();

        return $this;
    }

    public function identifyStrengthsAndWeaknesses(): void
    {
        $scores = $this->score_breakdown['individual_scores'] ?? [];
        $strengths = [];
        $weaknesses = [];

        foreach ($scores as $metric => $score) {
            if ($score >= 80) {
                $strengths[] = [
                    'metric' => $metric,
                    'score' => $score,
                    'label' => $this->getMetricLabel($metric),
                ];
            } elseif ($score < 60) {
                $weaknesses[] = [
                    'metric' => $metric,
                    'score' => $score,
                    'label' => $this->getMetricLabel($metric),
                ];
            }
        }

        $this->strengths = $strengths;
        $this->weaknesses = $weaknesses;
    }

    public function generateRecommendations(): void
    {
        $recommendations = [];

        if ($this->approval_rate < 70) {
            $recommendations[] = [
                'priority' => 'high',
                'area' => 'submissions',
                'recommendation' => 'Improve product descriptions and condition accuracy to increase approval rates.',
            ];
        }

        if ($this->return_rate > 10) {
            $recommendations[] = [
                'priority' => 'high',
                'area' => 'returns',
                'recommendation' => 'Review product condition assessments and descriptions to reduce return rates.',
            ];
        }

        if ($this->compliance_score < 70) {
            $recommendations[] = [
                'priority' => 'medium',
                'area' => 'compliance',
                'recommendation' => 'Ensure all submissions include required documentation and meet policy requirements.',
            ];
        }

        if ($this->sell_through_rate < 50) {
            $recommendations[] = [
                'priority' => 'medium',
                'area' => 'pricing',
                'recommendation' => 'Consider reviewing pricing strategies or product selection to improve sell-through rates.',
            ];
        }

        if ($this->customer_complaints > 5) {
            $recommendations[] = [
                'priority' => 'high',
                'area' => 'customer_service',
                'recommendation' => 'Address customer complaint patterns to improve satisfaction scores.',
            ];
        }

        $this->improvement_recommendations = $recommendations;
    }

    protected function getMetricLabel(string $metric): string
    {
        return match ($metric) {
            'quality_score' => 'Product Quality',
            'sales_performance' => 'Sales Performance',
            'compliance_score' => 'Policy Compliance',
            'customer_satisfaction' => 'Customer Satisfaction',
            'accuracy_score' => 'Description Accuracy',
            default => ucwords(str_replace('_', ' ', $metric)),
        };
    }

    public function determineTierChange(): ?array
    {
        $supplier = $this->supplier;
        $currentTier = $supplier->currentTier;
        $newTier = SupplierTier::findByScore(
            $this->organization_id,
            $this->overall_performance_score
        );

        if (! $newTier || ! $currentTier) {
            return null;
        }

        if ($newTier->id === $currentTier->id) {
            return null;
        }

        $changeType = $newTier->sort_order < $currentTier->sort_order ? 'promotion' : 'demotion';

        return [
            'from_tier' => $currentTier,
            'to_tier' => $newTier,
            'change_type' => $changeType,
            'score' => $this->overall_performance_score,
        ];
    }

    public function approve(int $userId): self
    {
        $this->update([
            'status' => 'finalized',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);

        return $this;
    }

    // Static Methods
    public static function getLatestForSupplier(string $supplierId): ?self
    {
        return self::forSupplier($supplierId)
            ->finalized()
            ->latest()
            ->first();
    }

    public static function createForPeriod(
        string $supplierId,
        int $organizationId,
        \DateTime $periodStart,
        \DateTime $periodEnd,
        string $periodType = 'monthly'
    ): self {
        return self::create([
            'supplier_id' => $supplierId,
            'organization_id' => $organizationId,
            'evaluation_period_start' => $periodStart,
            'evaluation_period_end' => $periodEnd,
            'period_type' => $periodType,
            'status' => 'draft',
        ]);
    }
}
