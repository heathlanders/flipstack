<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Concerns\AnalyticsQueryTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BusinessHealthScoreService
{
    use AnalyticsQueryTrait;

    protected array $weights = [
        'profitability' => 20,
        'fulfillment' => 20,
        'inventory' => 15,
        'marketplace' => 15,
        'customer' => 10,
        'procurement' => 10,
        'communication' => 5,
        'workforce' => 5,
    ];

    public function calculate(): array
    {
        $dimensions = [
            'profitability' => $this->calculateProfitability(),
            'fulfillment' => $this->calculateFulfillmentSpeed(),
            'inventory' => $this->calculateInventoryHealth(),
            'marketplace' => $this->calculateMarketplaceHealth(),
            'customer' => $this->calculateCustomerMetrics(),
            'procurement' => $this->calculateProcurement(),
            'communication' => $this->calculateCommunication(),
            'workforce' => $this->calculateWorkforce(),
        ];

        $dimensionDetails = [];
        foreach ($dimensions as $key => $data) {
            $dimensionDetails[$key] = $data;
        }

        $dimensionScores = [];
        foreach ($dimensions as $key => $data) {
            $dimensionScores[$key] = $data['score'];
        }

        $overallScore = 0;
        foreach ($dimensionScores as $key => $score) {
            $overallScore += $score * ($this->weights[$key] / 100);
        }
        $overallScore = round($overallScore, 2);

        $alerts = [];
        foreach ($dimensionScores as $key => $score) {
            if ($score < 40) {
                $alerts[] = [
                    'dimension' => $key,
                    'score' => $score,
                    'level' => 'critical',
                    'message' => ucfirst($key).' score is critically low at '.$score,
                ];
            } elseif ($score < 60) {
                $alerts[] = [
                    'dimension' => $key,
                    'score' => $score,
                    'level' => 'warning',
                    'message' => ucfirst($key).' score needs attention at '.$score,
                ];
            }
        }

        $result = [
            'overall_score' => $overallScore,
            'dimensions' => $dimensionScores,
            'dimension_details' => $dimensionDetails,
            'alerts' => $alerts,
        ];

        DB::table('business_health_scores')->updateOrInsert(
            [
                'organization_id' => $this->organizationId,
                'score_date' => now()->toDateString(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'overall_score' => $overallScore,
                'dimensions' => json_encode($dimensionScores),
                'dimension_details' => json_encode($dimensionDetails),
                'alerts' => json_encode($alerts),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->dispatchAlerts($alerts);

        return $result;
    }

    protected function dispatchAlerts(array $alerts): void
    {
        if (empty($alerts) || ! is_numeric($this->organizationId)) {
            return;
        }

        $notificationService = app(\App\Services\NotificationService::class);
        foreach ($alerts as $alert) {
            $notificationService->dispatchEvent(
                eventKey: 'analytics.health_score.alert',
                organizationId: (int) $this->organizationId,
                args: [
                    (string) $alert['dimension'],
                    (float) $alert['score'],
                    (string) $alert['level'],
                    (string) $alert['message'],
                ],
                relatedId: $alert['dimension'].':'.$alert['level'],
            );
        }
    }

    public function getHistorical(int $days = 30): array
    {
        return DB::table('business_health_scores')
            ->where('organization_id', $this->organizationId)
            ->where('score_date', '>=', now()->subDays($days)->toDateString())
            ->select(['score_date', 'overall_score'])
            ->orderBy('score_date')
            ->get()
            ->map(fn ($row) => [
                'score_date' => $row->score_date,
                'overall_score' => round((float) $row->overall_score, 2),
            ])
            ->toArray();
    }

    protected function calculateProfitability(): array
    {
        $row = DB::table('order_financials')
            ->join('orders', 'order_financials.order_id', '=', 'orders.id')
            ->where('order_financials.organization_id', $this->organizationId)
            ->whereBetween('orders.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->whereNull('orders.deleted_at')
            ->select([
                DB::raw('COALESCE(AVG(order_financials.profit_margin_pct), 0) as avg_margin'),
                DB::raw('COALESCE(SUM(order_financials.revenue), 0) as total_revenue'),
                DB::raw('COALESCE(SUM(order_financials.net_profit), 0) as total_net_profit'),
            ])
            ->first();

        $margin = (float) $row->avg_margin;

        // Scale: 0% -> 0, 5% -> 50, 15%+ -> 100
        $score = 0;
        if ($margin >= 15) {
            $score = 100;
        } elseif ($margin >= 5) {
            $score = 50 + (($margin - 5) / 10) * 50;
        } elseif ($margin > 0) {
            $score = ($margin / 5) * 50;
        }

        return [
            'score' => round(min(100, max(0, $score)), 2),
            'avg_margin' => round($margin, 2),
            'total_revenue' => round((float) $row->total_revenue, 2),
            'total_net_profit' => round((float) $row->total_net_profit, 2),
        ];
    }

    protected function calculateFulfillmentSpeed(): array
    {
        $row = DB::table('orders')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->whereNull('deleted_at')
            ->whereIn('status', ['shipped', 'delivered'])
            ->whereNotNull('paid_at')
            ->whereNotNull('shipped_at')
            ->select([
                DB::raw('COALESCE(AVG(EXTRACT(EPOCH FROM (shipped_at - paid_at)) / 3600), 0) as avg_hours'),
                DB::raw('COUNT(*) as total_shipped'),
                DB::raw('COUNT(*) FILTER (WHERE ship_by_date IS NOT NULL AND shipped_at <= ship_by_date) as on_time'),
                DB::raw('COUNT(*) FILTER (WHERE ship_by_date IS NOT NULL) as with_deadline'),
            ])
            ->first();

        $avgHours = (float) $row->avg_hours;
        $onTimeRate = (int) $row->with_deadline > 0
            ? ((int) $row->on_time / (int) $row->with_deadline) * 100
            : 100;

        // Speed score: 72h+ -> 0, 48h -> 50, 24h -> 80, <12h -> 100
        $speedScore = 0;
        if ($avgHours < 12) {
            $speedScore = 100;
        } elseif ($avgHours < 24) {
            $speedScore = 80 + ((24 - $avgHours) / 12) * 20;
        } elseif ($avgHours < 48) {
            $speedScore = 50 + ((48 - $avgHours) / 24) * 30;
        } elseif ($avgHours < 72) {
            $speedScore = ((72 - $avgHours) / 24) * 50;
        }

        // Blend speed (60%) and on-time rate (40%)
        $score = ($speedScore * 0.6) + ($onTimeRate * 0.4);

        return [
            'score' => round(min(100, max(0, $score)), 2),
            'avg_hours' => round($avgHours, 2),
            'on_time_rate' => round($onTimeRate, 2),
        ];
    }

    protected function calculateInventoryHealth(): array
    {
        $row = DB::table('inventory_levels')
            ->where('organization_id', $this->organizationId)
            ->select([
                DB::raw('COUNT(*) as total_skus'),
                DB::raw('COUNT(*) FILTER (WHERE quantity_on_hand > reorder_point) as healthy'),
                DB::raw('COUNT(*) FILTER (WHERE quantity_on_hand <= reorder_point AND quantity_on_hand > 0) as low_stock'),
                DB::raw('COUNT(*) FILTER (WHERE quantity_on_hand = 0) as out_of_stock'),
            ])
            ->first();

        $total = (int) $row->total_skus;
        $healthy = (int) $row->healthy;
        $healthyPct = $total > 0 ? ($healthy / $total) * 100 : 100;

        // Score based on healthy stock percentage
        $score = $healthyPct;

        return [
            'score' => round(min(100, max(0, $score)), 2),
            'total_skus' => $total,
            'healthy' => $healthy,
            'low_stock' => (int) $row->low_stock,
            'out_of_stock' => (int) $row->out_of_stock,
            'healthy_pct' => round($healthyPct, 2),
        ];
    }

    protected function calculateMarketplaceHealth(): array
    {
        $ebay = DB::table('ebay_sync_logs')
            ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(*) FILTER (WHERE status = 'success') as successes"),
            ])
            ->first();

        $shopify = DB::table('shopify_sync_logs')
            ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(*) FILTER (WHERE status = 'success') as successes"),
            ])
            ->first();

        $totalAttempts = (int) $ebay->total + (int) $shopify->total;
        $totalSuccesses = (int) $ebay->successes + (int) $shopify->successes;
        $successRate = $totalAttempts > 0 ? ($totalSuccesses / $totalAttempts) * 100 : 100;

        return [
            'score' => round(min(100, max(0, $successRate)), 2),
            'total_syncs' => $totalAttempts,
            'successes' => $totalSuccesses,
            'success_rate' => round($successRate, 2),
        ];
    }

    protected function calculateCustomerMetrics(): array
    {
        $row = DB::table('customers')
            ->where('organization_id', $this->organizationId)
            ->select([
                DB::raw('COUNT(*) as total_customers'),
                DB::raw('COUNT(*) FILTER (WHERE total_orders > 1) as repeat_customers'),
            ])
            ->first();

        $returns = DB::table('return_requests')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->count();

        $orders = DB::table('orders')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->whereNull('deleted_at')
            ->count();

        $total = (int) $row->total_customers;
        $repeatRate = $total > 0 ? ((int) $row->repeat_customers / $total) * 100 : 0;
        $returnRate = $orders > 0 ? ($returns / $orders) * 100 : 0;

        // Blend repeat rate (positive, 60%) and inverse return rate (40%)
        $repeatScore = min(100, $repeatRate * 2); // 50% repeat = 100
        $returnScore = max(0, 100 - ($returnRate * 10)); // 10% return = 0
        $score = ($repeatScore * 0.6) + ($returnScore * 0.4);

        return [
            'score' => round(min(100, max(0, $score)), 2),
            'repeat_rate' => round($repeatRate, 2),
            'return_rate' => round($returnRate, 2),
        ];
    }

    protected function calculateProcurement(): array
    {
        $row = DB::table('purchase_orders')
            ->where('organization_id', $this->organizationId)
            ->whereNotNull('received_at')
            ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->select([
                DB::raw('COUNT(*) as total_received'),
                DB::raw('COUNT(*) FILTER (WHERE expected_delivery_date IS NOT NULL AND received_at::date <= expected_delivery_date) as on_time'),
                DB::raw('COUNT(*) FILTER (WHERE expected_delivery_date IS NOT NULL) as with_expected'),
            ])
            ->first();

        $withExpected = (int) $row->with_expected;
        $onTimeRate = $withExpected > 0 ? ((int) $row->on_time / $withExpected) * 100 : 100;

        return [
            'score' => round(min(100, max(0, $onTimeRate)), 2),
            'total_received' => (int) $row->total_received,
            'on_time_rate' => round($onTimeRate, 2),
        ];
    }

    protected function calculateCommunication(): array
    {
        $row = DB::table('marketplace_messages')
            ->where('organization_id', $this->organizationId)
            ->where('direction', 'inbound')
            ->whereBetween('received_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->select([
                DB::raw('COUNT(*) as total_inbound'),
                DB::raw('COUNT(*) FILTER (WHERE replied_at IS NOT NULL) as replied'),
                DB::raw('COUNT(*) FILTER (WHERE replied_at IS NOT NULL AND EXTRACT(EPOCH FROM (replied_at - received_at)) / 3600 <= 24) as within_sla'),
            ])
            ->first();

        $totalInbound = (int) $row->total_inbound;
        $slaRate = $totalInbound > 0 ? ((int) $row->within_sla / $totalInbound) * 100 : 100;

        return [
            'score' => round(min(100, max(0, $slaRate)), 2),
            'total_inbound' => $totalInbound,
            'replied' => (int) $row->replied,
            'sla_compliance' => round($slaRate, 2),
        ];
    }

    protected function calculateWorkforce(): array
    {
        $row = DB::table('workforce_performance_daily')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('performance_date', [$this->startDate, $this->endDate])
            ->select([
                DB::raw('COALESCE(SUM(items_picked + items_packed), 0) as total_items'),
                DB::raw('COALESCE(SUM(pick_errors + pack_errors), 0) as total_errors'),
            ])
            ->first();

        $totalItems = (int) $row->total_items;
        $totalErrors = (int) $row->total_errors;
        $errorRate = $totalItems > 0 ? ($totalErrors / $totalItems) * 100 : 0;

        // Inverse error rate: 0% errors = 100, 5%+ errors = 0
        $score = max(0, 100 - ($errorRate * 20));

        return [
            'score' => round(min(100, max(0, $score)), 2),
            'total_items_processed' => $totalItems,
            'total_errors' => $totalErrors,
            'error_rate' => round($errorRate, 2),
        ];
    }
}
