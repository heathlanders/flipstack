<?php

namespace App\Services\Analytics;

/**
 * Maps a "view" key (e.g. "workforce", "compensation") to the analytics
 * service + method that produces the rows for export, plus a default
 * label and column set. Keep additions to this file when a new
 * exportable analytics view ships.
 */
class ReportRegistry
{
    /**
     * Each entry: label, fetch (callable that takes orgId+filters, returns rows), columns.
     */
    public static function definitions(): array
    {
        return [
            'workforce_individual' => [
                'label' => 'Workforce — Individual Performance',
                'columns' => [
                    'user_name' => 'User',
                    'orders_picked' => 'Orders Picked',
                    'items_picked' => 'Items Picked',
                    'pick_errors' => 'Pick Errors',
                    'avg_pick_seconds' => 'Avg Pick (s)',
                    'orders_packed' => 'Orders Packed',
                    'items_packed' => 'Items Packed',
                    'pack_errors' => 'Pack Errors',
                    'avg_pack_seconds' => 'Avg Pack (s)',
                    'shipments_created' => 'Shipments',
                    'returns_processed' => 'Returns',
                    'days_active' => 'Days Active',
                ],
                'fetch' => fn ($orgId, $f) => app(WorkforceAnalyticsService::class)
                    ->forOrganization($orgId)
                    ->dateRange($f['start_date'], $f['end_date'])
                    ->getIndividualPerformance(),
            ],
            'workforce_leaderboard' => [
                'label' => 'Workforce — Leaderboard',
                'columns' => [
                    'rank' => 'Rank',
                    'user_name' => 'User',
                    'value' => 'Items Picked',
                ],
                'fetch' => fn ($orgId, $f) => app(WorkforceAnalyticsService::class)
                    ->forOrganization($orgId)
                    ->dateRange($f['start_date'], $f['end_date'])
                    ->getLeaderboard('items_picked', 100),
            ],
            'workforce_errors' => [
                'label' => 'Workforce — Error Rates',
                'columns' => [
                    'user_name' => 'User',
                    'pick_errors' => 'Pick Errors',
                    'pack_errors' => 'Pack Errors',
                    'total_errors' => 'Total Errors',
                    'error_rate' => 'Error Rate %',
                ],
                'fetch' => fn ($orgId, $f) => app(WorkforceAnalyticsService::class)
                    ->forOrganization($orgId)
                    ->dateRange($f['start_date'], $f['end_date'])
                    ->getErrorRates(),
            ],
            'compensation' => [
                'label' => 'Compensation — Per User Settlement',
                'columns' => [
                    'user_name' => 'User',
                    'supplier_name' => 'Supplier',
                    'tier_name' => 'Tier',
                    'settlement_count' => 'Settlements',
                    'total_items_sold' => 'Items Sold',
                    'gross_sales' => 'Gross Sales',
                    'commission' => 'Commission',
                    'bonuses' => 'Bonuses',
                    'fees' => 'Fees',
                    'net_payout' => 'Net Payout',
                ],
                'fetch' => fn ($orgId, $f) => app(WorkforceCompensationService::class)
                    ->forOrganization($orgId)
                    ->dateRange($f['start_date'], $f['end_date'])
                    ->getUserSettlementSummary(),
            ],
            'attribution_returns' => [
                'label' => 'Attribution — Returns by Packer',
                'columns' => [
                    'user_name' => 'Packer',
                    'orders_packed' => 'Orders Packed',
                    'returns_filed' => 'Returns Filed',
                    'returns_refunded' => 'Refunded',
                    'return_rate' => 'Return Rate %',
                ],
                'fetch' => fn ($orgId, $f) => app(ErrorAttributionService::class)
                    ->forOrganization($orgId)
                    ->dateRange($f['start_date'], $f['end_date'])
                    ->getReturnRateByPacker(),
            ],
            'attribution_cancels' => [
                'label' => 'Attribution — Cancellations by Handler',
                'columns' => [
                    'user_name' => 'User',
                    'picker_handled' => 'Picked',
                    'picker_cancelled' => 'Picker Cancelled',
                    'picker_cancel_rate' => 'Picker Cancel %',
                    'packer_handled' => 'Packed',
                    'packer_cancelled' => 'Packer Cancelled',
                    'packer_cancel_rate' => 'Packer Cancel %',
                    'total_cancelled' => 'Total Cancelled',
                ],
                'fetch' => fn ($orgId, $f) => app(ErrorAttributionService::class)
                    ->forOrganization($orgId)
                    ->dateRange($f['start_date'], $f['end_date'])
                    ->getCancellationRateByHandler(),
            ],
            'attribution_refunds' => [
                'label' => 'Attribution — Refunds by Lister',
                'columns' => [
                    'user_name' => 'Lister',
                    'items_sold' => 'Items Sold',
                    'items_refunded' => 'Items Refunded',
                    'refund_total' => 'Refund Total $',
                    'refund_rate' => 'Refund Rate %',
                ],
                'fetch' => fn ($orgId, $f) => app(ErrorAttributionService::class)
                    ->forOrganization($orgId)
                    ->dateRange($f['start_date'], $f['end_date'])
                    ->getRefundRateByLister(),
            ],
            'labor_cost' => [
                'label' => 'Labor Cost — Per User',
                'columns' => [
                    'user_name' => 'User',
                    'hourly_rate' => 'Rate $/hr',
                    'active_hours' => 'Active Hrs',
                    'items_picked' => 'Items Picked',
                    'listings_created' => 'Listings',
                    'replies_sent' => 'Replies',
                    'total_labor_cost' => 'Total Cost $',
                    'cost_per_picked_unit' => '$/Pick',
                    'cost_per_listed_item' => '$/Listing',
                    'cost_per_ticket' => '$/Ticket',
                    'cost_to_serve_per_order' => '$/Order',
                ],
                'fetch' => fn ($orgId, $f) => app(LaborCostService::class)
                    ->forOrganization($orgId)
                    ->dateRange($f['start_date'], $f['end_date'])
                    ->getCostByUser(),
            ],
        ];
    }

    public static function get(string $view): ?array
    {
        return self::definitions()[$view] ?? null;
    }

    public static function viewOptions(): array
    {
        return collect(self::definitions())
            ->map(fn ($def, $key) => ['value' => $key, 'label' => $def['label']])
            ->values()
            ->all();
    }
}
