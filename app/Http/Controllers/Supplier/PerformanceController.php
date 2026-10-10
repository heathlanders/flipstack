<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Resources\Supplier\PerformanceMetricsResource;
use App\Models\Inventory\Supplier;
use App\Models\Supplier\SupplierPerformanceMetric;
use App\Models\Supplier\SupplierTier;
use App\Models\Supplier\SupplierTierHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PerformanceController extends Controller
{
    /**
     * Display performance overview page
     */
    public function index(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            return redirect()->route('dashboard')
                ->with('error', 'Supplier account not found.');
        }

        // Get latest metrics
        $latestMetrics = SupplierPerformanceMetric::getLatestForSupplier($supplier->id);

        // Get historical metrics
        $historicalMetrics = SupplierPerformanceMetric::forSupplier($supplier->id)
            ->finalized()
            ->orderBy('evaluation_period_end', 'desc')
            ->take(12)
            ->get();

        // Get tier history
        $tierHistory = SupplierTierHistory::getSupplierTimeline($supplier->id, 10);

        // Get all tiers for reference
        $allTiers = SupplierTier::where('organization_id', $supplier->organization_id)
            ->active()
            ->ordered()
            ->get();

        // Build the current tier info
        $currentTierData = $supplier->currentTier ? [
            'name' => $supplier->currentTier->tier_name,
            'code' => $supplier->currentTier->tier_code,
            'min_score' => $supplier->currentTier->min_performance_score,
            'max_score' => $supplier->currentTier->max_performance_score,
        ] : null;

        // Determine next tier
        $nextTier = $supplier->currentTier?->getNextTier();
        $nextTierData = $nextTier ? [
            'name' => $nextTier->tier_name,
            'code' => $nextTier->tier_code,
            'min_score' => $nextTier->min_performance_score,
        ] : null;

        // Build trend data from historical metrics
        $trendData = $historicalMetrics->reverse()->values()->map(fn ($m) => [
            'overall_score' => round($m->overall_performance_score ?? 0, 1),
            'quality_score' => round($m->quality_score ?? 0, 1),
            'period' => $m->evaluation_period_start?->format('M Y'),
        ]);

        return Inertia::render('Supplier/Performance', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'current_tier' => $currentTierData,
                'performance_score' => $supplier->cached_performance_score,
                'next_evaluation' => $supplier->next_tier_evaluation,
            ],
            'metrics' => $latestMetrics ? [
                'overall_score' => round($latestMetrics->overall_performance_score ?? 0, 1),
                'items_sold' => $latestMetrics->items_sold,
                'total_sales' => round($latestMetrics->total_revenue ?? 0, 2),
                'acceptance_rate' => round($latestMetrics->approval_rate ?? 0, 1),
            ] : null,
            'tier' => $currentTierData,
            'nextTier' => $nextTierData,
            'trendData' => $trendData,
            'historicalMetrics' => PerformanceMetricsResource::collection($historicalMetrics),
            'tierHistory' => $tierHistory,
            'allTiers' => $allTiers->map(fn ($tier) => [
                'id' => $tier->id,
                'name' => $tier->tier_name,
                'code' => $tier->tier_code,
                'min_score' => $tier->min_performance_score,
                'max_score' => $tier->max_performance_score,
                'benefits' => $tier->benefits,
                'settlement_frequency' => $tier->settlement_frequency,
            ]),
        ]);
    }

    /**
     * Get detailed metrics for a specific period
     */
    public function show(Request $request, $metricId)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $metrics = SupplierPerformanceMetric::where('id', $metricId)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        return response()->json(new PerformanceMetricsResource($metrics));
    }

    /**
     * Get performance trends over time
     */
    public function trends(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();
        $months = $request->input('months', 12);

        $metrics = SupplierPerformanceMetric::forSupplier($supplier->id)
            ->finalized()
            ->where('evaluation_period_end', '>=', now()->subMonths($months))
            ->orderBy('evaluation_period_end')
            ->get();

        $trends = $metrics->map(fn ($m) => [
            'period' => $m->evaluation_period_start->format('M Y'),
            'overall_score' => $m->overall_performance_score,
            'quality_score' => $m->quality_score,
            'compliance_score' => $m->compliance_score,
            'customer_rating' => $m->customer_rating,
            'sell_through_rate' => $m->sell_through_rate,
            'return_rate' => $m->return_rate,
        ]);

        // Calculate trend directions
        $trendAnalysis = $this->analyzeTrends($metrics);

        return response()->json([
            'data' => $trends,
            'analysis' => $trendAnalysis,
        ]);
    }

    /**
     * Get score breakdown
     */
    public function scoreBreakdown(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $latestMetrics = SupplierPerformanceMetric::getLatestForSupplier($supplier->id);

        if (! $latestMetrics) {
            return response()->json([
                'error' => 'No performance metrics found',
            ], 404);
        }

        $weights = $latestMetrics->score_weights ?? SupplierPerformanceMetric::DEFAULT_WEIGHTS;

        $breakdown = [
            [
                'name' => 'Quality Score',
                'score' => $latestMetrics->quality_score,
                'weight' => $weights['quality_score'] * 100,
                'weighted_score' => round($latestMetrics->quality_score * $weights['quality_score'], 2),
                'description' => 'Overall quality of submitted items',
            ],
            [
                'name' => 'Sales Performance',
                'score' => $latestMetrics->calculateSalesPerformanceScore(),
                'weight' => $weights['sales_performance'] * 100,
                'weighted_score' => round($latestMetrics->calculateSalesPerformanceScore() * $weights['sales_performance'], 2),
                'description' => 'Sell-through rate and revenue metrics',
            ],
            [
                'name' => 'Compliance Score',
                'score' => $latestMetrics->compliance_score,
                'weight' => $weights['compliance_score'] * 100,
                'weighted_score' => round($latestMetrics->compliance_score * $weights['compliance_score'], 2),
                'description' => 'Policy adherence and documentation',
            ],
            [
                'name' => 'Customer Satisfaction',
                'score' => $latestMetrics->calculateCustomerSatisfactionScore(),
                'weight' => $weights['customer_satisfaction'] * 100,
                'weighted_score' => round($latestMetrics->calculateCustomerSatisfactionScore() * $weights['customer_satisfaction'], 2),
                'description' => 'Customer ratings and feedback',
            ],
            [
                'name' => 'Accuracy Score',
                'score' => $latestMetrics->accuracy_score,
                'weight' => $weights['accuracy_score'] * 100,
                'weighted_score' => round($latestMetrics->accuracy_score * $weights['accuracy_score'], 2),
                'description' => 'Product description and condition accuracy',
            ],
        ];

        return response()->json([
            'overall_score' => $latestMetrics->overall_performance_score,
            'breakdown' => $breakdown,
            'period' => [
                'start' => $latestMetrics->evaluation_period_start->format('M d, Y'),
                'end' => $latestMetrics->evaluation_period_end->format('M d, Y'),
            ],
        ]);
    }

    /**
     * Get tier requirements and progress
     */
    public function tierRequirements(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();
        $currentTier = $supplier->currentTier;

        if (! $currentTier) {
            return response()->json([
                'error' => 'No tier assigned',
            ], 404);
        }

        $latestMetrics = SupplierPerformanceMetric::getLatestForSupplier($supplier->id);
        $currentScore = $latestMetrics?->overall_performance_score ?? 0;

        $allTiers = SupplierTier::where('organization_id', $supplier->organization_id)
            ->active()
            ->ordered()
            ->get();

        $tierRequirements = $allTiers->map(function ($tier) use ($currentScore, $currentTier) {
            $isCurrent = $tier->id === $currentTier->id;
            $isAchievable = $currentScore >= $tier->min_performance_score;
            $pointsNeeded = max(0, $tier->min_performance_score - $currentScore);

            return [
                'id' => $tier->id,
                'name' => $tier->tier_name,
                'code' => $tier->tier_code,
                'min_score' => $tier->min_performance_score,
                'max_score' => $tier->max_performance_score,
                'is_current' => $isCurrent,
                'is_achievable' => $isAchievable,
                'points_needed' => $pointsNeeded,
                'benefits' => $tier->benefits ?? [],
                'requirements' => $tier->requirements ?? [],
                'settlement_frequency' => $tier->settlement_frequency,
                'commission_adjustment' => $tier->commission_adjustment,
            ];
        });

        return response()->json([
            'current_score' => $currentScore,
            'current_tier' => $currentTier->tier_name,
            'tiers' => $tierRequirements,
        ]);
    }

    /**
     * Get improvement recommendations
     */
    public function recommendations(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $latestMetrics = SupplierPerformanceMetric::getLatestForSupplier($supplier->id);

        if (! $latestMetrics) {
            return response()->json([
                'recommendations' => [
                    'Start submitting products to build your performance history.',
                    'Ensure all product descriptions are accurate and detailed.',
                    'Respond promptly to any issues or requests.',
                ],
                'strengths' => [],
                'weaknesses' => [],
            ]);
        }

        // Generate fresh recommendations if not cached
        if (empty($latestMetrics->improvement_recommendations)) {
            $latestMetrics->generateRecommendations();
            $latestMetrics->save();
        }

        return response()->json([
            'recommendations' => $latestMetrics->improvement_recommendations ?? [],
            'strengths' => $latestMetrics->strengths ?? [],
            'weaknesses' => $latestMetrics->weaknesses ?? [],
            'next_tier_requirements' => $this->getNextTierGap($supplier, $latestMetrics),
        ]);
    }

    /**
     * Get comparison with other suppliers (anonymized)
     */
    public function benchmark(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $latestMetrics = SupplierPerformanceMetric::getLatestForSupplier($supplier->id);

        if (! $latestMetrics) {
            return response()->json([
                'error' => 'No metrics available for comparison',
            ], 404);
        }

        // Get organization averages (anonymized)
        $orgAverages = SupplierPerformanceMetric::where('organization_id', $supplier->organization_id)
            ->where('status', 'finalized')
            ->where('evaluation_period_end', '>=', now()->subMonths(3))
            ->selectRaw('
                AVG(overall_performance_score) as avg_overall,
                AVG(quality_score) as avg_quality,
                AVG(compliance_score) as avg_compliance,
                AVG(customer_rating) as avg_customer_rating,
                AVG(sell_through_rate) as avg_sell_through,
                AVG(return_rate) as avg_return_rate
            ')
            ->first();

        // Calculate percentile ranking
        $totalSuppliers = SupplierPerformanceMetric::where('organization_id', $supplier->organization_id)
            ->where('status', 'finalized')
            ->where('evaluation_period_end', '>=', now()->subMonths(3))
            ->distinct('supplier_id')
            ->count();

        $suppliersBelow = SupplierPerformanceMetric::where('organization_id', $supplier->organization_id)
            ->where('status', 'finalized')
            ->where('evaluation_period_end', '>=', now()->subMonths(3))
            ->where('overall_performance_score', '<', $latestMetrics->overall_performance_score)
            ->distinct('supplier_id')
            ->count();

        $percentile = $totalSuppliers > 0
            ? round(($suppliersBelow / $totalSuppliers) * 100)
            : 0;

        return response()->json([
            'your_scores' => [
                'overall' => $latestMetrics->overall_performance_score,
                'quality' => $latestMetrics->quality_score,
                'compliance' => $latestMetrics->compliance_score,
                'customer_rating' => $latestMetrics->customer_rating,
                'sell_through' => $latestMetrics->sell_through_rate,
                'return_rate' => $latestMetrics->return_rate,
            ],
            'averages' => [
                'overall' => round($orgAverages->avg_overall ?? 0, 1),
                'quality' => round($orgAverages->avg_quality ?? 0, 1),
                'compliance' => round($orgAverages->avg_compliance ?? 0, 1),
                'customer_rating' => round($orgAverages->avg_customer_rating ?? 1, 1),
                'sell_through' => round($orgAverages->avg_sell_through ?? 0, 1),
                'return_rate' => round($orgAverages->avg_return_rate ?? 0, 1),
            ],
            'percentile' => $percentile,
            'total_suppliers' => $totalSuppliers,
        ]);
    }

    // ============================================
    // Protected Helper Methods
    // ============================================

    /**
     * Get the authenticated supplier
     */
    protected function getAuthenticatedSupplier(): ?Supplier
    {
        $user = Auth::user();

        if ($user->supplier_id) {
            return Supplier::with(['currentTier'])
                ->find($user->supplier_id);
        }

        return Supplier::where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->first();
    }

    /**
     * Analyze performance trends
     */
    protected function analyzeTrends($metrics): array
    {
        if ($metrics->count() < 2) {
            return [
                'direction' => 'stable',
                'message' => 'Not enough data to determine trend',
            ];
        }

        $scores = $metrics->pluck('overall_performance_score')->toArray();
        $recentAvg = array_sum(array_slice($scores, -3)) / min(3, count($scores));
        $olderAvg = array_sum(array_slice($scores, 0, 3)) / min(3, count($scores));

        $change = $recentAvg - $olderAvg;
        $percentChange = $olderAvg > 0 ? ($change / $olderAvg) * 100 : 0;

        if ($percentChange > 5) {
            return [
                'direction' => 'improving',
                'change' => round($percentChange, 1),
                'message' => 'Your performance is trending upward',
            ];
        } elseif ($percentChange < -5) {
            return [
                'direction' => 'declining',
                'change' => round($percentChange, 1),
                'message' => 'Your performance needs attention',
            ];
        } else {
            return [
                'direction' => 'stable',
                'change' => round($percentChange, 1),
                'message' => 'Your performance is consistent',
            ];
        }
    }

    /**
     * Get gap to next tier
     */
    protected function getNextTierGap(Supplier $supplier, SupplierPerformanceMetric $metrics): ?array
    {
        $currentTier = $supplier->currentTier;

        if (! $currentTier) {
            return null;
        }

        $nextTier = $currentTier->getNextTier();

        if (! $nextTier) {
            return [
                'message' => 'You are at the highest tier!',
                'points_needed' => 0,
            ];
        }

        $pointsNeeded = max(0, $nextTier->min_performance_score - $metrics->overall_performance_score);

        return [
            'next_tier' => $nextTier->tier_name,
            'points_needed' => $pointsNeeded,
            'current_score' => $metrics->overall_performance_score,
            'required_score' => $nextTier->min_performance_score,
            'benefits' => $nextTier->benefits ?? [],
        ];
    }
}
