<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Concerns\AnalyticsQueryTrait;
use Illuminate\Support\Facades\DB;

class MarketplaceHealthService
{
    use AnalyticsQueryTrait;

    public function getOverview(): array
    {
        return $this->cached('marketplace_health_overview', 900, function () {
            return $this->withComparison(function (string $startDate, string $endDate) {
                $ebayListings = DB::table('ebay_listings')
                    ->join('integration_credentials', 'ebay_listings.integration_credential_id', '=', 'integration_credentials.id')
                    ->join('teams', 'integration_credentials.team_id', '=', 'teams.id')
                    ->where('teams.organization_id', $this->organizationId)
                    ->select([
                        DB::raw('COUNT(*) as total'),
                        DB::raw("COUNT(*) FILTER (WHERE ebay_listings.status = 'active') as active"),
                        DB::raw("COUNT(*) FILTER (WHERE ebay_listings.status = 'error') as error"),
                    ])
                    ->first();

                $shopifyListings = DB::table('shopify_listings')
                    ->join('integration_credentials', 'shopify_listings.integration_credential_id', '=', 'integration_credentials.id')
                    ->join('teams', 'integration_credentials.team_id', '=', 'teams.id')
                    ->where('teams.organization_id', $this->organizationId)
                    ->select([
                        DB::raw('COUNT(*) as total'),
                        DB::raw("COUNT(*) FILTER (WHERE shopify_listings.status = 'active') as active"),
                        DB::raw("COUNT(*) FILTER (WHERE shopify_listings.status = 'error') as error"),
                    ])
                    ->first();

                $totalListings = (int) $ebayListings->total + (int) $shopifyListings->total;
                $activeListings = (int) $ebayListings->active + (int) $shopifyListings->active;
                $errorListings = (int) $ebayListings->error + (int) $shopifyListings->error;

                $syncStats = $this->getSyncStatsForRange($startDate, $endDate);

                return [
                    'total_listings' => $totalListings,
                    'active_pct' => $totalListings > 0 ? round(($activeListings / $totalListings) * 100, 2) : 0,
                    'error_pct' => $totalListings > 0 ? round(($errorListings / $totalListings) * 100, 2) : 0,
                    'sync_success_rate' => $syncStats['success_rate'],
                    'avg_response_time' => $syncStats['avg_response_ms'],
                ];
            });
        });
    }

    public function getSyncHealth(?string $marketplace = null): array
    {
        return $this->cached("marketplace_sync_health_{$marketplace}", 900, function () use ($marketplace) {
            $results = [];

            $marketplaces = $marketplace
                ? [$marketplace]
                : ['ebay', 'shopify', 'amazon'];

            foreach ($marketplaces as $mp) {
                $table = $mp.'_sync_logs';

                if (! DB::getSchemaBuilder()->hasTable($table)) {
                    continue;
                }

                $row = DB::table($table)
                    ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                    ->select([
                        DB::raw('COUNT(*) as total'),
                        DB::raw("COUNT(*) FILTER (WHERE status = 'success') as successes"),
                        DB::raw("COUNT(*) FILTER (WHERE status = 'error') as errors"),
                        DB::raw('COALESCE(AVG(response_time_ms), 0) as avg_response_ms'),
                        DB::raw('COALESCE(PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY response_time_ms) FILTER (WHERE response_time_ms IS NOT NULL), 0) as p95_response_ms'),
                    ])
                    ->first();

                $total = (int) $row->total;

                $results[] = [
                    'marketplace' => $mp,
                    'total_syncs' => $total,
                    'success_rate' => $total > 0 ? round(((int) $row->successes / $total) * 100, 2) : 0,
                    'avg_response_ms' => round((float) $row->avg_response_ms, 2),
                    'p95_response_ms' => round((float) $row->p95_response_ms, 2),
                    'error_count' => (int) $row->errors,
                ];
            }

            return $results;
        });
    }

    public function getListingHealth(?string $marketplace = null): array
    {
        return $this->cached("marketplace_listing_health_{$marketplace}", 900, function () use ($marketplace) {
            $results = [];

            if ($marketplace === null || $marketplace === 'ebay') {
                $ebay = DB::table('ebay_listings')
                    ->join('integration_credentials', 'ebay_listings.integration_credential_id', '=', 'integration_credentials.id')
                    ->join('teams', 'integration_credentials.team_id', '=', 'teams.id')
                    ->where('teams.organization_id', $this->organizationId)
                    ->select([
                        DB::raw("COUNT(*) FILTER (WHERE ebay_listings.status = 'active') as active"),
                        DB::raw("COUNT(*) FILTER (WHERE ebay_listings.status = 'pending') as pending"),
                        DB::raw("COUNT(*) FILTER (WHERE ebay_listings.status = 'error') as error"),
                        DB::raw("COUNT(*) FILTER (WHERE ebay_listings.status = 'ended') as ended"),
                        DB::raw('COUNT(*) as total'),
                    ])
                    ->first();

                $results[] = [
                    'marketplace' => 'ebay',
                    'active' => (int) $ebay->active,
                    'pending' => (int) $ebay->pending,
                    'error' => (int) $ebay->error,
                    'ended' => (int) $ebay->ended,
                    'total' => (int) $ebay->total,
                ];
            }

            if ($marketplace === null || $marketplace === 'shopify') {
                $shopify = DB::table('shopify_listings')
                    ->join('integration_credentials', 'shopify_listings.integration_credential_id', '=', 'integration_credentials.id')
                    ->join('teams', 'integration_credentials.team_id', '=', 'teams.id')
                    ->where('teams.organization_id', $this->organizationId)
                    ->select([
                        DB::raw("COUNT(*) FILTER (WHERE shopify_listings.status = 'active') as active"),
                        DB::raw("COUNT(*) FILTER (WHERE shopify_listings.status = 'pending') as pending"),
                        DB::raw("COUNT(*) FILTER (WHERE shopify_listings.status = 'error') as error"),
                        DB::raw("COUNT(*) FILTER (WHERE shopify_listings.status = 'ended') as ended"),
                        DB::raw('COUNT(*) as total'),
                    ])
                    ->first();

                $results[] = [
                    'marketplace' => 'shopify',
                    'active' => (int) $shopify->active,
                    'pending' => (int) $shopify->pending,
                    'error' => (int) $shopify->error,
                    'ended' => (int) $shopify->ended,
                    'total' => (int) $shopify->total,
                ];
            }

            return $results;
        });
    }

    public function getResponseTimeTrend(): array
    {
        return $this->cached('marketplace_response_trend', 900, function () {
            $ebay = DB::table('ebay_sync_logs')
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNotNull('response_time_ms')
                ->select([
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('AVG(response_time_ms) as avg_ms'),
                    DB::raw("'ebay' as marketplace"),
                ])
                ->groupBy('date')
                ->orderBy('date');

            $shopify = DB::table('shopify_sync_logs')
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNotNull('response_time_ms')
                ->select([
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('AVG(response_time_ms) as avg_ms'),
                    DB::raw("'shopify' as marketplace"),
                ])
                ->groupBy('date')
                ->orderBy('date');

            $results = $ebay->unionAll($shopify)
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'marketplace' => $row->marketplace,
                    'avg_response_ms' => round((float) $row->avg_ms, 2),
                ])
                ->toArray();

            return $results;
        });
    }

    public function getErrorBreakdown(): array
    {
        return $this->cached('marketplace_error_breakdown', 900, function () {
            $ebayErrors = DB::table('ebay_sync_logs')
                ->where('status', 'error')
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNotNull('error_message')
                ->select([
                    'error_message',
                    DB::raw('COUNT(*) as count'),
                    DB::raw("'ebay' as marketplace"),
                ])
                ->groupBy('error_message')
                ->orderByDesc('count')
                ->limit(20);

            $shopifyErrors = DB::table('shopify_sync_logs')
                ->where('status', 'error')
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->whereNotNull('error_message')
                ->select([
                    'error_message',
                    DB::raw('COUNT(*) as count'),
                    DB::raw("'shopify' as marketplace"),
                ])
                ->groupBy('error_message')
                ->orderByDesc('count')
                ->limit(20);

            return $ebayErrors->unionAll($shopifyErrors)
                ->get()
                ->map(fn ($row) => [
                    'marketplace' => $row->marketplace,
                    'error_message' => $row->error_message,
                    'count' => (int) $row->count,
                ])
                ->sortByDesc('count')
                ->values()
                ->toArray();
        });
    }

    public function getFeeAnalysis(): array
    {
        return $this->cached('marketplace_fee_analysis', 900, function () {
            $byType = DB::table('marketplace_fees')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('fee_date', [$this->startDate, $this->endDate])
                ->select([
                    'marketplace',
                    'fee_type',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('COALESCE(SUM(amount), 0) as total_amount'),
                ])
                ->groupBy('marketplace', 'fee_type')
                ->orderBy('marketplace')
                ->orderByDesc('total_amount')
                ->get()
                ->map(fn ($row) => [
                    'marketplace' => $row->marketplace,
                    'fee_type' => $row->fee_type,
                    'count' => (int) $row->count,
                    'total_amount' => round((float) $row->total_amount, 2),
                ])
                ->toArray();

            $byMarketplace = DB::table('marketplace_fees')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('fee_date', [$this->startDate, $this->endDate])
                ->select([
                    'marketplace',
                    DB::raw('COALESCE(SUM(amount), 0) as total_fees'),
                ])
                ->groupBy('marketplace')
                ->orderByDesc('total_fees')
                ->get()
                ->map(fn ($row) => [
                    'marketplace' => $row->marketplace,
                    'total_fees' => round((float) $row->total_fees, 2),
                ])
                ->toArray();

            return [
                'by_type' => $byType,
                'by_marketplace' => $byMarketplace,
            ];
        });
    }

    public function getCrossMarketplaceComparison(): array
    {
        return $this->cached('marketplace_cross_comparison', 900, function () {
            $syncHealth = collect($this->getSyncHealth())->keyBy('marketplace');
            $listingHealth = collect($this->getListingHealth())->keyBy('marketplace');
            $feeAnalysis = collect($this->getFeeAnalysis()['by_marketplace'])->keyBy('marketplace');

            $marketplaces = $syncHealth->keys()
                ->merge($listingHealth->keys())
                ->merge($feeAnalysis->keys())
                ->unique();

            return $marketplaces->map(function ($mp) use ($syncHealth, $listingHealth, $feeAnalysis) {
                $sync = $syncHealth->get($mp, []);
                $listing = $listingHealth->get($mp, []);
                $fees = $feeAnalysis->get($mp, []);

                return [
                    'marketplace' => $mp,
                    'total_listings' => $listing['total'] ?? 0,
                    'active_listings' => $listing['active'] ?? 0,
                    'error_listings' => $listing['error'] ?? 0,
                    'sync_success_rate' => $sync['success_rate'] ?? 0,
                    'avg_response_ms' => $sync['avg_response_ms'] ?? 0,
                    'total_fees' => $fees['total_fees'] ?? 0,
                ];
            })->values()->toArray();
        });
    }

    protected function getSyncStatsForRange(string $startDate, string $endDate): array
    {
        $totalSyncs = 0;
        $totalSuccesses = 0;
        $totalResponseMs = 0;
        $responseCount = 0;

        foreach (['ebay_sync_logs', 'shopify_sync_logs', 'amazon_sync_logs'] as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $row = DB::table($table)
                ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
                ->select([
                    DB::raw('COUNT(*) as total'),
                    DB::raw("COUNT(*) FILTER (WHERE status = 'success') as successes"),
                    DB::raw('COALESCE(SUM(response_time_ms) FILTER (WHERE response_time_ms IS NOT NULL), 0) as total_ms'),
                    DB::raw('COUNT(*) FILTER (WHERE response_time_ms IS NOT NULL) as ms_count'),
                ])
                ->first();

            $totalSyncs += (int) $row->total;
            $totalSuccesses += (int) $row->successes;
            $totalResponseMs += (float) $row->total_ms;
            $responseCount += (int) $row->ms_count;
        }

        return [
            'success_rate' => $totalSyncs > 0 ? round(($totalSuccesses / $totalSyncs) * 100, 2) : 0,
            'avg_response_ms' => $responseCount > 0 ? round($totalResponseMs / $responseCount, 2) : 0,
        ];
    }
}
