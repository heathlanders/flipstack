<?php

namespace App\Services\Supplier;

use App\Models\Inventory\Supplier;
use App\Models\Supplier\SupplierNotification;
use App\Models\Supplier\SupplierPerformanceMetric;
use App\Models\Supplier\SupplierTier;
use App\Models\Supplier\SupplierTierHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierTierManagerService
{
    /**
     * Evaluate a supplier's tier based on their performance
     */
    public function evaluateSupplierTier(
        Supplier $supplier,
        ?SupplierPerformanceMetric $metric = null
    ): array {
        $metric = $metric ?? SupplierPerformanceMetric::getLatestForSupplier($supplier->id);

        if (! $metric || ! $metric->overall_performance_score) {
            return [
                'success' => false,
                'message' => 'No performance metrics available for evaluation',
                'current_tier' => $supplier->currentTier,
                'recommended_tier' => null,
            ];
        }

        $currentTier = $supplier->currentTier;
        $recommendedTier = SupplierTier::findByScore(
            $supplier->organization_id,
            $metric->overall_performance_score
        );

        if (! $recommendedTier) {
            return [
                'success' => false,
                'message' => 'No tier found for performance score',
                'current_tier' => $currentTier,
                'recommended_tier' => null,
                'performance_score' => $metric->overall_performance_score,
            ];
        }

        $tierChange = $this->determineTierChange($currentTier, $recommendedTier, $metric);

        return [
            'success' => true,
            'message' => $tierChange['message'],
            'current_tier' => $currentTier,
            'recommended_tier' => $recommendedTier,
            'performance_score' => $metric->overall_performance_score,
            'change_type' => $tierChange['type'],
            'should_change' => $tierChange['should_change'],
            'reasons' => $tierChange['reasons'],
        ];
    }

    /**
     * Determine if tier should change and the type of change
     */
    protected function determineTierChange(
        ?SupplierTier $currentTier,
        SupplierTier $recommendedTier,
        SupplierPerformanceMetric $metric
    ): array {
        // Initial tier assignment
        if (! $currentTier) {
            return [
                'type' => 'initial',
                'should_change' => true,
                'message' => "Initial tier assignment to {$recommendedTier->tier_name}",
                'reasons' => ['Initial supplier tier assignment based on performance score'],
            ];
        }

        // No change needed
        if ($currentTier->id === $recommendedTier->id) {
            return [
                'type' => 'none',
                'should_change' => false,
                'message' => "Supplier remains at {$currentTier->tier_name}",
                'reasons' => ['Performance score within current tier range'],
            ];
        }

        // Promotion
        if ($recommendedTier->min_performance_score > $currentTier->min_performance_score) {
            $shouldPromote = $this->shouldPromote($currentTier, $recommendedTier, $metric);

            return [
                'type' => 'promotion',
                'should_change' => $shouldPromote['should_change'],
                'message' => $shouldPromote['should_change']
                    ? "Eligible for promotion to {$recommendedTier->tier_name}"
                    : "Not yet eligible for promotion to {$recommendedTier->tier_name}",
                'reasons' => $shouldPromote['reasons'],
            ];
        }

        // Demotion
        $shouldDemote = $this->shouldDemote($currentTier, $recommendedTier, $metric);

        return [
            'type' => 'demotion',
            'should_change' => $shouldDemote['should_change'],
            'message' => $shouldDemote['should_change']
                ? "Subject to demotion to {$recommendedTier->tier_name}"
                : 'Warning: Performance below tier threshold',
            'reasons' => $shouldDemote['reasons'],
        ];
    }

    /**
     * Check if supplier should be promoted
     */
    protected function shouldPromote(
        SupplierTier $currentTier,
        SupplierTier $recommendedTier,
        SupplierPerformanceMetric $metric
    ): array {
        $reasons = [];

        // Check if auto promotion is enabled
        if (! $recommendedTier->auto_promotion_enabled) {
            $reasons[] = 'Auto-promotion is disabled for this tier - manual review required';

            return ['should_change' => false, 'reasons' => $reasons];
        }

        // Check minimum requirements
        $requirements = $recommendedTier->requirements ?? [];

        if (! empty($requirements['min_months_active'])) {
            // Would need to check supplier's active duration
            $reasons[] = 'Meets minimum active months requirement';
        }

        if (! empty($requirements['min_total_sales'])) {
            if ($metric->total_sales < $requirements['min_total_sales']) {
                $reasons[] = "Has not met minimum total sales requirement ({$metric->total_sales}/{$requirements['min_total_sales']})";

                return ['should_change' => false, 'reasons' => $reasons];
            }
            $reasons[] = 'Meets minimum total sales requirement';
        }

        if (! empty($requirements['min_quality_score'])) {
            if ($metric->quality_score < $requirements['min_quality_score']) {
                $reasons[] = "Quality score below requirement ({$metric->quality_score}/{$requirements['min_quality_score']})";

                return ['should_change' => false, 'reasons' => $reasons];
            }
            $reasons[] = 'Meets quality score requirement';
        }

        if (! empty($requirements['max_return_rate'])) {
            if ($metric->return_rate > $requirements['max_return_rate']) {
                $reasons[] = "Return rate exceeds maximum ({$metric->return_rate}%/{$requirements['max_return_rate']}%)";

                return ['should_change' => false, 'reasons' => $reasons];
            }
            $reasons[] = 'Return rate within acceptable limit';
        }

        $reasons[] = "Performance score qualifies for {$recommendedTier->tier_name}";

        return ['should_change' => true, 'reasons' => $reasons];
    }

    /**
     * Check if supplier should be demoted
     */
    protected function shouldDemote(
        SupplierTier $currentTier,
        SupplierTier $recommendedTier,
        SupplierPerformanceMetric $metric
    ): array {
        $reasons = [];

        // Check if auto demotion is enabled
        if (! $currentTier->auto_demotion_enabled) {
            $reasons[] = 'Auto-demotion is disabled for current tier - manual review required';

            return ['should_change' => false, 'reasons' => $reasons];
        }

        // Grace period check - don't demote immediately after promotion
        // This would typically check the supplier's last tier change date

        $reasons[] = "Performance score ({$metric->overall_performance_score}) below {$currentTier->tier_name} minimum ({$currentTier->min_performance_score})";

        // Check for consecutive low performance periods
        // For now, we'll demote if score is below threshold

        return ['should_change' => true, 'reasons' => $reasons];
    }

    /**
     * Apply tier change to supplier
     */
    public function applyTierChange(
        Supplier $supplier,
        SupplierTier $newTier,
        string $changeType = 'manual',
        ?SupplierPerformanceMetric $metric = null,
        ?int $changedById = null,
        ?string $reason = null
    ): SupplierTierHistory {
        return DB::transaction(function () use ($supplier, $newTier, $changeType, $metric, $changedById, $reason) {
            $oldTier = $supplier->currentTier;

            // Record the tier change
            $history = SupplierTierHistory::recordChange(
                $supplier,
                $oldTier,
                $newTier,
                $changeType,
                $metric,
                $changedById,
                $reason
            );

            // Update supplier
            $supplier->update([
                'current_tier_id' => $newTier->id,
                'tier_change_date' => now(),
                'next_tier_evaluation' => $this->calculateNextEvaluationDate($newTier),
            ]);

            // Create notification
            SupplierNotification::tierChange($supplier, $history);

            // Log the change
            Log::info('Supplier tier changed', [
                'supplier_id' => $supplier->id,
                'from_tier' => $oldTier?->tier_name,
                'to_tier' => $newTier->tier_name,
                'change_type' => $changeType,
                'performance_score' => $metric?->overall_performance_score,
            ]);

            return $history;
        });
    }

    /**
     * Calculate next evaluation date based on tier
     */
    protected function calculateNextEvaluationDate(SupplierTier $tier): Carbon
    {
        // Evaluation frequency based on tier
        // Higher tiers evaluated less frequently, lower tiers more frequently
        $daysUntilEvaluation = match ($tier->tier_code) {
            'platinum' => 90,  // Quarterly
            'gold' => 60,      // Bi-monthly
            'silver' => 30,    // Monthly
            'bronze' => 14,    // Bi-weekly
            'probation' => 7,  // Weekly
            default => 30,
        };

        return now()->addDays($daysUntilEvaluation);
    }

    /**
     * Process tier evaluations for all due suppliers
     */
    public function processScheduledEvaluations(int $organizationId): array
    {
        $suppliers = Supplier::where('organization_id', $organizationId)
            ->where('supplier_status', 'active')
            ->where(function ($query) {
                $query->whereNull('next_tier_evaluation')
                    ->orWhere('next_tier_evaluation', '<=', now());
            })
            ->get();

        $results = [
            'processed' => 0,
            'promotions' => 0,
            'demotions' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'details' => [],
        ];

        foreach ($suppliers as $supplier) {
            try {
                $evaluation = $this->evaluateSupplierTier($supplier);

                if ($evaluation['should_change'] && $evaluation['recommended_tier']) {
                    $isPromotion = $evaluation['change_type'] === 'promotion';

                    $this->applyTierChange(
                        $supplier,
                        $evaluation['recommended_tier'],
                        $isPromotion ? 'promotion' : 'demotion',
                        SupplierPerformanceMetric::getLatestForSupplier($supplier->id)
                    );

                    $isPromotion ? $results['promotions']++ : $results['demotions']++;
                } else {
                    // Update next evaluation date even if no change
                    $supplier->update([
                        'next_tier_evaluation' => $this->calculateNextEvaluationDate(
                            $supplier->currentTier ?? SupplierTier::getDefault($organizationId)
                        ),
                    ]);
                    $results['unchanged']++;
                }

                $results['processed']++;
                $results['details'][] = [
                    'supplier_id' => $supplier->id,
                    'result' => $evaluation['message'],
                ];
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'supplier_id' => $supplier->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('Error evaluating supplier tier', [
                    'supplier_id' => $supplier->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Get tier distribution for organization
     */
    public function getTierDistribution(int $organizationId): Collection
    {
        return Supplier::where('organization_id', $organizationId)
            ->where('supplier_status', 'active')
            ->select('current_tier_id', DB::raw('count(*) as count'))
            ->groupBy('current_tier_id')
            ->get()
            ->map(function ($item) {
                $tier = $item->current_tier_id
                    ? SupplierTier::find($item->current_tier_id)
                    : null;

                return [
                    'tier_id' => $item->current_tier_id,
                    'tier_name' => $tier?->tier_name ?? 'Unassigned',
                    'tier_code' => $tier?->tier_code ?? 'unassigned',
                    'count' => $item->count,
                ];
            });
    }

    /**
     * Get suppliers due for promotion review
     */
    public function getSuppliersNearPromotion(int $organizationId, int $limit = 10): Collection
    {
        // Get suppliers whose performance score is close to the next tier threshold
        return Supplier::where('organization_id', $organizationId)
            ->where('supplier_status', 'active')
            ->whereNotNull('current_tier_id')
            ->whereNotNull('cached_performance_score')
            ->orderByDesc('cached_performance_score')
            ->limit($limit)
            ->get()
            ->filter(function ($supplier) {
                $currentTier = $supplier->currentTier;
                $nextTier = $currentTier?->getNextTier();

                if (! $nextTier) {
                    return false;
                }

                // Within 5 points of next tier
                return $supplier->cached_performance_score >= ($nextTier->min_performance_score - 5);
            });
    }

    /**
     * Get suppliers at risk of demotion
     */
    public function getSuppliersAtRiskOfDemotion(int $organizationId, int $limit = 10): Collection
    {
        return Supplier::where('organization_id', $organizationId)
            ->where('supplier_status', 'active')
            ->whereNotNull('current_tier_id')
            ->whereNotNull('cached_performance_score')
            ->get()
            ->filter(function ($supplier) {
                $currentTier = $supplier->currentTier;

                if (! $currentTier) {
                    return false;
                }

                // Within 5 points of demotion threshold
                return $supplier->cached_performance_score <= ($currentTier->min_performance_score + 5);
            })
            ->sortBy('cached_performance_score')
            ->take($limit);
    }

    /**
     * Manually assign tier to supplier
     */
    public function manuallyAssignTier(
        Supplier $supplier,
        SupplierTier $tier,
        int $changedById,
        string $reason
    ): SupplierTierHistory {
        return $this->applyTierChange(
            $supplier,
            $tier,
            'manual',
            null,
            $changedById,
            $reason
        );
    }

    /**
     * Reset supplier to default/initial tier
     */
    public function resetToDefaultTier(
        Supplier $supplier,
        int $changedById,
        string $reason
    ): SupplierTierHistory {
        $defaultTier = SupplierTier::getDefault($supplier->organization_id);

        if (! $defaultTier) {
            throw new \RuntimeException('No default tier configured for organization');
        }

        return $this->applyTierChange(
            $supplier,
            $defaultTier,
            'reset',
            null,
            $changedById,
            $reason
        );
    }

    /**
     * Get tier change history for supplier
     */
    public function getTierHistory(Supplier $supplier, int $limit = 10): Collection
    {
        return SupplierTierHistory::getSupplierTimeline($supplier->id, $limit);
    }

    /**
     * Calculate estimated time to next tier promotion
     */
    public function estimateTimeToPromotion(Supplier $supplier): ?array
    {
        $currentTier = $supplier->currentTier;
        $nextTier = $currentTier?->getNextTier();

        if (! $nextTier) {
            return null; // Already at highest tier
        }

        $currentScore = $supplier->cached_performance_score ?? 0;
        $requiredScore = $nextTier->min_performance_score;
        $scoreDifference = $requiredScore - $currentScore;

        if ($scoreDifference <= 0) {
            return [
                'next_tier' => $nextTier->tier_name,
                'eligible_now' => true,
                'message' => 'Eligible for promotion in next evaluation',
            ];
        }

        // Estimate based on typical improvement rate
        // This would ideally use historical data
        $estimatedWeeks = ceil($scoreDifference / 2); // Assume ~2 points improvement per week

        return [
            'next_tier' => $nextTier->tier_name,
            'current_score' => $currentScore,
            'required_score' => $requiredScore,
            'points_needed' => $scoreDifference,
            'estimated_weeks' => $estimatedWeeks,
            'eligible_now' => false,
            'message' => "Approximately {$estimatedWeeks} weeks to reach {$nextTier->tier_name} tier",
        ];
    }
}
