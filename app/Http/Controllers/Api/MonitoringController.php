<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Catalog\ProductIntakeQueue;
use App\Services\EbayRateLimiter;
use Illuminate\Http\Request;

/**
 * Monitoring API Controller
 * Provides endpoints for the optimization dashboard
 */
class MonitoringController extends Controller
{
    public function __construct(
        private EbayRateLimiter $rateLimiter
    ) {}

    /**
     * GET /api/v1/monitoring/rate-limit-status
     */
    public function rateLimitStatus()
    {
        return response()->json($this->rateLimiter->getStatus());
    }

    /**
     * GET /api/v1/monitoring/quality-metrics
     */
    public function qualityMetrics(Request $request)
    {
        $teamId = $request->user()->currentTeam->id;

        // Get stats for last 100 items
        $recentItems = ProductIntakeQueue::forTeam($teamId)
            ->whereNotNull('quality_score')
            ->latest('last_processed_at')
            ->limit(100)
            ->get();

        $totalProcessed = $recentItems->count();
        $ebayReadyCount = $recentItems->where('validation.is_complete', true)->count();
        $averageQualityScore = round($recentItems->avg('quality_score'), 1);

        // Calculate processing rate (items per minute in last hour)
        $lastHourCount = ProductIntakeQueue::forTeam($teamId)
            ->where('last_processed_at', '>=', now()->subHour())
            ->count();
        $processingRate = round($lastHourCount / 60, 1);

        return response()->json([
            'averageQualityScore' => $averageQualityScore,
            'ebayReadyPercentage' => $totalProcessed > 0
                ? round(($ebayReadyCount / $totalProcessed) * 100, 1)
                : 0,
            'ebayReadyCount' => $ebayReadyCount,
            'totalProcessed' => $totalProcessed,
            'processingRate' => $processingRate,
        ]);
    }

    /**
     * GET /api/v1/monitoring/recent-activity
     */
    public function recentActivity(Request $request)
    {
        $teamId = $request->user()->currentTeam->id;

        $items = ProductIntakeQueue::forTeam($teamId)
            ->with('capturedBy')
            ->whereNotNull('last_processed_at')
            ->latest('last_processed_at')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'preview_name' => $item->enriched_data['merged_product_fields']['product_identity']['title']
                        ?? $item->enriched_data['merged_product_fields']['name']
                        ?? 'Unknown Product',
                    'quality_score' => $item->quality_score ?? 0,
                    'last_processed_at' => $item->last_processed_at,
                    'data_sources' => $item->data_sources ?? [],
                    'missing_fields' => $item->missing_fields ?? [],
                    'validation' => $item->enriched_data['validation'] ?? null,
                ];
            });

        // Calculate distribution
        $distribution = $this->calculateQualityDistribution($teamId);

        return response()->json([
            'items' => $items,
            'distribution' => $distribution,
        ]);
    }

    /**
     * GET /api/v1/monitoring/recent-errors
     */
    public function recentErrors(Request $request)
    {
        $teamId = $request->user()->currentTeam->id;

        $errors = ProductIntakeQueue::forTeam($teamId)
            ->where('processing_status', 'failed')
            ->latest('last_processed_at')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'error_message' => $item->enriched_data['error'] ?? 'Unknown error',
                    'failed_at' => $item->last_processed_at,
                    'attempts' => $item->processing_attempts ?? 0,
                ];
            });

        return response()->json($errors);
    }

    /**
     * GET /api/v1/monitoring/source-performance
     */
    public function sourcePerformance(Request $request)
    {
        $teamId = $request->user()->currentTeam->id;

        // Analyze which sources are being used and their success rates
        $items = ProductIntakeQueue::forTeam($teamId)
            ->whereNotNull('data_sources')
            ->where('last_processed_at', '>=', now()->subDays(7))
            ->get();

        $sourceStats = [
            'ebay_api' => ['name' => 'eBay API', 'success' => 0, 'total' => 0, 'response_times' => []],
            'gemini_ai' => ['name' => 'Gemini AI', 'success' => 0, 'total' => 0, 'response_times' => []],
            'upc_lookup' => ['name' => 'UPC Database', 'success' => 0, 'total' => 0, 'response_times' => []],
            'background_removal' => ['name' => 'Background Removal', 'success' => 0, 'total' => 0, 'response_times' => []],
        ];

        foreach ($items as $item) {
            $sources = $item->data_sources ?? [];
            $isSuccess = $item->processing_status !== 'failed';

            foreach ($sources as $source) {
                if (isset($sourceStats[$source])) {
                    $sourceStats[$source]['total']++;
                    if ($isSuccess) {
                        $sourceStats[$source]['success']++;
                    }
                }
            }
        }

        // Calculate metrics
        $metrics = [];
        foreach ($sourceStats as $key => $stats) {
            if ($stats['total'] > 0) {
                $successRate = round(($stats['success'] / $stats['total']) * 100, 1);
                $metrics[] = [
                    'name' => $stats['name'],
                    'success_rate' => $successRate,
                    'avg_response_time' => rand(100, 500), // Placeholder - implement actual timing
                    'calls_today' => $stats['total'],
                    'is_healthy' => $successRate >= 90,
                ];
            }
        }

        return response()->json($metrics);
    }

    /**
     * POST /api/v1/intake-queue/{id}/retry
     */
    public function retryItem(Request $request, ProductIntakeQueue $queueItem)
    {
        $this->authorize('update', $queueItem);

        // Reset status and re-dispatch
        $queueItem->update([
            'processing_status' => 'pending',
            'ai_analysis_status' => 'pending',
            'enriched_data' => null,
        ]);

        \App\Jobs\ProcessQueueItem::dispatch($queueItem);

        return response()->json([
            'success' => true,
            'message' => 'Item queued for reprocessing',
        ]);
    }

    /**
     * Calculate quality score distribution
     */
    private function calculateQualityDistribution(int $teamId): array
    {
        $ranges = [
            '0-20' => 0,
            '21-40' => 0,
            '41-60' => 0,
            '61-80' => 0,
            '81-100' => 0,
        ];

        $items = ProductIntakeQueue::forTeam($teamId)
            ->whereNotNull('quality_score')
            ->where('last_processed_at', '>=', now()->subDays(7))
            ->pluck('quality_score');

        foreach ($items as $score) {
            if ($score <= 20) {
                $ranges['0-20']++;
            } elseif ($score <= 40) {
                $ranges['21-40']++;
            } elseif ($score <= 60) {
                $ranges['41-60']++;
            } elseif ($score <= 80) {
                $ranges['61-80']++;
            } else {
                $ranges['81-100']++;
            }
        }

        return $ranges;
    }
}

// ============================================================================
// ARTISAN COMMANDS
// ============================================================================

/**
 * FILE: app/Console/Commands/OptimizationMonitor.php
 *
 * Monitor optimization system health
 *
 * Usage: php artisan optimization:monitor
 */

namespace App\Console\Commands;

use App\Models\Catalog\ProductIntakeQueue;
use App\Services\EbayRateLimiter;
use Illuminate\Console\Command;

class OptimizationMonitor extends Command
{
    protected $signature = 'optimization:monitor';

    protected $description = 'Monitor optimization system health and performance';

    public function handle(EbayRateLimiter $rateLimiter)
    {
        $this->info('Optimization System Health Check');
        $this->newLine();

        // Rate Limiter Status
        $this->info('Rate Limiter Status:');
        $status = $rateLimiter->getStatus();

        $this->line("  Daily Usage: {$status['daily']['used']}/{$status['daily']['limit']} ({$status['daily']['percentage_used']}%)");
        $this->line("  Per-Second Available: {$status['per_second']['available']}/{$status['per_second']['limit']}");

        if ($status['daily']['percentage_used'] > 80) {
            $this->warn('   Warning: Approaching daily limit!');
        } else {
            $this->info('  Healthy');
        }

        $this->newLine();

        // Processing Stats
        $this->info('Processing Statistics (Last 24 Hours):');

        $processed = ProductIntakeQueue::where('last_processed_at', '>=', now()->subDay())->count();
        $successful = ProductIntakeQueue::where('last_processed_at', '>=', now()->subDay())
            ->where('processing_status', '!=', 'failed')
            ->count();
        $failed = ProductIntakeQueue::where('last_processed_at', '>=', now()->subDay())
            ->where('processing_status', 'failed')
            ->count();

        $this->line("  Total Processed: {$processed}");
        $this->line("  Successful: {$successful}");
        $this->line("  Failed: {$failed}");

        if ($processed > 0) {
            $successRate = round(($successful / $processed) * 100, 1);
            $this->line("  Success Rate: {$successRate}%");

            if ($successRate < 90) {
                $this->warn('   Warning: Success rate below 90%!');
            } else {
                $this->info('  Healthy');
            }
        }

        $this->newLine();

        // Quality Metrics
        $this->info('⭐ Quality Metrics:');

        $avgQuality = ProductIntakeQueue::whereNotNull('quality_score')
            ->where('last_processed_at', '>=', now()->subDay())
            ->avg('quality_score');

        $ebayReady = ProductIntakeQueue::where('last_processed_at', '>=', now()->subDay())
            ->whereRaw("enriched_data->'validation'->>'is_complete' = 'true'")
            ->count();

        $this->line('  Average Quality Score: '.round($avgQuality, 1).'/100');
        $this->line("  eBay Ready: {$ebayReady}/{$processed}");

        if ($avgQuality < 60) {
            $this->warn('   Warning: Average quality score below 60!');
        } else {
            $this->info('  Healthy');
        }

        $this->newLine();
        $this->info('Health check complete!');

        return Command::SUCCESS;
    }
}

/**
 * FILE: app/Console/Commands/ReprocessFailedItems.php
 *
 * Reprocess failed queue items
 *
 * Usage: php artisan optimization:reprocess-failed --limit=10
 */

namespace App\Console\Commands;

use App\Jobs\ProcessQueueItem;
use App\Models\Catalog\ProductIntakeQueue;
use Illuminate\Console\Command;

class ReprocessFailedItems extends Command
{
    protected $signature = 'optimization:reprocess-failed {--limit=10 : Number of items to reprocess}';

    protected $description = 'Reprocess failed queue items with optimized processor';

    public function handle()
    {
        $limit = $this->option('limit');

        $failedItems = ProductIntakeQueue::where('processing_status', 'failed')
            ->where('processing_attempts', '<', 3)
            ->limit($limit)
            ->get();

        if ($failedItems->isEmpty()) {
            $this->info('No failed items to reprocess.');

            return Command::SUCCESS;
        }

        $this->info("Found {$failedItems->count()} failed items to reprocess.");

        $bar = $this->output->createProgressBar($failedItems->count());
        $bar->start();

        foreach ($failedItems as $item) {
            // Reset status
            $item->update([
                'processing_status' => 'pending',
                'ai_analysis_status' => 'pending',
            ]);

            // Dispatch job
            ProcessQueueItem::dispatch($item);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('All items queued for reprocessing!');

        return Command::SUCCESS;
    }
}

/**
 * FILE: app/Console/Commands/AnalyzeQualityTrends.php
 *
 * Analyze quality trends over time
 *
 * Usage: php artisan optimization:analyze-trends --days=7
 */

namespace App\Console\Commands;

use App\Models\Catalog\ProductIntakeQueue;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AnalyzeQualityTrends extends Command
{
    protected $signature = 'optimization:analyze-trends {--days=7 : Number of days to analyze}';

    protected $description = 'Analyze quality score trends over time';

    public function handle()
    {
        $days = $this->option('days');

        $this->info("Quality Trends Analysis (Last {$days} Days)");
        $this->newLine();

        $headers = ['Date', 'Processed', 'Avg Quality', 'eBay Ready %', 'Failed'];
        $rows = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dateStr = $date->format('Y-m-d');

            $items = ProductIntakeQueue::whereDate('last_processed_at', $date)->get();

            if ($items->isEmpty()) {
                continue;
            }

            $processed = $items->count();
            $avgQuality = round($items->avg('quality_score'), 1);
            $ebayReady = $items->where('enriched_data.validation.is_complete', true)->count();
            $ebayReadyPct = round(($ebayReady / $processed) * 100, 1);
            $failed = $items->where('processing_status', 'failed')->count();

            $rows[] = [
                $dateStr,
                $processed,
                $avgQuality,
                $ebayReadyPct.'%',
                $failed,
            ];
        }

        $this->table($headers, $rows);

        // Calculate improvement
        if (count($rows) >= 2) {
            $firstDay = $rows[0];
            $lastDay = $rows[count($rows) - 1];

            $qualityImprovement = $lastDay[2] - $firstDay[2];

            $this->newLine();
            if ($qualityImprovement > 0) {
                $this->info("Quality improved by {$qualityImprovement} points!");
            } elseif ($qualityImprovement < 0) {
                $this->warn(' Quality decreased by '.abs($qualityImprovement).' points.');
            } else {
                $this->line('Quality remained stable.');
            }
        }

        return Command::SUCCESS;
    }
}
