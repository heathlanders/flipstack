<?php

namespace App\Jobs\Analytics;

use App\Models\Analytics\MarketplaceHealthSnapshot;
use App\Models\Analytics\WorkforcePerformanceDaily;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AggregateDailyKPIs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        protected string $organizationId,
        protected string $date
    ) {
        $this->queue = 'analytics';
    }

    public function handle(): void
    {
        try {
            $this->aggregateWorkforce();
            $this->aggregateMarketplaceHealth();
        } catch (\Throwable $e) {
            Log::error("KPI aggregation failed for org {$this->organizationId} on {$this->date}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function aggregateWorkforce(): void
    {
        $users = DB::table('fulfillment_logs')
            ->where('organization_id', $this->organizationId)
            ->whereDate('created_at', $this->date)
            ->distinct()
            ->pluck('user_id')
            ->filter();

        foreach ($users as $userId) {
            $logs = DB::table('fulfillment_logs')
                ->where('organization_id', $this->organizationId)
                ->where('user_id', $userId)
                ->whereDate('created_at', $this->date);

            $pickCompleted = (clone $logs)->where('action', 'pick_completed')->count();
            $itemsPicked = (clone $logs)->where('action', 'item_picked')->count();
            $pickErrors = (clone $logs)->where('action', 'pick_shorted')->count();
            $packCompleted = (clone $logs)->where('action', 'pack_completed')->count();
            $itemsPacked = (clone $logs)->where('action', 'item_packed')->count();
            $packErrors = (clone $logs)->whereIn('action', ['label_voided'])->count();
            $labelsPurchased = (clone $logs)->where('action', 'label_purchased')->count();
            $shipmentsCreated = (clone $logs)->where('action', 'shipment_created')->count();
            $returnsProcessed = (clone $logs)->whereIn('action', ['return_received'])->count();

            $avgPickDuration = $this->calculateAvgDuration($userId, 'pick_started', 'pick_completed');
            $avgPackDuration = $this->calculateAvgDuration($userId, 'pack_started', 'pack_completed');

            $totalActiveSeconds = 0;
            if ($avgPickDuration && $pickCompleted > 0) {
                $totalActiveSeconds += $avgPickDuration * $pickCompleted;
            }
            if ($avgPackDuration && $packCompleted > 0) {
                $totalActiveSeconds += $avgPackDuration * $packCompleted;
            }

            WorkforcePerformanceDaily::updateOrCreate(
                [
                    'organization_id' => $this->organizationId,
                    'user_id' => $userId,
                    'performance_date' => $this->date,
                ],
                [
                    'orders_picked' => $pickCompleted,
                    'items_picked' => $itemsPicked,
                    'pick_errors' => $pickErrors,
                    'avg_pick_duration_seconds' => $avgPickDuration,
                    'orders_packed' => $packCompleted,
                    'items_packed' => $itemsPacked,
                    'pack_errors' => $packErrors,
                    'avg_pack_duration_seconds' => $avgPackDuration,
                    'labels_purchased' => $labelsPurchased,
                    'shipments_created' => $shipmentsCreated,
                    'returns_processed' => $returnsProcessed,
                    'total_active_seconds' => (int) $totalActiveSeconds,
                ]
            );
        }
    }

    protected function calculateAvgDuration(int $userId, string $startAction, string $endAction): ?int
    {
        $result = DB::select('
            SELECT AVG(EXTRACT(EPOCH FROM (e.created_at - s.created_at))) as avg_seconds
            FROM fulfillment_logs s
            JOIN fulfillment_logs e ON s.order_id = e.order_id
                AND e.user_id = s.user_id
                AND e.action = ?
                AND e.created_at > s.created_at
            WHERE s.organization_id = ?
                AND s.user_id = ?
                AND s.action = ?
                AND DATE(s.created_at) = ?
        ', [$endAction, $this->organizationId, $userId, $startAction, $this->date]);

        $avg = $result[0]->avg_seconds ?? null;

        return $avg !== null ? (int) round((float) $avg) : null;
    }

    protected function aggregateMarketplaceHealth(): void
    {
        $marketplaces = ['ebay', 'shopify', 'amazon'];
        $logTables = [
            'ebay' => 'ebay_sync_logs',
            'shopify' => 'shopify_sync_logs',
            'amazon' => 'amazon_sync_logs',
        ];
        $listingTables = [
            'ebay' => 'ebay_listings',
            'shopify' => 'shopify_listings',
        ];

        foreach ($marketplaces as $marketplace) {
            $logTable = $logTables[$marketplace] ?? null;
            if (! $logTable || ! DB::getSchemaBuilder()->hasTable($logTable)) {
                continue;
            }

            $syncStats = DB::table($logTable)
                ->where('organization_id', $this->organizationId)
                ->whereDate('created_at', $this->date)
                ->select([
                    DB::raw('COUNT(*) as sync_attempts'),
                    DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as sync_successes"),
                    DB::raw("SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as sync_failures"),
                    DB::raw('COALESCE(AVG(response_time_ms), 0) as avg_response_time_ms'),
                    DB::raw('COALESCE(PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY COALESCE(response_time_ms, 0)), 0) as p95_response_time_ms'),
                ])
                ->first();

            $listingTable = $listingTables[$marketplace] ?? null;
            $totalListings = 0;
            $activeListings = 0;
            $errorListings = 0;

            if ($listingTable && DB::getSchemaBuilder()->hasTable($listingTable)) {
                $listingStats = DB::table($listingTable)
                    ->where('organization_id', $this->organizationId)
                    ->select([
                        DB::raw('COUNT(*) as total'),
                        DB::raw("SUM(CASE WHEN status IN ('published', 'active') THEN 1 ELSE 0 END) as active"),
                        DB::raw("SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors"),
                    ])
                    ->first();

                $totalListings = (int) ($listingStats->total ?? 0);
                $activeListings = (int) ($listingStats->active ?? 0);
                $errorListings = (int) ($listingStats->errors ?? 0);
            }

            $revenue = DB::table('orders')
                ->where('organization_id', $this->organizationId)
                ->where('marketplace', $marketplace)
                ->whereDate('created_at', $this->date)
                ->whereNotIn('status', ['cancelled'])
                ->sum('total_amount');

            $fees = DB::table('marketplace_fees')
                ->where('organization_id', $this->organizationId)
                ->where('marketplace', $marketplace)
                ->whereDate('fee_date', $this->date)
                ->sum('amount');

            $ordersImported = DB::table('orders')
                ->where('organization_id', $this->organizationId)
                ->where('marketplace', $marketplace)
                ->whereDate('created_at', $this->date)
                ->count();

            MarketplaceHealthSnapshot::updateOrCreate(
                [
                    'organization_id' => $this->organizationId,
                    'marketplace' => $marketplace,
                    'snapshot_date' => $this->date,
                ],
                [
                    'total_listings' => $totalListings,
                    'active_listings' => $activeListings,
                    'error_listings' => $errorListings,
                    'sync_attempts' => (int) ($syncStats->sync_attempts ?? 0),
                    'sync_successes' => (int) ($syncStats->sync_successes ?? 0),
                    'sync_failures' => (int) ($syncStats->sync_failures ?? 0),
                    'avg_response_time_ms' => (int) ($syncStats->avg_response_time_ms ?? 0),
                    'p95_response_time_ms' => (int) ($syncStats->p95_response_time_ms ?? 0),
                    'total_revenue' => (float) $revenue,
                    'total_fees' => (float) $fees,
                    'orders_imported' => $ordersImported,
                ]
            );
        }
    }
}
