<?php

namespace App\Services\Analytics;

use App\Models\Analytics\UserProductivityDaily;

/**
 * Read-side helpers for the audit-derived KPI table.
 *
 * Mirrors the WorkforceAnalyticsService pattern. Pulls from user_productivity_daily
 * (populated nightly by AggregateAuditDerivedKPIs).
 */
class UserProductivityService
{
    protected int $organizationId;

    protected string $from;

    protected string $to;

    public function forOrganization(int $organizationId): self
    {
        $this->organizationId = $organizationId;

        return $this;
    }

    public function dateRange(string $from, string $to): self
    {
        $this->from = $from;
        $this->to = $to;

        return $this;
    }

    /**
     * Per-user totals across the date range.
     */
    public function perUserTotals(): array
    {
        return UserProductivityDaily::query()
            ->where('organization_id', $this->organizationId)
            ->whereBetween('performance_date', [$this->from, $this->to])
            ->selectRaw(implode(', ', [
                'user_id',
                'sum(products_listed_count) as products_listed',
                'sum(pos_created_count) as pos_created',
                'sum(pos_approved_count) as pos_approved',
                'sum(pos_received_count) as pos_received',
                'sum(messages_answered_count) as messages_answered',
                'sum(inventory_moves_count) as inventory_moves',
                'sum(listings_published_ebay) as listings_ebay',
                'sum(listings_published_shopify) as listings_shopify',
                'sum(listings_published_amazon) as listings_amazon',
                'sum(customer_records_touched) as customer_records_touched',
                'sum(pricing_rules_changed) as pricing_rules_changed',
            ]))
            ->groupBy('user_id')
            ->with('user:id,name,email')
            ->get()
            ->map(fn ($row) => $row->toArray() + ['user' => $row->user?->only(['id', 'name', 'email'])])
            ->all();
    }

    /**
     * Org-wide daily series for a given metric column. Use to render a trend line.
     */
    public function dailyTotalsFor(string $column): array
    {
        $allowed = array_keys(\App\Jobs\Analytics\AggregateAuditDerivedKPIs::ACTION_MAP);
        if (! in_array($column, $allowed, true)) {
            return [];
        }

        return UserProductivityDaily::query()
            ->where('organization_id', $this->organizationId)
            ->whereBetween('performance_date', [$this->from, $this->to])
            ->selectRaw("performance_date, sum({$column}) as value")
            ->groupBy('performance_date')
            ->orderBy('performance_date')
            ->get()
            ->map(fn ($r) => ['date' => $r->performance_date->toDateString(), 'value' => (int) $r->value])
            ->all();
    }

    /**
     * Top-N leaderboard for a given metric column.
     */
    public function leaderboard(string $column, int $limit = 15): array
    {
        $allowed = array_keys(\App\Jobs\Analytics\AggregateAuditDerivedKPIs::ACTION_MAP);
        if (! in_array($column, $allowed, true)) {
            return [];
        }

        return UserProductivityDaily::query()
            ->where('organization_id', $this->organizationId)
            ->whereBetween('performance_date', [$this->from, $this->to])
            ->selectRaw("user_id, sum({$column}) as value")
            ->groupBy('user_id')
            ->orderByDesc('value')
            ->limit($limit)
            ->with('user:id,name,email')
            ->get()
            ->map(fn ($r) => [
                'user_id' => $r->user_id,
                'user_name' => $r->user?->name ?? 'Unknown',
                'user_email' => $r->user?->email,
                'value' => (int) $r->value,
            ])
            ->all();
    }
}
