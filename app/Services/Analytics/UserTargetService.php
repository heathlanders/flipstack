<?php

namespace App\Services\Analytics;

use App\Models\Analytics\UserTarget;
use App\Services\Analytics\Concerns\AnalyticsQueryTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Targets/attainment service for KPIs #6, #7, #8.
 *
 * Targets can scope to a specific user, role, team, or the whole org.
 * For attainment we resolve all targets that apply to a user (highest
 * precedence wins: user > team > role > org) and compare to actuals
 * pulled from workforce_performance_daily.
 */
class UserTargetService
{
    use AnalyticsQueryTrait;

    private const PRECEDENCE = [
        UserTarget::SCOPE_USER => 4,
        UserTarget::SCOPE_TEAM => 3,
        UserTarget::SCOPE_ROLE => 2,
        UserTarget::SCOPE_ORG => 1,
    ];

    /**
     * All targets applicable to a user on a given date — taking precedence
     * into account. Returns one row per (metric, period) combination.
     */
    public function getApplicableTargets(int $userId, ?Carbon $onDate = null): array
    {
        $onDate = ($onDate ?? now())->toDateString();

        $userRoleIds = DB::table('user_roles')->where('user_id', $userId)->pluck('role_id')->all();
        $userTeamIds = DB::table('team_user')->where('user_id', $userId)->pluck('team_id')->all();

        $rows = UserTarget::query()
            ->where('organization_id', $this->organizationId)
            ->whereDate('effective_from', '<=', $onDate)
            ->where(function ($q) use ($onDate) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $onDate);
            })
            ->where(function ($q) use ($userId, $userRoleIds, $userTeamIds) {
                $q->where(function ($q) use ($userId) {
                    $q->where('scope_type', UserTarget::SCOPE_USER)->where('scope_id', (string) $userId);
                });
                if (! empty($userRoleIds)) {
                    $q->orWhere(function ($q) use ($userRoleIds) {
                        $q->where('scope_type', UserTarget::SCOPE_ROLE)
                            ->whereIn('scope_id', array_map('strval', $userRoleIds));
                    });
                }
                if (! empty($userTeamIds)) {
                    $q->orWhere(function ($q) use ($userTeamIds) {
                        $q->where('scope_type', UserTarget::SCOPE_TEAM)
                            ->whereIn('scope_id', array_map('strval', $userTeamIds));
                    });
                }
                $q->orWhere('scope_type', UserTarget::SCOPE_ORG);
            })
            ->get();

        // Reduce by (metric, period), keeping highest precedence
        $byKey = [];
        foreach ($rows as $row) {
            $key = $row->metric.':'.$row->period;
            $current = $byKey[$key] ?? null;
            $rowPrec = self::PRECEDENCE[$row->scope_type] ?? 0;
            $curPrec = $current ? (self::PRECEDENCE[$current->scope_type] ?? 0) : -1;

            if ($rowPrec > $curPrec) {
                $byKey[$key] = $row;
            }
        }

        return array_values(array_map(fn ($r) => [
            'id' => $r->id,
            'scope_type' => $r->scope_type,
            'scope_id' => $r->scope_id,
            'metric' => $r->metric,
            'period' => $r->period,
            'target_value' => (float) $r->target_value,
            'effective_from' => $r->effective_from?->toDateString(),
            'effective_to' => $r->effective_to?->toDateString(),
        ], $byKey));
    }

    /**
     * Attainment % per metric for a user across the configured date range.
     * Pro-rates the target by the number of days/weeks/months in the range.
     */
    public function getAttainmentByUser(int $userId): array
    {
        $targets = $this->getApplicableTargets($userId);
        if (empty($targets)) {
            return [];
        }

        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);
        $days = max(1, $start->diffInDays($end) + 1);

        $actuals = DB::table('workforce_performance_daily')
            ->where('organization_id', $this->organizationId)
            ->where('user_id', $userId)
            ->whereBetween('performance_date', [$this->startDate, $this->endDate])
            ->select([
                DB::raw('COALESCE(SUM(items_picked), 0) as items_picked'),
                DB::raw('COALESCE(SUM(items_packed), 0) as items_packed'),
                DB::raw('COALESCE(SUM(shipments_created), 0) as shipments_created'),
                DB::raw('COALESCE(SUM(returns_processed), 0) as returns_processed'),
                DB::raw('COALESCE(SUM(pick_errors), 0) as pick_errors'),
                DB::raw('COALESCE(SUM(pack_errors), 0) as pack_errors'),
                DB::raw('COALESCE(SUM(orders_picked), 0) as orders_picked'),
                DB::raw('COALESCE(SUM(orders_packed), 0) as orders_packed'),
            ])
            ->first();

        $results = [];
        foreach ($targets as $target) {
            $metric = $target['metric'];
            $actual = (float) ($actuals->$metric ?? 0);

            // Pro-rate target to the active range
            $perDay = match ($target['period']) {
                UserTarget::PERIOD_WEEKLY => $target['target_value'] / 7,
                UserTarget::PERIOD_MONTHLY => $target['target_value'] / 30,
                default => $target['target_value'],
            };
            $proRatedTarget = round($perDay * $days, 2);
            $attainment = $proRatedTarget > 0 ? round(($actual / $proRatedTarget) * 100, 1) : 0;
            $lowerIsBetter = in_array($metric, ['pick_errors', 'pack_errors']);

            $results[] = [
                'metric' => $metric,
                'period' => $target['period'],
                'scope_type' => $target['scope_type'],
                'target_value' => $target['target_value'],
                'pro_rated_target' => $proRatedTarget,
                'actual' => $actual,
                'attainment_pct' => $attainment,
                'lower_is_better' => $lowerIsBetter,
                'status' => $this->status($attainment, $lowerIsBetter),
            ];
        }

        return $results;
    }

    private function status(float $pct, bool $lowerIsBetter): string
    {
        if ($lowerIsBetter) {
            // Inverted: attainment below 100% is good
            return match (true) {
                $pct <= 50 => 'excellent',
                $pct <= 100 => 'on_track',
                $pct <= 150 => 'at_risk',
                default => 'critical',
            };
        }

        return match (true) {
            $pct >= 100 => 'excellent',
            $pct >= 75 => 'on_track',
            $pct >= 50 => 'at_risk',
            default => 'critical',
        };
    }
}
