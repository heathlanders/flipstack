<?php

namespace App\Services;

use App\Models\Inventory\Supplier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SupplierPreferenceService
 *
 * Handles supplier context analysis, reliability scoring, and preference management
 * for enhanced product intake processing
 */
class SupplierPreferenceService
{
    /**
     * Get comprehensive supplier context for confidence scoring
     */
    public function getSupplierContext(string $supplierId): array
    {
        return Cache::remember("supplier_context_{$supplierId}", 3600, function () use ($supplierId) {
            try {
                $supplier = Supplier::find($supplierId);

                if (! $supplier) {
                    return $this->getDefaultContext();
                }

                $reliabilityMetrics = $this->calculateReliabilityMetrics($supplierId);

                // If no processing history, use real supplier name with default metrics
                if ($reliabilityMetrics['total_items'] === 0) {
                    $defaultContext = $this->getDefaultContext();
                    $defaultContext['supplier_id'] = $supplierId;
                    $defaultContext['name'] = $supplier->name;  // ← Use real name!

                    return $defaultContext;
                }

                // Get processing history
                $processingHistory = $this->getProcessingHistory($supplierId);

                // Calculate reliability boost for confidence scoring
                $reliabilityBoost = $this->calculateReliabilityBoost($reliabilityMetrics);

                // Check for priority supplier status
                $priorityBoost = $this->isPrioritySupplier($supplier, $reliabilityMetrics);

                return [
                    'supplier_id' => $supplierId,
                    'name' => $supplier->name,
                    'reliability_score' => $reliabilityMetrics['reliability_score'],
                    'reliability_boost' => $reliabilityBoost,
                    'priority_boost' => $priorityBoost,
                    'preferred_conditions' => $this->getPreferredConditions($supplierId),
                    'average_quality' => $processingHistory['average_quality_score'],
                    'average_completeness' => $processingHistory['average_completeness'],
                    'total_items_processed' => $processingHistory['total_processed'],
                    'success_rate' => $processingHistory['success_rate'],
                    'processing_speed' => $processingHistory['avg_processing_time'],
                    'metrics' => $reliabilityMetrics,
                ];

            } catch (\Exception $e) {
                Log::error('Failed to get supplier context', [
                    'supplier_id' => $supplierId,
                    'error' => $e->getMessage(),
                ]);

                return $this->getDefaultContext();
            }
        });
    }

    /**
     * Calculate reliability metrics for a supplier
     */
    protected function calculateReliabilityMetrics(string $supplierId): array
    {
        $metrics = DB::table('product_intake_queue')
            ->where('supplier_id', $supplierId)
            ->where('ai_analysis_status', 'complete')
            ->selectRaw("
                COUNT(*) as total_items,
                AVG(ai_confidence_score) as avg_confidence,
                AVG(quality_score) as avg_quality,
                AVG(completeness_score) as avg_completeness,
                COUNT(CASE WHEN processing_status = 'ready_for_listing' THEN 1 END) as ready_items,
                COUNT(CASE WHEN processing_status = 'failed' THEN 1 END) as failed_items,
                AVG(CASE WHEN ai_completed_at IS NOT NULL AND ai_started_at IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (ai_completed_at - ai_started_at)) END) as avg_processing_seconds
            ")
            ->first();

        $totalItems = $metrics->total_items ?? 0;

        if ($totalItems === 0) {
            return $this->getDefaultMetrics();
        }

        // Calculate reliability score (0-100)
        $reliabilityScore = $this->calculateReliabilityScore($metrics);

        return [
            'total_items' => $totalItems,
            'avg_confidence' => round($metrics->avg_confidence ?? 0, 2),
            'avg_quality' => round($metrics->avg_quality ?? 0, 2),
            'avg_completeness' => round($metrics->avg_completeness ?? 0, 2),
            'success_rate' => round(($metrics->ready_items / $totalItems) * 100, 1),
            'failure_rate' => round(($metrics->failed_items / $totalItems) * 100, 1),
            'avg_processing_seconds' => round($metrics->avg_processing_seconds ?? 0),
            'reliability_score' => $reliabilityScore,
            'tier' => $this->getSupplierTier($reliabilityScore),
        ];
    }

    /**
     * Calculate overall reliability score (0-100)
     */
    protected function calculateReliabilityScore($metrics): float
    {
        $totalItems = $metrics->total_items;

        // Weighted scoring
        $scores = [
            'confidence' => ($metrics->avg_confidence ?? 0) * 100, // 0-100
            'quality' => $metrics->avg_quality ?? 0, // 0-100
            'completeness' => $metrics->avg_completeness ?? 0, // 0-100
            'success_rate' => ($metrics->ready_items / $totalItems) * 100, // 0-100
        ];

        // Weights for different factors
        $weights = [
            'confidence' => 0.25,
            'quality' => 0.30,
            'completeness' => 0.25,
            'success_rate' => 0.20,
        ];

        $weightedScore = 0;
        foreach ($scores as $metric => $score) {
            $weightedScore += $score * $weights[$metric];
        }

        // Bonus for volume (suppliers with more items get slight boost)
        $volumeBonus = min(10, $totalItems / 10); // Up to 10 point bonus for 100+ items

        return round(min(100, $weightedScore + $volumeBonus), 1);
    }

    /**
     * Calculate confidence boost for highly reliable suppliers
     */
    protected function calculateReliabilityBoost(array $metrics): float
    {
        $reliabilityScore = $metrics['reliability_score'];

        if ($reliabilityScore >= 90) {
            return 0.15; // 15% boost for excellent suppliers
        }

        if ($reliabilityScore >= 80) {
            return 0.10; // 10% boost for very good suppliers
        }

        if ($reliabilityScore >= 70) {
            return 0.05; // 5% boost for good suppliers
        }

        return 0; // No boost for unreliable suppliers
    }

    /**
     * Check if supplier qualifies for priority processing
     */
    protected function isPrioritySupplier(Supplier $supplier, array $metrics): bool
    {
        // Manual priority flag
        if ($supplier->is_priority ?? false) {
            return true;
        }

        // Automatic priority based on performance
        return $metrics['reliability_score'] >= 85
            && $metrics['total_items'] >= 50
            && $metrics['success_rate'] >= 80;
    }

    /**
     * Get supplier's preferred product conditions
     */
    protected function getPreferredConditions(string $supplierId): array
    {
        $conditions = DB::table('product_intake_queue')
            ->where('supplier_id', $supplierId)
            ->select('condition', DB::raw('COUNT(*) as count'), DB::raw('AVG(ai_confidence_score) as avg_confidence'))
            ->groupBy('condition')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return $conditions->map(function ($condition) {
            return [
                'condition' => $condition->condition,
                'frequency' => $condition->count,
                'avg_confidence' => round($condition->avg_confidence, 2),
            ];
        })->toArray();
    }

    /**
     * Get processing history summary
     */
    protected function getProcessingHistory(string $supplierId): array
    {
        $history = DB::table('product_intake_queue')
            ->where('supplier_id', $supplierId)
            ->selectRaw("
                COUNT(*) as total_processed,
                AVG(quality_score) as average_quality_score,
                AVG(completeness_score) as average_completeness,
                COUNT(CASE WHEN processing_status IN ('ready_for_listing', 'needs_minor_review') THEN 1 END) as successful_items,
                AVG(CASE WHEN ai_completed_at IS NOT NULL AND ai_started_at IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (ai_completed_at - ai_started_at)) END) as avg_processing_time
            ")
            ->first();

        $totalProcessed = $history->total_processed ?? 0;
        $successfulItems = $history->successful_items ?? 0;

        return [
            'total_processed' => $totalProcessed,
            'average_quality_score' => round($history->average_quality_score ?? 0, 1),
            'average_completeness' => round($history->average_completeness ?? 0, 1),
            'success_rate' => $totalProcessed > 0 ? round(($successfulItems / $totalProcessed) * 100, 1) : 0,
            'avg_processing_time' => round($history->avg_processing_time ?? 0),
        ];
    }

    /**
     * Get supplier tier based on reliability score
     */
    protected function getSupplierTier(float $reliabilityScore): string
    {
        if ($reliabilityScore >= 90) {
            return 'platinum';
        }
        if ($reliabilityScore >= 80) {
            return 'gold';
        }
        if ($reliabilityScore >= 70) {
            return 'silver';
        }
        if ($reliabilityScore >= 60) {
            return 'bronze';
        }

        return 'standard';
    }

    /**
     * Get default context for unknown/new suppliers
     */
    protected function getDefaultContext(): array
    {
        return [
            'supplier_id' => null,
            'name' => 'Unknown Supplier',
            'reliability_score' => 50.0,
            'reliability_boost' => 0,
            'priority_boost' => false,
            'preferred_conditions' => [],
            'average_quality' => 0,
            'average_completeness' => 0,
            'total_items_processed' => 0,
            'success_rate' => 0,
            'processing_speed' => 0,
            'metrics' => $this->getDefaultMetrics(),
        ];
    }

    /**
     * Get default metrics for new suppliers
     */
    protected function getDefaultMetrics(): array
    {
        return [
            'total_items' => 0,
            'avg_confidence' => 0,
            'avg_quality' => 0,
            'avg_completeness' => 0,
            'success_rate' => 0,
            'failure_rate' => 0,
            'avg_processing_seconds' => 0,
            'reliability_score' => 50.0,
            'tier' => 'standard',
        ];
    }

    /**
     * Update supplier metrics after processing an item
     */
    public function updateSupplierMetrics(string $supplierId): void
    {
        // Clear cache to force recalculation
        Cache::forget("supplier_context_{$supplierId}");

        Log::info('Supplier metrics cache cleared', ['supplier_id' => $supplierId]);
    }

    /**
     * Get top performing suppliers
     */
    public function getTopSuppliers(int $limit = 10): array
    {
        $suppliers = DB::table('suppliers as s')
            ->leftJoin('product_intake_queue as piq', 's.id', '=', 'piq.supplier_id')
            ->select('s.id', 's.name', 's.is_priority')
            ->addSelect([
                'total_items' => DB::raw('COUNT(piq.id)'),
                'avg_confidence' => DB::raw('AVG(piq.ai_confidence_score)'),
                'avg_quality' => DB::raw('AVG(piq.quality_score)'),
                'success_count' => DB::raw("COUNT(CASE WHEN piq.processing_status = 'ready_for_listing' THEN 1 END)"),
            ])
            ->where('piq.ai_analysis_status', 'complete')
            ->groupBy('s.id', 's.name', 's.is_priority')
            ->having('total_items', '>=', 5) // Minimum 5 items to qualify
            ->orderByDesc(DB::raw('(AVG(piq.ai_confidence_score) * 100 + AVG(piq.quality_score)) / 2'))
            ->limit($limit)
            ->get();

        return $suppliers->map(function ($supplier) {
            $successRate = $supplier->total_items > 0
                ? round(($supplier->success_count / $supplier->total_items) * 100, 1)
                : 0;

            return [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'is_priority' => $supplier->is_priority,
                'total_items' => $supplier->total_items,
                'avg_confidence' => round($supplier->avg_confidence * 100, 1),
                'avg_quality' => round($supplier->avg_quality, 1),
                'success_rate' => $successRate,
                'composite_score' => round(($supplier->avg_confidence * 100 + $supplier->avg_quality) / 2, 1),
            ];
        })->toArray();
    }

    /**
     * Get supplier performance dashboard data
     */
    public function getSupplierDashboard(string $supplierId): array
    {
        $context = $this->getSupplierContext($supplierId);

        // Recent performance trend (last 30 days)
        $recentTrend = DB::table('product_intake_queue')
            ->where('supplier_id', $supplierId)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw("
                DATE(created_at) as date,
                COUNT(*) as items_submitted,
                AVG(ai_confidence_score) as avg_confidence,
                AVG(quality_score) as avg_quality,
                COUNT(CASE WHEN processing_status = 'ready_for_listing' THEN 1 END) as ready_items
            ")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'supplier_context' => $context,
            'recent_trend' => $recentTrend,
            'recommendations' => $this->getSupplierRecommendations($context),
        ];
    }

    /**
     * Get recommendations for improving supplier performance
     */
    protected function getSupplierRecommendations(array $context): array
    {
        $recommendations = [];

        if ($context['average_completeness'] < 70) {
            $recommendations[] = [
                'type' => 'completeness',
                'priority' => 'high',
                'message' => 'Focus on providing complete product information. Current average: '.$context['average_completeness'].'%',
                'action' => 'Review product intake checklists and ensure all components are documented.',
            ];
        }

        if ($context['average_quality'] < 60) {
            $recommendations[] = [
                'type' => 'quality',
                'priority' => 'high',
                'message' => 'Product data quality needs improvement. Current average: '.$context['average_quality'],
                'action' => 'Provide clearer photos and more detailed product descriptions.',
            ];
        }

        if ($context['success_rate'] < 70) {
            $recommendations[] = [
                'type' => 'success_rate',
                'priority' => 'medium',
                'message' => 'Many items require manual review. Current success rate: '.$context['success_rate'].'%',
                'action' => 'Focus on providing accurate manufacturer part numbers and model information.',
            ];
        }

        if ($context['reliability_score'] >= 85) {
            $recommendations[] = [
                'type' => 'recognition',
                'priority' => 'info',
                'message' => 'Excellent performance! You\'re in the top tier of suppliers.',
                'action' => 'Consider applying for priority supplier status for faster processing.',
            ];
        }

        return $recommendations;
    }
}
