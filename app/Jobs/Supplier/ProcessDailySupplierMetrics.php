<?php

namespace App\Jobs\Supplier;

use App\Models\Catalog\Product;
use App\Models\Inventory\Supplier;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessDailySupplierMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600; // 10 minutes

    protected ?int $organizationId;

    protected ?string $supplierId;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $organizationId = null, ?string $supplierId = null)
    {
        $this->organizationId = $organizationId;
        $this->supplierId = $supplierId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting daily supplier metrics processing', [
            'organization_id' => $this->organizationId,
            'supplier_id' => $this->supplierId,
        ]);

        $query = Supplier::query()
            ->where('supplier_status', 'active');

        if ($this->organizationId) {
            $query->where('organization_id', $this->organizationId);
        }

        if ($this->supplierId) {
            $query->where('id', $this->supplierId);
        }

        $suppliers = $query->get();

        foreach ($suppliers as $supplier) {
            try {
                $this->processSupplierMetrics($supplier);
            } catch (\Exception $e) {
                Log::error('Error processing daily metrics for supplier', [
                    'supplier_id' => $supplier->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed daily supplier metrics processing', [
            'suppliers_processed' => $suppliers->count(),
        ]);
    }

    /**
     * Process metrics for a single supplier
     */
    protected function processSupplierMetrics(Supplier $supplier): void
    {
        $today = now();
        $startOfDay = $today->copy()->startOfDay();
        $endOfDay = $today->copy()->endOfDay();

        // Get daily submission stats
        $submissions = $this->getSubmissionStats($supplier, $startOfDay, $endOfDay);

        // Get daily sales stats
        $sales = $this->getSalesStats($supplier, $startOfDay, $endOfDay);

        // Get return stats
        $returns = $this->getReturnStats($supplier, $startOfDay, $endOfDay);

        // Update cached performance score
        $this->updateCachedScore($supplier, $submissions, $sales, $returns);

        Log::debug('Processed daily metrics for supplier', [
            'supplier_id' => $supplier->id,
            'submissions' => $submissions,
            'sales' => $sales,
            'returns' => $returns,
        ]);
    }

    /**
     * Get submission statistics for the day
     */
    protected function getSubmissionStats(Supplier $supplier, Carbon $start, Carbon $end): array
    {
        // Query product intake queue for submissions
        $stats = DB::table('product_intake_queue')
            ->where('supplier_id', $supplier->id)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = \'approved\' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = \'rejected\' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) as pending
            ')
            ->first();

        return [
            'total' => $stats->total ?? 0,
            'approved' => $stats->approved ?? 0,
            'rejected' => $stats->rejected ?? 0,
            'pending' => $stats->pending ?? 0,
            'approval_rate' => $stats->total > 0
                ? (($stats->approved ?? 0) / $stats->total) * 100
                : 0,
        ];
    }

    /**
     * Get sales statistics for the day
     */
    protected function getSalesStats(Supplier $supplier, Carbon $start, Carbon $end): array
    {
        $stats = Product::whereHas('supplierProduct', function ($query) use ($supplier) {
            $query->where('supplier_id', $supplier->id);
        })
            ->where('status', 'sold')
            ->whereBetween('sold_at', [$start, $end])
            ->selectRaw('
                COUNT(*) as items_sold,
                COALESCE(SUM(final_price), 0) as revenue,
                COALESCE(AVG(final_price), 0) as avg_price
            ')
            ->first();

        return [
            'items_sold' => $stats->items_sold ?? 0,
            'revenue' => $stats->revenue ?? 0,
            'avg_price' => $stats->avg_price ?? 0,
        ];
    }

    /**
     * Get return statistics for the day
     */
    protected function getReturnStats(Supplier $supplier, Carbon $start, Carbon $end): array
    {
        $returns = Product::whereHas('supplierProduct', function ($query) use ($supplier) {
            $query->where('supplier_id', $supplier->id);
        })
            ->where('status', 'returned')
            ->whereBetween('returned_at', [$start, $end])
            ->selectRaw('
                COUNT(*) as total_returns,
                COALESCE(SUM(final_price), 0) as return_value
            ')
            ->first();

        return [
            'total_returns' => $returns->total_returns ?? 0,
            'return_value' => $returns->return_value ?? 0,
        ];
    }

    /**
     * Update supplier's cached performance score
     */
    protected function updateCachedScore(
        Supplier $supplier,
        array $submissions,
        array $sales,
        array $returns
    ): void {
        // Get rolling 30-day metrics
        $thirtyDaysAgo = now()->subDays(30);

        $rollingSubmissions = DB::table('product_intake_queue')
            ->where('supplier_id', $supplier->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = \'approved\' THEN 1 ELSE 0 END) as approved
            ')
            ->first();

        $rollingSales = Product::whereHas('supplierProduct', function ($query) use ($supplier) {
            $query->where('supplier_id', $supplier->id);
        })
            ->where('status', 'sold')
            ->where('sold_at', '>=', $thirtyDaysAgo)
            ->count();

        $rollingReturns = Product::whereHas('supplierProduct', function ($query) use ($supplier) {
            $query->where('supplier_id', $supplier->id);
        })
            ->where('status', 'returned')
            ->where('returned_at', '>=', $thirtyDaysAgo)
            ->count();

        // Calculate simple performance score (0-100)
        $approvalRate = ($rollingSubmissions->total ?? 0) > 0
            ? (($rollingSubmissions->approved ?? 0) / $rollingSubmissions->total) * 100
            : 100;

        $returnRate = $rollingSales > 0
            ? ($rollingReturns / $rollingSales) * 100
            : 0;

        // Weighted score calculation
        $score = ($approvalRate * 0.4) + ((100 - $returnRate) * 0.3) + 50; // Base 50 points
        $score = min(100, max(0, $score));

        $supplier->update([
            'cached_performance_score' => round($score, 2),
            'performance_calculated_at' => now(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'supplier-metrics',
            'organization:'.($this->organizationId ?? 'all'),
            'supplier:'.($this->supplierId ?? 'all'),
        ];
    }
}
