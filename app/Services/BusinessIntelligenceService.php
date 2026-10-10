<?php

namespace App\Services;

use App\Models\Catalog\ProductIntakeQueue;
use Carbon\Carbon;

/**
 * ============================================================================
 * BUSINESS INTELLIGENCE & ROI TRACKER
 * ============================================================================
 *
 * Tracks financial impact, efficiency gains, and system performance
 * for executive dashboards and investor reports.
 */
class BusinessIntelligenceService
{
    // Cost constants (adjust to your actual costs)
    private const LABOR_COST_PER_HOUR = 15.00;

    private const EBAY_LISTING_FEE = 0.35;

    private const EBAY_FINAL_VALUE_FEE_PERCENT = 12.9;

    private const ERROR_RELISTING_COST = 2.50;

    /**
     * Calculate comprehensive ROI metrics
     */
    public function calculateROI(Carbon $startDate, Carbon $endDate): array
    {
        $oldSystemData = $this->getOldSystemMetrics($startDate, $endDate);
        $newSystemData = $this->getNewSystemMetrics($startDate, $endDate);

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $startDate->diffInDays($endDate),
            ],

            'efficiency_gains' => $this->calculateEfficiencyGains($oldSystemData, $newSystemData),
            'cost_savings' => $this->calculateCostSavings($oldSystemData, $newSystemData),
            'quality_improvements' => $this->calculateQualityImprovements($oldSystemData, $newSystemData),
            'revenue_impact' => $this->calculateRevenueImpact($oldSystemData, $newSystemData),

            'total_roi' => $this->calculateTotalROI($oldSystemData, $newSystemData),
        ];
    }

    /**
     * Get metrics before optimization
     */
    private function getOldSystemMetrics(Carbon $start, Carbon $end): array
    {
        // These are baseline estimates - adjust based on your actual pre-optimization data
        return [
            'items_processed' => 1000,
            'avg_time_per_item_minutes' => 18,
            'manual_review_rate' => 0.65,
            'first_time_success_rate' => 0.75,
            'avg_quality_score' => 62,
            'ebay_ready_rate' => 0.50,
        ];
    }

    /**
     * Get metrics after optimization
     */
    private function getNewSystemMetrics(Carbon $start, Carbon $end): array
    {
        $items = ProductIntakeQueue::whereBetween('last_processed_at', [$start, $end])
            ->whereNotNull('quality_score')
            ->get();

        if ($items->isEmpty()) {
            return [
                'items_processed' => 0,
                'avg_time_per_item_minutes' => 0,
                'manual_review_rate' => 0,
                'first_time_success_rate' => 0,
                'avg_quality_score' => 0,
                'ebay_ready_rate' => 0,
            ];
        }

        $totalProcessed = $items->count();
        $ebayReady = $items->where('enriched_data.validation.is_complete', true)->count();
        $needsReview = $items->where('processing_status', 'needs_review')->count();
        $failed = $items->where('processing_status', 'failed')->count();

        // Calculate average processing time
        $avgProcessingTime = $items->map(function ($item) {
            if ($item->ai_started_at && $item->ai_completed_at) {
                return $item->ai_started_at->diffInMinutes($item->ai_completed_at);
            }

            return 5; // Default estimate
        })->avg();

        return [
            'items_processed' => $totalProcessed,
            'avg_time_per_item_minutes' => $avgProcessingTime,
            'manual_review_rate' => $needsReview / $totalProcessed,
            'first_time_success_rate' => ($totalProcessed - $failed) / $totalProcessed,
            'avg_quality_score' => $items->avg('quality_score'),
            'ebay_ready_rate' => $ebayReady / $totalProcessed,
        ];
    }

    /**
     * Calculate efficiency gains
     */
    private function calculateEfficiencyGains(array $old, array $new): array
    {
        $timeReductionPerItem = $old['avg_time_per_item_minutes'] - $new['avg_time_per_item_minutes'];
        $totalTimeSaved = $timeReductionPerItem * $new['items_processed'];

        $reviewReduction = ($old['manual_review_rate'] - $new['manual_review_rate']) * $new['items_processed'];

        return [
            'time_saved_per_item_minutes' => round($timeReductionPerItem, 2),
            'time_saved_percentage' => round(($timeReductionPerItem / $old['avg_time_per_item_minutes']) * 100, 1),
            'total_time_saved_hours' => round($totalTimeSaved / 60, 2),
            'manual_review_reduction' => round($reviewReduction, 0),
            'manual_review_reduction_percentage' => round((($old['manual_review_rate'] - $new['manual_review_rate']) / $old['manual_review_rate']) * 100, 1),
            'throughput_increase_percentage' => round(((1 / ($new['avg_time_per_item_minutes'] / $old['avg_time_per_item_minutes'])) - 1) * 100, 1),
        ];
    }

    /**
     * Calculate cost savings
     */
    private function calculateCostSavings(array $old, array $new): array
    {
        $efficiencyGains = $this->calculateEfficiencyGains($old, $new);

        // Labor savings
        $laborSavings = $efficiencyGains['total_time_saved_hours'] * self::LABOR_COST_PER_HOUR;

        // Error reduction savings
        $errorRate = 1 - $new['first_time_success_rate'];
        $oldErrorRate = 1 - $old['first_time_success_rate'];
        $errorReduction = ($oldErrorRate - $errorRate) * $new['items_processed'];
        $errorSavings = $errorReduction * self::ERROR_RELISTING_COST;

        // eBay fee savings (fewer relists)
        $feeSavings = $errorReduction * self::EBAY_LISTING_FEE;

        $totalSavings = $laborSavings + $errorSavings + $feeSavings;

        return [
            'labor_savings' => round($laborSavings, 2),
            'error_reduction_savings' => round($errorSavings, 2),
            'ebay_fee_savings' => round($feeSavings, 2),
            'total_cost_savings' => round($totalSavings, 2),
            'cost_per_item_old' => round(($old['avg_time_per_item_minutes'] / 60) * self::LABOR_COST_PER_HOUR, 2),
            'cost_per_item_new' => round(($new['avg_time_per_item_minutes'] / 60) * self::LABOR_COST_PER_HOUR, 2),
            'cost_reduction_per_item' => round(
                (($old['avg_time_per_item_minutes'] - $new['avg_time_per_item_minutes']) / 60) * self::LABOR_COST_PER_HOUR,
                2
            ),
        ];
    }

    /**
     * Calculate quality improvements
     */
    private function calculateQualityImprovements(array $old, array $new): array
    {
        $qualityIncrease = $new['avg_quality_score'] - $old['avg_quality_score'];
        $ebayReadyIncrease = $new['ebay_ready_rate'] - $old['ebay_ready_rate'];

        return [
            'quality_score_increase' => round($qualityIncrease, 1),
            'quality_score_increase_percentage' => round(($qualityIncrease / $old['avg_quality_score']) * 100, 1),
            'ebay_ready_increase' => round($ebayReadyIncrease * 100, 1),
            'ebay_ready_increase_percentage' => round(($ebayReadyIncrease / $old['ebay_ready_rate']) * 100, 1),
            'first_time_success_improvement' => round(($new['first_time_success_rate'] - $old['first_time_success_rate']) * 100, 1),
        ];
    }

    /**
     * Calculate revenue impact
     */
    private function calculateRevenueImpact(array $old, array $new): array
    {
        $efficiencyGains = $this->calculateEfficiencyGains($old, $new);

        // Faster listings = earlier sales = more revenue per period
        $additionalCapacity = $efficiencyGains['throughput_increase_percentage'] / 100 * $new['items_processed'];

        // Assume average sale price of $200 and 70% sell-through rate
        $avgSalePrice = 200;
        $sellThroughRate = 0.70;

        $additionalRevenue = $additionalCapacity * $avgSalePrice * $sellThroughRate;

        // Better quality listings = higher conversion rate (estimate +5% conversion improvement)
        $qualityImprovementFactor = 0.05;
        $conversionImprovementRevenue = $new['items_processed'] * $avgSalePrice * $sellThroughRate * $qualityImprovementFactor;

        $totalRevenueImpact = $additionalRevenue + $conversionImprovementRevenue;

        return [
            'additional_capacity_items' => round($additionalCapacity, 0),
            'additional_capacity_revenue' => round($additionalRevenue, 2),
            'quality_improvement_revenue' => round($conversionImprovementRevenue, 2),
            'total_revenue_impact' => round($totalRevenueImpact, 2),
            'faster_time_to_market_days' => round($efficiencyGains['time_saved_per_item_minutes'] / 60 / 8, 1), // Convert to working days
        ];
    }

    /**
     * Calculate total ROI
     */
    private function calculateTotalROI(array $old, array $new): array
    {
        $costSavings = $this->calculateCostSavings($old, $new);
        $revenueImpact = $this->calculateRevenueImpact($old, $new);

        $totalBenefit = $costSavings['total_cost_savings'] + $revenueImpact['total_revenue_impact'];

        // Implementation cost (one-time + ongoing)
        $implementationCost = 5000; // One-time setup cost
        $monthlyOperatingCost = 200; // Ongoing maintenance

        $daysInPeriod = 30; // Assuming monthly analysis
        $operatingCost = $monthlyOperatingCost * ($daysInPeriod / 30);

        $totalCost = $implementationCost + $operatingCost;
        $netBenefit = $totalBenefit - $totalCost;
        $roi = ($netBenefit / $totalCost) * 100;

        // Payback period
        $monthlyBenefit = $totalBenefit / ($daysInPeriod / 30);
        $paybackMonths = $implementationCost / $monthlyBenefit;

        return [
            'total_benefit' => round($totalBenefit, 2),
            'total_cost' => round($totalCost, 2),
            'net_benefit' => round($netBenefit, 2),
            'roi_percentage' => round($roi, 1),
            'payback_period_months' => round($paybackMonths, 1),
            'annualized_benefit' => round($totalBenefit * (365 / $daysInPeriod), 2),
            'break_even' => $netBenefit > 0,
        ];
    }

    /**
     * Generate executive summary
     */
    public function generateExecutiveSummary(Carbon $startDate, Carbon $endDate): array
    {
        $roi = $this->calculateROI($startDate, $endDate);

        return [
            'headline_metrics' => [
                'roi_percentage' => $roi['total_roi']['roi_percentage'].'%',
                'total_savings' => '$'.number_format($roi['total_roi']['net_benefit'], 2),
                'payback_period' => $roi['total_roi']['payback_period_months'].' months',
                'annualized_value' => '$'.number_format($roi['total_roi']['annualized_benefit'], 2),
            ],

            'key_improvements' => [
                'Processing speed: '.$roi['efficiency_gains']['time_saved_percentage'].'% faster',
                'Manual review: '.$roi['efficiency_gains']['manual_review_reduction_percentage'].'% reduction',
                'Quality score: +'.$roi['quality_improvements']['quality_score_increase'].' points',
                'eBay ready: +'.$roi['quality_improvements']['ebay_ready_increase'].'%',
            ],

            'financial_impact' => [
                'Monthly cost savings' => '$'.number_format($roi['cost_savings']['total_cost_savings'], 2),
                'Revenue opportunity' => '$'.number_format($roi['revenue_impact']['total_revenue_impact'], 2),
                'Additional capacity' => $roi['revenue_impact']['additional_capacity_items'].' items/month',
            ],

            'competitive_advantage' => [
                'Time to market: '.$roi['revenue_impact']['faster_time_to_market_days'].' days faster',
                'Throughput increase: +'.$roi['efficiency_gains']['throughput_increase_percentage'].'%',
                'First-time success: +'.$roi['quality_improvements']['first_time_success_improvement'].'%',
            ],

            'full_roi_data' => $roi,
        ];
    }

    /**
     * Get daily performance snapshot
     */
    public function getDailySnapshot(Carbon $date): array
    {
        $items = ProductIntakeQueue::whereDate('last_processed_at', $date)->get();

        if ($items->isEmpty()) {
            return [
                'date' => $date->format('Y-m-d'),
                'no_data' => true,
            ];
        }

        return [
            'date' => $date->format('Y-m-d'),
            'total_processed' => $items->count(),
            'avg_quality_score' => round($items->avg('quality_score'), 1),
            'ebay_ready_count' => $items->where('enriched_data.validation.is_complete', true)->count(),
            'ebay_ready_percentage' => round(($items->where('enriched_data.validation.is_complete', true)->count() / $items->count()) * 100, 1),
            'failed_count' => $items->where('processing_status', 'failed')->count(),
            'needs_review_count' => $items->where('processing_status', 'needs_review')->count(),
            'avg_processing_time_minutes' => round($items->map(function ($item) {
                if ($item->ai_started_at && $item->ai_completed_at) {
                    return $item->ai_started_at->diffInMinutes($item->ai_completed_at);
                }

                return 0;
            })->avg(), 1),
        ];
    }

    /**
     * Get weekly comparison
     */
    public function getWeeklyComparison(): array
    {
        $thisWeek = $this->getWeekMetrics(now());
        $lastWeek = $this->getWeekMetrics(now()->subWeek());

        return [
            'this_week' => $thisWeek,
            'last_week' => $lastWeek,
            'changes' => [
                'volume' => $thisWeek['total_processed'] - $lastWeek['total_processed'],
                'quality' => round($thisWeek['avg_quality_score'] - $lastWeek['avg_quality_score'], 1),
                'ebay_ready_rate' => round($thisWeek['ebay_ready_percentage'] - $lastWeek['ebay_ready_percentage'], 1),
            ],
        ];
    }

    private function getWeekMetrics(Carbon $date): array
    {
        $startOfWeek = $date->copy()->startOfWeek();
        $endOfWeek = $date->copy()->endOfWeek();

        $items = ProductIntakeQueue::whereBetween('last_processed_at', [$startOfWeek, $endOfWeek])->get();

        if ($items->isEmpty()) {
            return [
                'total_processed' => 0,
                'avg_quality_score' => 0,
                'ebay_ready_percentage' => 0,
            ];
        }

        return [
            'total_processed' => $items->count(),
            'avg_quality_score' => round($items->avg('quality_score'), 1),
            'ebay_ready_percentage' => round(($items->where('enriched_data.validation.is_complete', true)->count() / $items->count()) * 100, 1),
        ];
    }
}

/**
 * ============================================================================
 * ARTISAN COMMAND: Generate ROI Report
 * ============================================================================
 *
 * Usage: php artisan roi:report --period=30
 */

namespace App\Console\Commands;

use App\Services\BusinessIntelligenceService;
use Illuminate\Console\Command;

class GenerateROIReport extends Command
{
    protected $signature = 'roi:report {--period=30 : Number of days to analyze} {--format=table : Output format (table|json|csv)}';

    protected $description = 'Generate ROI report for optimization system';

    public function handle(BusinessIntelligenceService $bi)
    {
        $period = $this->option('period');
        $format = $this->option('format');

        $endDate = now();
        $startDate = now()->subDays($period);

        $this->info("Generating ROI Report ({$period} days)");
        $this->newLine();

        $summary = $bi->generateExecutiveSummary($startDate, $endDate);

        if ($format === 'json') {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        // Display in readable format
        $this->info('HEADLINE METRICS');
        foreach ($summary['headline_metrics'] as $key => $value) {
            $this->line('  '.ucwords(str_replace('_', ' ', $key)).": {$value}");
        }

        $this->newLine();
        $this->info('KEY IMPROVEMENTS');
        foreach ($summary['key_improvements'] as $improvement) {
            $this->line("  {$improvement}");
        }

        $this->newLine();
        $this->info('FINANCIAL IMPACT');
        foreach ($summary['financial_impact'] as $key => $value) {
            $this->line("  {$key}: {$value}");
        }

        $this->newLine();
        $this->info('COMPETITIVE ADVANTAGE');
        foreach ($summary['competitive_advantage'] as $advantage) {
            $this->line("  {$advantage}");
        }

        $this->newLine();

        if ($summary['full_roi_data']['total_roi']['break_even']) {
            $this->info('SYSTEM IS PROFITABLE');
        } else {
            $this->warn(' System not yet at break-even (needs more time)');
        }

        $this->newLine();
        $this->line('Full data saved to: storage/logs/roi_report_'.now()->format('Y-m-d').'.json');

        // Save to file
        file_put_contents(
            storage_path('logs/roi_report_'.now()->format('Y-m-d').'.json'),
            json_encode($summary, JSON_PRETTY_PRINT)
        );

        return Command::SUCCESS;
    }
}
