<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Concerns\AnalyticsQueryTrait;
use Illuminate\Support\Facades\DB;

class PricingAnalyticsService
{
    use AnalyticsQueryTrait;

    /**
     * Base query for repricing_logs scoped to the org via repricing_rules.
     * repricing_logs has no organization_id column.
     */
    private function logsForOrg()
    {
        return DB::table('repricing_logs')
            ->join('repricing_rules', 'repricing_logs.repricing_rule_id', '=', 'repricing_rules.id')
            ->where('repricing_rules.organization_id', $this->organizationId)
            ->whereBetween('repricing_logs.created_at', [$this->startDate, $this->endDate.' 23:59:59']);
    }

    public function getOverview(): array
    {
        return $this->cached('pricing_overview', 900, function () {
            $reprices = $this->logsForOrg()
                ->select([
                    DB::raw('COUNT(*) as total_reprices'),
                    DB::raw('COALESCE(AVG(CASE WHEN repricing_logs.old_price > 0 THEN ((repricing_logs.new_price - repricing_logs.old_price) / repricing_logs.old_price) * 100 END), 0) as avg_price_change_pct'),
                ])
                ->first();

            $activeRules = DB::table('repricing_rules')
                ->where('organization_id', $this->organizationId)
                ->where('is_active', true)
                ->count();

            return [
                'total_reprices' => (int) ($reprices->total_reprices ?? 0),
                'avg_price_change_pct' => round((float) ($reprices->avg_price_change_pct ?? 0), 2),
                'active_rules' => $activeRules,
            ];
        });
    }

    public function getRepricingFrequency(): array
    {
        return $this->logsForOrg()
            ->select([
                DB::raw('DATE(repricing_logs.created_at) as date'),
                DB::raw('COUNT(*) as reprice_count'),
            ])
            ->groupBy(DB::raw('DATE(repricing_logs.created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'reprice_count' => (int) $row->reprice_count,
            ])
            ->toArray();
    }

    public function getMarginImpact(): array
    {
        $before = $this->logsForOrg()
            ->select([
                DB::raw('COALESCE(AVG(repricing_logs.old_price), 0) as avg_old_price'),
                DB::raw('COALESCE(AVG(repricing_logs.new_price), 0) as avg_new_price'),
            ])
            ->first();

        return [
            'avg_price_before' => round((float) ($before->avg_old_price ?? 0), 2),
            'avg_price_after' => round((float) ($before->avg_new_price ?? 0), 2),
            'avg_change' => round((float) ($before->avg_new_price ?? 0) - (float) ($before->avg_old_price ?? 0), 2),
        ];
    }

    public function getCompetitivePosition(): array
    {
        $positions = $this->logsForOrg()
            ->whereNotNull('repricing_logs.competitor_price')
            ->where('repricing_logs.competitor_price', '>', 0)
            ->select([
                DB::raw('SUM(CASE WHEN repricing_logs.new_price < repricing_logs.competitor_price THEN 1 ELSE 0 END) as cheaper'),
                DB::raw('SUM(CASE WHEN repricing_logs.new_price = repricing_logs.competitor_price THEN 1 ELSE 0 END) as equal'),
                DB::raw('SUM(CASE WHEN repricing_logs.new_price > repricing_logs.competitor_price THEN 1 ELSE 0 END) as higher'),
                DB::raw('COUNT(*) as total'),
            ])
            ->first();

        return [
            'cheaper' => (int) ($positions->cheaper ?? 0),
            'equal' => (int) ($positions->equal ?? 0),
            'higher' => (int) ($positions->higher ?? 0),
            'total' => (int) ($positions->total ?? 0),
        ];
    }

    public function getRulePerformance(): array
    {
        return $this->logsForOrg()
            ->select([
                'repricing_rules.id',
                'repricing_rules.name',
                DB::raw('COUNT(repricing_logs.id) as reprices'),
                DB::raw('COALESCE(AVG(CASE WHEN repricing_logs.old_price > 0 THEN ((repricing_logs.new_price - repricing_logs.old_price) / repricing_logs.old_price) * 100 END), 0) as avg_change_pct'),
            ])
            ->groupBy('repricing_rules.id', 'repricing_rules.name')
            ->orderByDesc('reprices')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'reprices' => (int) $row->reprices,
                'avg_change_pct' => round((float) $row->avg_change_pct, 2),
            ])
            ->toArray();
    }
}
