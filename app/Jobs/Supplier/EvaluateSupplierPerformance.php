<?php

namespace App\Jobs\Supplier;

use App\Models\Catalog\Product;
use App\Models\Inventory\Supplier;
use App\Models\Supplier\SupplierPerformanceMetric;
use App\Services\Supplier\SupplierTierManagerService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EvaluateSupplierPerformance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 1800; // 30 minutes

    protected int $organizationId;

    protected Carbon $evaluationPeriodStart;

    protected Carbon $evaluationPeriodEnd;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $organizationId,
        ?Carbon $evaluationPeriodStart = null,
        ?Carbon $evaluationPeriodEnd = null
    ) {
        $this->organizationId = $organizationId;
        $this->evaluationPeriodStart = $evaluationPeriodStart ?? now()->subMonth()->startOfMonth();
        $this->evaluationPeriodEnd = $evaluationPeriodEnd ?? now()->subMonth()->endOfMonth();
    }

    /**
     * Execute the job.
     */
    public function handle(SupplierTierManagerService $tierManager): void
    {
        Log::info('Starting supplier performance evaluation', [
            'organization_id' => $this->organizationId,
            'period_start' => $this->evaluationPeriodStart->toDateString(),
            'period_end' => $this->evaluationPeriodEnd->toDateString(),
        ]);

        $suppliers = Supplier::where('organization_id', $this->organizationId)
            ->where('supplier_status', 'active')
            ->get();

        $results = [
            'processed' => 0,
            'metrics_created' => 0,
            'tier_changes' => 0,
            'errors' => 0,
        ];

        foreach ($suppliers as $supplier) {
            try {
                $metric = $this->createPerformanceMetric($supplier);
                $results['metrics_created']++;

                // Evaluate tier change
                $evaluation = $tierManager->evaluateSupplierTier($supplier, $metric);

                if ($evaluation['should_change'] && $evaluation['recommended_tier']) {
                    $tierManager->applyTierChange(
                        $supplier,
                        $evaluation['recommended_tier'],
                        $evaluation['change_type'] === 'promotion' ? 'promotion' : 'demotion',
                        $metric
                    );
                    $results['tier_changes']++;
                }

                $results['processed']++;

            } catch (\Exception $e) {
                $results['errors']++;
                Log::error('Error evaluating supplier performance', [
                    'supplier_id' => $supplier->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed supplier performance evaluation', $results);
    }

    /**
     * Create performance metric for supplier
     */
    protected function createPerformanceMetric(Supplier $supplier): SupplierPerformanceMetric
    {
        // Gather all metrics
        $submissions = $this->getSubmissionMetrics($supplier);
        $sales = $this->getSalesMetrics($supplier);
        $returns = $this->getReturnMetrics($supplier);
        $quality = $this->getQualityMetrics($supplier);
        $compliance = $this->getComplianceMetrics($supplier);
        $customer = $this->getCustomerMetrics($supplier);

        return DB::transaction(function () use (
            $supplier, $submissions, $sales, $returns, $quality, $compliance, $customer
        ) {
            $metric = SupplierPerformanceMetric::createForPeriod(
                $supplier,
                $this->evaluationPeriodStart,
                $this->evaluationPeriodEnd
            );

            $metric->update([
                // Submissions
                'total_submissions' => $submissions['total'],
                'approved_submissions' => $submissions['approved'],
                'rejected_submissions' => $submissions['rejected'],
                'pending_submissions' => $submissions['pending'],
                'approval_rate' => $submissions['approval_rate'],

                // Sales
                'items_listed' => $sales['items_listed'],
                'items_sold' => $sales['items_sold'],
                'sell_through_rate' => $sales['sell_through_rate'],
                'total_revenue' => $sales['total_revenue'],
                'average_sale_price' => $sales['average_sale_price'],
                'average_days_to_sell' => $sales['average_days_to_sell'],

                // Returns
                'total_returns' => $returns['total_returns'],
                'return_rate' => $returns['return_rate'],
                'return_value' => $returns['return_value'],

                // Quality
                'quality_score' => $quality['score'],
                'accuracy_score' => $quality['accuracy_score'],
                'condition_accuracy' => $quality['condition_accuracy'],
                'packaging_score' => $quality['packaging_score'],

                // Compliance
                'compliance_score' => $compliance['score'],
                'policy_violations' => $compliance['violations'],
                'late_submissions' => $compliance['late_submissions'],
                'documentation_issues' => $compliance['documentation_issues'],

                // Customer
                'customer_rating' => $customer['rating'],
                'customer_complaints' => $customer['complaints'],
                'positive_feedback_count' => $customer['positive_count'],
                'negative_feedback_count' => $customer['negative_count'],

                // Tier tracking
                'current_tier_id' => $supplier->current_tier_id,
            ]);

            // Calculate overall score
            $metric->recalculate();

            // Generate recommendations
            $metric->update([
                'improvement_recommendations' => $metric->generateRecommendations(),
            ]);

            // Identify strengths and weaknesses
            $analysis = $metric->identifyStrengthsAndWeaknesses();
            $metric->update([
                'strengths' => $analysis['strengths'],
                'weaknesses' => $analysis['weaknesses'],
            ]);

            return $metric->fresh();
        });
    }

    /**
     * Get submission metrics
     */
    protected function getSubmissionMetrics(Supplier $supplier): array
    {
        $stats = DB::table('product_intake_queue')
            ->where('supplier_id', $supplier->id)
            ->whereBetween('created_at', [$this->evaluationPeriodStart, $this->evaluationPeriodEnd])
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = \'approved\' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = \'rejected\' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) as pending
            ')
            ->first();

        $total = $stats->total ?? 0;

        return [
            'total' => $total,
            'approved' => $stats->approved ?? 0,
            'rejected' => $stats->rejected ?? 0,
            'pending' => $stats->pending ?? 0,
            'approval_rate' => $total > 0 ? (($stats->approved ?? 0) / $total) * 100 : 0,
        ];
    }

    /**
     * Get sales metrics
     */
    protected function getSalesMetrics(Supplier $supplier): array
    {
        // Items listed during period
        $itemsListed = Product::whereHas('supplierProduct', function ($query) use ($supplier) {
            $query->where('supplier_id', $supplier->id);
        })
            ->whereBetween('created_at', [$this->evaluationPeriodStart, $this->evaluationPeriodEnd])
            ->count();

        // Items sold during period
        $sales = Product::whereHas('supplierProduct', function ($query) use ($supplier) {
            $query->where('supplier_id', $supplier->id);
        })
            ->where('status', 'sold')
            ->whereBetween('sold_at', [$this->evaluationPeriodStart, $this->evaluationPeriodEnd])
            ->selectRaw('
                COUNT(*) as items_sold,
                COALESCE(SUM(final_price), 0) as total_revenue,
                COALESCE(AVG(final_price), 0) as avg_price,
                COALESCE(AVG(EXTRACT(DAY FROM (sold_at - created_at))), 0) as avg_days_to_sell
            ')
            ->first();

        $itemsSold = $sales->items_sold ?? 0;

        return [
            'items_listed' => $itemsListed,
            'items_sold' => $itemsSold,
            'sell_through_rate' => $itemsListed > 0 ? ($itemsSold / $itemsListed) * 100 : 0,
            'total_revenue' => $sales->total_revenue ?? 0,
            'average_sale_price' => $sales->avg_price ?? 0,
            'average_days_to_sell' => $sales->avg_days_to_sell ?? 0,
        ];
    }

    /**
     * Get return metrics
     */
    protected function getReturnMetrics(Supplier $supplier): array
    {
        $soldCount = Product::whereHas('supplierProduct', function ($query) use ($supplier) {
            $query->where('supplier_id', $supplier->id);
        })
            ->where('status', 'sold')
            ->whereBetween('sold_at', [$this->evaluationPeriodStart, $this->evaluationPeriodEnd])
            ->count();

        $returns = Product::whereHas('supplierProduct', function ($query) use ($supplier) {
            $query->where('supplier_id', $supplier->id);
        })
            ->where('status', 'returned')
            ->whereBetween('returned_at', [$this->evaluationPeriodStart, $this->evaluationPeriodEnd])
            ->selectRaw('
                COUNT(*) as total_returns,
                COALESCE(SUM(final_price), 0) as return_value
            ')
            ->first();

        $totalReturns = $returns->total_returns ?? 0;

        return [
            'total_returns' => $totalReturns,
            'return_rate' => $soldCount > 0 ? ($totalReturns / $soldCount) * 100 : 0,
            'return_value' => $returns->return_value ?? 0,
        ];
    }

    /**
     * Get quality metrics
     */
    protected function getQualityMetrics(Supplier $supplier): array
    {
        // Get quality scores from AI analysis
        $quality = DB::table('product_ai_analyses')
            ->join('products', 'product_ai_analyses.product_id', '=', 'products.id')
            ->join('supplier_products', 'products.id', '=', 'supplier_products.product_id')
            ->where('supplier_products.supplier_id', $supplier->id)
            ->whereBetween('product_ai_analyses.created_at', [
                $this->evaluationPeriodStart,
                $this->evaluationPeriodEnd,
            ])
            ->selectRaw('
                AVG(COALESCE(quality_score, 80)) as avg_quality,
                AVG(CASE WHEN condition_match = true THEN 100 ELSE 0 END) as condition_accuracy
            ')
            ->first();

        // Default scores if no data
        $qualityScore = $quality->avg_quality ?? 80;
        $conditionAccuracy = $quality->condition_accuracy ?? 85;

        return [
            'score' => round($qualityScore, 2),
            'accuracy_score' => round(($qualityScore + $conditionAccuracy) / 2, 2),
            'condition_accuracy' => round($conditionAccuracy, 2),
            'packaging_score' => 85, // Default - would come from inspection data
        ];
    }

    /**
     * Get compliance metrics
     */
    protected function getComplianceMetrics(Supplier $supplier): array
    {
        // Check for policy violations
        $violations = DB::table('supplier_compliance_logs')
            ->where('supplier_id', $supplier->id)
            ->whereBetween('created_at', [$this->evaluationPeriodStart, $this->evaluationPeriodEnd])
            ->where('type', 'violation')
            ->count();

        // Check for late submissions
        $lateSubmissions = DB::table('product_intake_queue')
            ->where('supplier_id', $supplier->id)
            ->whereBetween('created_at', [$this->evaluationPeriodStart, $this->evaluationPeriodEnd])
            ->whereNotNull('due_date')
            ->whereRaw('created_at > due_date')
            ->count();

        // Check for documentation issues
        $docIssues = DB::table('supplier_documents')
            ->where('supplier_id', $supplier->id)
            ->where('verification_status', 'rejected')
            ->whereBetween('created_at', [$this->evaluationPeriodStart, $this->evaluationPeriodEnd])
            ->count();

        // Calculate compliance score (starts at 100, deduct for issues)
        $score = 100;
        $score -= ($violations * 10);
        $score -= ($lateSubmissions * 2);
        $score -= ($docIssues * 5);
        $score = max(0, $score);

        return [
            'score' => $score,
            'violations' => $violations,
            'late_submissions' => $lateSubmissions,
            'documentation_issues' => $docIssues,
        ];
    }

    /**
     * Get customer metrics
     */
    protected function getCustomerMetrics(Supplier $supplier): array
    {
        // Get customer feedback from products
        $feedback = Product::whereHas('supplierProduct', function ($query) use ($supplier) {
            $query->where('supplier_id', $supplier->id);
        })
            ->whereNotNull('customer_rating')
            ->whereBetween('sold_at', [$this->evaluationPeriodStart, $this->evaluationPeriodEnd])
            ->selectRaw('
                AVG(customer_rating) as avg_rating,
                SUM(CASE WHEN customer_rating >= 4 THEN 1 ELSE 0 END) as positive_count,
                SUM(CASE WHEN customer_rating <= 2 THEN 1 ELSE 0 END) as negative_count
            ')
            ->first();

        // Get complaints
        $complaints = DB::table('customer_complaints')
            ->join('products', 'customer_complaints.product_id', '=', 'products.id')
            ->join('supplier_products', 'products.id', '=', 'supplier_products.product_id')
            ->where('supplier_products.supplier_id', $supplier->id)
            ->whereBetween('customer_complaints.created_at', [
                $this->evaluationPeriodStart,
                $this->evaluationPeriodEnd,
            ])
            ->count();

        return [
            'rating' => $feedback->avg_rating ?? 4.0,
            'complaints' => $complaints,
            'positive_count' => $feedback->positive_count ?? 0,
            'negative_count' => $feedback->negative_count ?? 0,
        ];
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'supplier-performance',
            'organization:'.$this->organizationId,
        ];
    }
}
