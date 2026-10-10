<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Concerns\AnalyticsQueryTrait;
use Illuminate\Support\Facades\DB;

class WorkforceAnalyticsService
{
    use AnalyticsQueryTrait;

    public function getTeamOverview(): array
    {
        return $this->cached('workforce_team_overview', 900, function () {
            $row = DB::table('workforce_performance_daily')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('performance_date', [$this->startDate, $this->endDate])
                ->select([
                    DB::raw('COUNT(DISTINCT user_id) as active_workers'),
                    DB::raw('COALESCE(SUM(orders_picked), 0) as total_orders_picked'),
                    DB::raw('COALESCE(SUM(items_picked), 0) as total_items_picked'),
                    DB::raw('COALESCE(SUM(pick_errors), 0) as total_pick_errors'),
                    DB::raw('COALESCE(SUM(orders_packed), 0) as total_orders_packed'),
                    DB::raw('COALESCE(SUM(items_packed), 0) as total_items_packed'),
                    DB::raw('COALESCE(SUM(pack_errors), 0) as total_pack_errors'),
                    DB::raw('COALESCE(SUM(labels_purchased), 0) as total_labels_purchased'),
                    DB::raw('COALESCE(SUM(shipments_created), 0) as total_shipments_created'),
                    DB::raw('COALESCE(SUM(returns_processed), 0) as total_returns_processed'),
                    DB::raw('COALESCE(SUM(total_active_seconds), 0) as total_active_seconds'),
                    DB::raw('COALESCE(AVG(avg_pick_duration_seconds), 0) as avg_pick_duration_seconds'),
                    DB::raw('COALESCE(AVG(avg_pack_duration_seconds), 0) as avg_pack_duration_seconds'),
                ])
                ->first();

            return [
                'active_workers' => (int) $row->active_workers,
                'total_orders_picked' => (int) $row->total_orders_picked,
                'total_items_picked' => (int) $row->total_items_picked,
                'total_pick_errors' => (int) $row->total_pick_errors,
                'total_orders_packed' => (int) $row->total_orders_packed,
                'total_items_packed' => (int) $row->total_items_packed,
                'total_pack_errors' => (int) $row->total_pack_errors,
                'total_labels_purchased' => (int) $row->total_labels_purchased,
                'total_shipments_created' => (int) $row->total_shipments_created,
                'total_returns_processed' => (int) $row->total_returns_processed,
                'total_active_seconds' => (int) $row->total_active_seconds,
                'avg_pick_duration_seconds' => round((float) $row->avg_pick_duration_seconds, 2),
                'avg_pack_duration_seconds' => round((float) $row->avg_pack_duration_seconds, 2),
            ];
        });
    }

    public function getIndividualPerformance(?int $userId = null): array
    {
        return $this->cached("workforce_individual_{$userId}", 900, function () use ($userId) {
            $query = DB::table('workforce_performance_daily')
                ->join('users', 'workforce_performance_daily.user_id', '=', 'users.id')
                ->where('workforce_performance_daily.organization_id', $this->organizationId)
                ->whereBetween('workforce_performance_daily.performance_date', [$this->startDate, $this->endDate]);

            if ($userId !== null) {
                $query->where('workforce_performance_daily.user_id', $userId);
            }

            return $query->select([
                'users.id as user_id',
                'users.name as user_name',
                DB::raw('COALESCE(SUM(workforce_performance_daily.orders_picked), 0) as orders_picked'),
                DB::raw('COALESCE(SUM(workforce_performance_daily.items_picked), 0) as items_picked'),
                DB::raw('COALESCE(SUM(workforce_performance_daily.pick_errors), 0) as pick_errors'),
                DB::raw('COALESCE(AVG(workforce_performance_daily.avg_pick_duration_seconds), 0) as avg_pick_seconds'),
                DB::raw('COALESCE(SUM(workforce_performance_daily.orders_packed), 0) as orders_packed'),
                DB::raw('COALESCE(SUM(workforce_performance_daily.items_packed), 0) as items_packed'),
                DB::raw('COALESCE(SUM(workforce_performance_daily.pack_errors), 0) as pack_errors'),
                DB::raw('COALESCE(AVG(workforce_performance_daily.avg_pack_duration_seconds), 0) as avg_pack_seconds'),
                DB::raw('COALESCE(SUM(workforce_performance_daily.shipments_created), 0) as shipments_created'),
                DB::raw('COALESCE(SUM(workforce_performance_daily.returns_processed), 0) as returns_processed'),
                DB::raw('COALESCE(SUM(workforce_performance_daily.total_active_seconds), 0) as total_active_seconds'),
                DB::raw('COUNT(DISTINCT workforce_performance_daily.performance_date) as days_active'),
            ])
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('items_picked')
                ->get()
                ->map(fn ($row) => [
                    'user_id' => (int) $row->user_id,
                    'user_name' => $row->user_name,
                    'orders_picked' => (int) $row->orders_picked,
                    'items_picked' => (int) $row->items_picked,
                    'pick_errors' => (int) $row->pick_errors,
                    'avg_pick_seconds' => round((float) $row->avg_pick_seconds, 2),
                    'orders_packed' => (int) $row->orders_packed,
                    'items_packed' => (int) $row->items_packed,
                    'pack_errors' => (int) $row->pack_errors,
                    'avg_pack_seconds' => round((float) $row->avg_pack_seconds, 2),
                    'shipments_created' => (int) $row->shipments_created,
                    'returns_processed' => (int) $row->returns_processed,
                    'total_active_seconds' => (int) $row->total_active_seconds,
                    'days_active' => (int) $row->days_active,
                ])
                ->toArray();
        });
    }

    public function getLeaderboard(string $metric = 'items_picked', int $limit = 10): array
    {
        $allowedMetrics = [
            'items_picked', 'orders_picked', 'items_packed', 'orders_packed',
            'shipments_created', 'returns_processed',
        ];

        if (! in_array($metric, $allowedMetrics)) {
            $metric = 'items_picked';
        }

        return $this->cached("workforce_leaderboard_{$metric}_{$limit}", 900, function () use ($metric, $limit) {
            return DB::table('workforce_performance_daily')
                ->join('users', 'workforce_performance_daily.user_id', '=', 'users.id')
                ->where('workforce_performance_daily.organization_id', $this->organizationId)
                ->whereBetween('workforce_performance_daily.performance_date', [$this->startDate, $this->endDate])
                ->select([
                    'users.id as user_id',
                    'users.name as user_name',
                    DB::raw("COALESCE(SUM(workforce_performance_daily.{$metric}), 0) as metric_value"),
                ])
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('metric_value')
                ->limit($limit)
                ->get()
                ->values()
                ->map(fn ($row, $index) => [
                    'rank' => $index + 1,
                    'user_id' => (int) $row->user_id,
                    'user_name' => $row->user_name,
                    'metric' => $metric,
                    'value' => (int) $row->metric_value,
                ])
                ->toArray();
        });
    }

    public function getProductivityTrend(?int $userId = null): array
    {
        return $this->cached("workforce_productivity_trend_{$userId}", 900, function () use ($userId) {
            $query = DB::table('workforce_performance_daily')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('performance_date', [$this->startDate, $this->endDate]);

            if ($userId !== null) {
                $query->where('user_id', $userId);
            }

            return $query->select([
                DB::raw('performance_date as date'),
                DB::raw('COALESCE(SUM(items_picked), 0) as items_picked'),
                DB::raw('COALESCE(SUM(items_packed), 0) as items_packed'),
                DB::raw('COALESCE(SUM(shipments_created), 0) as shipments_created'),
            ])
                ->groupBy('performance_date')
                ->orderBy('performance_date')
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'items_picked' => (int) $row->items_picked,
                    'items_packed' => (int) $row->items_packed,
                    'shipments_created' => (int) $row->shipments_created,
                ])
                ->toArray();
        });
    }

    public function getErrorRates(): array
    {
        return $this->cached('workforce_error_rates', 900, function () {
            return DB::table('workforce_performance_daily')
                ->join('users', 'workforce_performance_daily.user_id', '=', 'users.id')
                ->where('workforce_performance_daily.organization_id', $this->organizationId)
                ->whereBetween('workforce_performance_daily.performance_date', [$this->startDate, $this->endDate])
                ->select([
                    'users.id as user_id',
                    'users.name as user_name',
                    DB::raw('COALESCE(SUM(workforce_performance_daily.pick_errors), 0) as pick_errors'),
                    DB::raw('COALESCE(SUM(workforce_performance_daily.pack_errors), 0) as pack_errors'),
                    DB::raw('COALESCE(SUM(workforce_performance_daily.items_picked), 0) + COALESCE(SUM(workforce_performance_daily.items_packed), 0) as total_items'),
                ])
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('pick_errors')
                ->get()
                ->map(fn ($row) => [
                    'user_id' => (int) $row->user_id,
                    'user_name' => $row->user_name,
                    'pick_errors' => (int) $row->pick_errors,
                    'pack_errors' => (int) $row->pack_errors,
                    'total_errors' => (int) $row->pick_errors + (int) $row->pack_errors,
                    'error_rate' => (int) $row->total_items > 0
                        ? round((((int) $row->pick_errors + (int) $row->pack_errors) / (int) $row->total_items) * 100, 2)
                        : 0,
                ])
                ->toArray();
        });
    }

    /**
     * Period-over-period comparison for the team (KPI #3).
     * `period` is 'week' (last 7d vs prior 7d) or 'month' (last 30d vs prior 30d).
     * Independent of the configured dateRange — anchors on now() to keep the
     * meaning of "this week" stable regardless of the active filter.
     */
    public function getPeriodComparison(string $period = 'week'): array
    {
        $days = $period === 'month' ? 30 : 7;
        $now = now()->endOfDay();
        $currentStart = $now->copy()->subDays($days - 1)->startOfDay();
        $previousEnd = $currentStart->copy()->subSecond();
        $previousStart = $previousEnd->copy()->subDays($days - 1)->startOfDay();

        $fetch = fn ($start, $end) => DB::table('workforce_performance_daily')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('performance_date', [$start->toDateString(), $end->toDateString()])
            ->select([
                DB::raw('COALESCE(SUM(items_picked), 0) as items_picked'),
                DB::raw('COALESCE(SUM(items_packed), 0) as items_packed'),
                DB::raw('COALESCE(SUM(shipments_created), 0) as shipments_created'),
                DB::raw('COALESCE(SUM(returns_processed), 0) as returns_processed'),
                DB::raw('COALESCE(SUM(pick_errors + pack_errors), 0) as total_errors'),
                DB::raw('COUNT(DISTINCT user_id) as active_users'),
            ])
            ->first();

        $current = $fetch($currentStart, $now);
        $previous = $fetch($previousStart, $previousEnd);

        $metrics = ['items_picked', 'items_packed', 'shipments_created', 'returns_processed', 'total_errors', 'active_users'];
        $changes = [];
        foreach ($metrics as $m) {
            $cur = (float) $current->$m;
            $prev = (float) $previous->$m;
            $changes[$m] = [
                'current' => (int) $cur,
                'previous' => (int) $prev,
                'change_pct' => $prev != 0 ? round((($cur - $prev) / abs($prev)) * 100, 1) : ($cur > 0 ? 100 : 0),
            ];
        }

        return [
            'period' => $period,
            'days' => $days,
            'current_range' => [$currentStart->toDateString(), $now->toDateString()],
            'previous_range' => [$previousStart->toDateString(), $previousEnd->toDateString()],
            'metrics' => $changes,
        ];
    }

    /**
     * Where the user sits vs every other active user on the team for one
     * metric (KPI #4). Returns rank, percentile, quartile, plus team
     * median/avg for context.
     */
    public function getUserPercentileRanking(int $userId, string $metric = 'items_picked'): array
    {
        $allowed = ['items_picked', 'items_packed', 'shipments_created', 'pick_errors', 'pack_errors', 'returns_processed'];
        if (! in_array($metric, $allowed)) {
            $metric = 'items_picked';
        }

        $rows = DB::table('workforce_performance_daily')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('performance_date', [$this->startDate, $this->endDate])
            ->select([
                'user_id',
                DB::raw("COALESCE(SUM({$metric}), 0) as metric_value"),
            ])
            ->groupBy('user_id')
            ->get();

        $values = $rows->pluck('metric_value')->map(fn ($v) => (float) $v)->sort()->values();
        $totalUsers = $values->count();

        if ($totalUsers === 0) {
            return [
                'metric' => $metric,
                'user_value' => 0,
                'rank' => null,
                'total_users' => 0,
                'percentile' => null,
                'quartile' => null,
                'team_median' => 0,
                'team_avg' => 0,
            ];
        }

        $userRow = $rows->firstWhere('user_id', $userId);
        $userValue = $userRow ? (float) $userRow->metric_value : 0.0;

        // Rank: 1 = highest. Count of users with strictly larger value + 1.
        $rank = $values->filter(fn ($v) => $v > $userValue)->count() + 1;
        $percentile = $totalUsers > 1
            ? round((($totalUsers - $rank) / ($totalUsers - 1)) * 100, 1)
            : 100.0;

        $quartile = match (true) {
            $percentile >= 75 => 1,
            $percentile >= 50 => 2,
            $percentile >= 25 => 3,
            default => 4,
        };

        $median = $totalUsers % 2 === 1
            ? $values[(int) floor($totalUsers / 2)]
            : ($values[$totalUsers / 2 - 1] + $values[$totalUsers / 2]) / 2;

        return [
            'metric' => $metric,
            'user_value' => $userValue,
            'rank' => $rank,
            'total_users' => $totalUsers,
            'percentile' => $percentile,
            'quartile' => $quartile,
            'team_median' => round($median, 2),
            'team_avg' => round($values->avg(), 2),
        ];
    }

    /**
     * Per-metric delta of one user vs team average (KPI #5).
     */
    public function getTeamVsIndividualDelta(int $userId): array
    {
        $userRow = DB::table('workforce_performance_daily')
            ->where('organization_id', $this->organizationId)
            ->where('user_id', $userId)
            ->whereBetween('performance_date', [$this->startDate, $this->endDate])
            ->select([
                DB::raw('COALESCE(SUM(items_picked), 0) as items_picked'),
                DB::raw('COALESCE(SUM(items_packed), 0) as items_packed'),
                DB::raw('COALESCE(SUM(shipments_created), 0) as shipments_created'),
                DB::raw('COALESCE(SUM(pick_errors), 0) as pick_errors'),
                DB::raw('COALESCE(SUM(pack_errors), 0) as pack_errors'),
                DB::raw('COALESCE(AVG(avg_pick_duration_seconds), 0) as avg_pick_seconds'),
                DB::raw('COALESCE(AVG(avg_pack_duration_seconds), 0) as avg_pack_seconds'),
            ])
            ->first();

        // Team avg = average per active user
        $teamRow = DB::table('workforce_performance_daily')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('performance_date', [$this->startDate, $this->endDate])
            ->select([
                DB::raw('COALESCE(SUM(items_picked), 0) as items_picked_total'),
                DB::raw('COALESCE(SUM(items_packed), 0) as items_packed_total'),
                DB::raw('COALESCE(SUM(shipments_created), 0) as shipments_created_total'),
                DB::raw('COALESCE(SUM(pick_errors), 0) as pick_errors_total'),
                DB::raw('COALESCE(SUM(pack_errors), 0) as pack_errors_total'),
                DB::raw('COALESCE(AVG(avg_pick_duration_seconds), 0) as avg_pick_seconds'),
                DB::raw('COALESCE(AVG(avg_pack_duration_seconds), 0) as avg_pack_seconds'),
                DB::raw('COUNT(DISTINCT user_id) as active_users'),
            ])
            ->first();

        $activeUsers = max(1, (int) $teamRow->active_users);

        $build = function (string $label, float $userValue, float $teamAvg, bool $lowerIsBetter = false) {
            $delta = $userValue - $teamAvg;
            $pct = $teamAvg != 0 ? round(($delta / abs($teamAvg)) * 100, 1) : ($userValue > 0 ? 100 : 0);
            // If lower is better (errors, durations), invert the trend so positive delta = bad
            $trend = $delta == 0 ? 'flat' : (($delta > 0) === $lowerIsBetter ? 'down' : 'up');

            return [
                'label' => $label,
                'user_value' => round($userValue, 2),
                'team_avg' => round($teamAvg, 2),
                'delta' => round($delta, 2),
                'delta_pct' => $pct,
                'trend' => $trend,
            ];
        };

        return [
            $build('Items Picked', (float) $userRow->items_picked, (float) $teamRow->items_picked_total / $activeUsers),
            $build('Items Packed', (float) $userRow->items_packed, (float) $teamRow->items_packed_total / $activeUsers),
            $build('Shipments', (float) $userRow->shipments_created, (float) $teamRow->shipments_created_total / $activeUsers),
            $build('Pick Errors', (float) $userRow->pick_errors, (float) $teamRow->pick_errors_total / $activeUsers, true),
            $build('Pack Errors', (float) $userRow->pack_errors, (float) $teamRow->pack_errors_total / $activeUsers, true),
            $build('Avg Pick (sec)', (float) $userRow->avg_pick_seconds, (float) $teamRow->avg_pick_seconds, true),
            $build('Avg Pack (sec)', (float) $userRow->avg_pack_seconds, (float) $teamRow->avg_pack_seconds, true),
        ];
    }

    /**
     * Daily error rate trend for one user (KPI #9 expanded).
     */
    public function getUserErrorTrend(int $userId): array
    {
        return DB::table('workforce_performance_daily')
            ->where('organization_id', $this->organizationId)
            ->where('user_id', $userId)
            ->whereBetween('performance_date', [$this->startDate, $this->endDate])
            ->select([
                'performance_date as date',
                DB::raw('COALESCE(SUM(pick_errors), 0) as pick_errors'),
                DB::raw('COALESCE(SUM(pack_errors), 0) as pack_errors'),
                DB::raw('COALESCE(SUM(items_picked + items_packed), 0) as total_items'),
            ])
            ->groupBy('performance_date')
            ->orderBy('performance_date')
            ->get()
            ->map(function ($row) {
                $errors = (int) $row->pick_errors + (int) $row->pack_errors;
                $items = (int) $row->total_items;

                return [
                    'date' => $row->date,
                    'pick_errors' => (int) $row->pick_errors,
                    'pack_errors' => (int) $row->pack_errors,
                    'error_rate' => $items > 0 ? round(($errors / $items) * 100, 2) : 0,
                ];
            })
            ->toArray();
    }

    /**
     * All users in the org with their basic activity totals across the
     * current date range. Used to render a permanent Team Members panel
     * that's reachable even when nightly aggregations are empty.
     */
    public function getAllOrgUsersWithStats(): array
    {
        $users = DB::table('users')
            ->where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->select(['id', 'name', 'email', 'profile_photo_path'])
            ->orderBy('name')
            ->get();

        if ($users->isEmpty()) {
            return [];
        }

        $stats = DB::table('workforce_performance_daily')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('performance_date', [$this->startDate, $this->endDate])
            ->whereIn('user_id', $users->pluck('id'))
            ->select([
                'user_id',
                DB::raw('COALESCE(SUM(items_picked), 0) as items_picked'),
                DB::raw('COALESCE(SUM(items_packed), 0) as items_packed'),
                DB::raw('COALESCE(SUM(shipments_created), 0) as shipments_created'),
                DB::raw('COALESCE(SUM(returns_processed), 0) as returns_processed'),
                DB::raw('COUNT(DISTINCT performance_date) as days_active'),
            ])
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return $users->map(function ($u) use ($stats) {
            $s = $stats->get($u->id);

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'items_picked' => (int) ($s->items_picked ?? 0),
                'items_packed' => (int) ($s->items_packed ?? 0),
                'shipments_created' => (int) ($s->shipments_created ?? 0),
                'returns_processed' => (int) ($s->returns_processed ?? 0),
                'days_active' => (int) ($s->days_active ?? 0),
            ];
        })->toArray();
    }

    /**
     * Per-session breakdown for a user (KPI #25). For each session_id in
     * user_activity_logs we compute start/end timestamps, total duration,
     * event count, and the longest idle gap inside the session.
     *
     * Idle gap = the largest delta between consecutive events in seconds.
     * Useful for spotting workers leaving a session open without activity.
     */
    public function getUserSessionStats(int $userId, int $idleThresholdSeconds = 300): array
    {
        $rows = DB::table('user_activity_logs')
            ->where('organization_id', $this->organizationId)
            ->where('user_id', $userId)
            ->whereNotNull('session_id')
            ->whereBetween('performed_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
            ->orderBy('session_id')
            ->orderBy('performed_at')
            ->get(['session_id', 'performed_at']);

        if ($rows->isEmpty()) {
            return [];
        }

        $sessions = [];
        $current = null;

        foreach ($rows as $row) {
            $ts = strtotime($row->performed_at);
            if ($current === null || $current['session_id'] !== $row->session_id) {
                if ($current !== null) {
                    $sessions[] = $this->finalizeSession($current);
                }
                $current = [
                    'session_id' => $row->session_id,
                    'started_at' => $row->performed_at,
                    'ended_at' => $row->performed_at,
                    'event_count' => 1,
                    'longest_idle' => 0,
                    'total_idle' => 0,
                    'last_ts' => $ts,
                    'idle_threshold' => $idleThresholdSeconds,
                ];
            } else {
                $gap = $ts - $current['last_ts'];
                if ($gap > $current['longest_idle']) {
                    $current['longest_idle'] = $gap;
                }
                if ($gap > $idleThresholdSeconds) {
                    $current['total_idle'] += $gap;
                }
                $current['event_count']++;
                $current['ended_at'] = $row->performed_at;
                $current['last_ts'] = $ts;
            }
        }

        if ($current !== null) {
            $sessions[] = $this->finalizeSession($current);
        }

        usort($sessions, fn ($a, $b) => strcmp($b['started_at'], $a['started_at']));

        return $sessions;
    }

    private function finalizeSession(array $s): array
    {
        $duration = max(0, strtotime($s['ended_at']) - strtotime($s['started_at']));

        return [
            'session_id' => $s['session_id'],
            'started_at' => $s['started_at'],
            'ended_at' => $s['ended_at'],
            'duration_seconds' => $duration,
            'duration_minutes' => round($duration / 60, 1),
            'event_count' => $s['event_count'],
            'longest_idle_minutes' => round($s['longest_idle'] / 60, 1),
            'total_idle_minutes' => round($s['total_idle'] / 60, 1),
            'active_minutes' => round(max(0, $duration - $s['total_idle']) / 60, 1),
        ];
    }

    /**
     * Org-wide / per-user roll-up of the per-session stats — averages and
     * extremes for the dashboard summary.
     */
    public function getUserSessionAggregates(int $userId, int $idleThresholdSeconds = 300): array
    {
        $sessions = $this->getUserSessionStats($userId, $idleThresholdSeconds);

        if (empty($sessions)) {
            return [
                'session_count' => 0,
                'avg_session_minutes' => 0,
                'longest_session_minutes' => 0,
                'avg_idle_minutes' => 0,
                'total_idle_minutes' => 0,
                'avg_active_minutes' => 0,
            ];
        }

        $count = count($sessions);
        $totalDuration = array_sum(array_column($sessions, 'duration_minutes'));
        $totalIdle = array_sum(array_column($sessions, 'total_idle_minutes'));
        $totalActive = array_sum(array_column($sessions, 'active_minutes'));
        $longest = max(array_column($sessions, 'duration_minutes'));

        return [
            'session_count' => $count,
            'avg_session_minutes' => round($totalDuration / $count, 1),
            'longest_session_minutes' => round($longest, 1),
            'avg_idle_minutes' => round($totalIdle / $count, 1),
            'total_idle_minutes' => round($totalIdle, 1),
            'avg_active_minutes' => round($totalActive / $count, 1),
        ];
    }

    /**
     * Aggregated KPIs for one user across the date range. Same shape as one
     * row of getIndividualPerformance() but always scoped to a single user.
     */
    public function getUserAggregatedKPIs(int $userId): array
    {
        return $this->cached("workforce_user_kpis_{$userId}", 900, function () use ($userId) {
            $row = DB::table('workforce_performance_daily')
                ->where('organization_id', $this->organizationId)
                ->where('user_id', $userId)
                ->whereBetween('performance_date', [$this->startDate, $this->endDate])
                ->select([
                    DB::raw('COALESCE(SUM(orders_picked), 0) as orders_picked'),
                    DB::raw('COALESCE(SUM(items_picked), 0) as items_picked'),
                    DB::raw('COALESCE(SUM(pick_errors), 0) as pick_errors'),
                    DB::raw('COALESCE(AVG(avg_pick_duration_seconds), 0) as avg_pick_seconds'),
                    DB::raw('COALESCE(SUM(orders_packed), 0) as orders_packed'),
                    DB::raw('COALESCE(SUM(items_packed), 0) as items_packed'),
                    DB::raw('COALESCE(SUM(pack_errors), 0) as pack_errors'),
                    DB::raw('COALESCE(AVG(avg_pack_duration_seconds), 0) as avg_pack_seconds'),
                    DB::raw('COALESCE(SUM(labels_purchased), 0) as labels_purchased'),
                    DB::raw('COALESCE(SUM(shipments_created), 0) as shipments_created'),
                    DB::raw('COALESCE(SUM(returns_processed), 0) as returns_processed'),
                    DB::raw('COALESCE(SUM(total_active_seconds), 0) as total_active_seconds'),
                    DB::raw('COUNT(DISTINCT performance_date) as days_active'),
                ])
                ->first();

            $itemsTotal = (int) $row->items_picked + (int) $row->items_packed;
            $errorsTotal = (int) $row->pick_errors + (int) $row->pack_errors;

            return [
                'orders_picked' => (int) $row->orders_picked,
                'items_picked' => (int) $row->items_picked,
                'pick_errors' => (int) $row->pick_errors,
                'avg_pick_seconds' => round((float) $row->avg_pick_seconds, 2),
                'orders_packed' => (int) $row->orders_packed,
                'items_packed' => (int) $row->items_packed,
                'pack_errors' => (int) $row->pack_errors,
                'avg_pack_seconds' => round((float) $row->avg_pack_seconds, 2),
                'labels_purchased' => (int) $row->labels_purchased,
                'shipments_created' => (int) $row->shipments_created,
                'returns_processed' => (int) $row->returns_processed,
                'total_active_seconds' => (int) $row->total_active_seconds,
                'days_active' => (int) $row->days_active,
                'error_rate' => $itemsTotal > 0 ? round(($errorsTotal / $itemsTotal) * 100, 2) : 0,
            ];
        });
    }

    /**
     * Cross-functional activity timeline for one user (KPI #1).
     * Pulls from user_activity_logs which captures every domain event.
     * Limited and paginated by the caller to avoid blowing the page up.
     */
    public function getUserActivityTimeline(int $userId, int $limit = 100): array
    {
        return DB::table('user_activity_logs')
            ->where('organization_id', $this->organizationId)
            ->where('user_id', $userId)
            ->whereBetween('performed_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
            ->orderByDesc('performed_at')
            ->limit($limit)
            ->get([
                'id',
                'action',
                'description',
                'metadata',
                'performed_at',
                'session_id',
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'action' => $row->action,
                'description' => $row->description,
                'metadata' => is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata,
                'performed_at' => $row->performed_at,
                'session_id' => $row->session_id,
            ])
            ->toArray();
    }

    /**
     * Activity heatmap for one user — count of events grouped by
     * day-of-week (0 = Sunday) and hour-of-day (KPI #24).
     * Uses local Postgres EXTRACT — caller can timezone-shift in UI if needed.
     */
    public function getUserActivityHeatmap(int $userId): array
    {
        return DB::table('user_activity_logs')
            ->where('organization_id', $this->organizationId)
            ->where('user_id', $userId)
            ->whereBetween('performed_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
            ->select([
                DB::raw('EXTRACT(DOW FROM performed_at)::int as day_of_week'),
                DB::raw('EXTRACT(HOUR FROM performed_at)::int as hour_of_day'),
                DB::raw('COUNT(*) as event_count'),
            ])
            ->groupBy('day_of_week', 'hour_of_day')
            ->orderBy('day_of_week')
            ->orderBy('hour_of_day')
            ->get()
            ->map(fn ($row) => [
                'day_of_week' => (int) $row->day_of_week,
                'hour_of_day' => (int) $row->hour_of_day,
                'event_count' => (int) $row->event_count,
            ])
            ->toArray();
    }

    /**
     * Concurrent active users by hour of day (KPI #26) for the team.
     * "Concurrent" defined as distinct users with at least one event in
     * a given hour — coarse but useful for shift-load visualization.
     */
    public function getConcurrentUsersByHour(): array
    {
        return DB::table('user_activity_logs')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('performed_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
            ->select([
                DB::raw('EXTRACT(HOUR FROM performed_at)::int as hour_of_day'),
                DB::raw('COUNT(DISTINCT user_id) as active_users'),
            ])
            ->groupBy('hour_of_day')
            ->orderBy('hour_of_day')
            ->get()
            ->map(fn ($row) => [
                'hour_of_day' => (int) $row->hour_of_day,
                'active_users' => (int) $row->active_users,
            ])
            ->toArray();
    }

    public function getTeamComparison(): array
    {
        return $this->cached('workforce_team_comparison', 900, function () {
            return DB::table('workforce_performance_daily')
                ->join('users', 'workforce_performance_daily.user_id', '=', 'users.id')
                ->join('team_user', 'users.id', '=', 'team_user.user_id')
                ->join('teams', 'team_user.team_id', '=', 'teams.id')
                ->where('workforce_performance_daily.organization_id', $this->organizationId)
                ->whereBetween('workforce_performance_daily.performance_date', [$this->startDate, $this->endDate])
                ->select([
                    'teams.id as team_id',
                    'teams.name as team_name',
                    DB::raw('COUNT(DISTINCT workforce_performance_daily.user_id) as member_count'),
                    DB::raw('COALESCE(SUM(workforce_performance_daily.items_picked), 0) as items_picked'),
                    DB::raw('COALESCE(SUM(workforce_performance_daily.items_packed), 0) as items_packed'),
                    DB::raw('COALESCE(SUM(workforce_performance_daily.shipments_created), 0) as shipments_created'),
                    DB::raw('COALESCE(SUM(workforce_performance_daily.pick_errors + workforce_performance_daily.pack_errors), 0) as total_errors'),
                ])
                ->groupBy('teams.id', 'teams.name')
                ->orderByDesc('items_picked')
                ->get()
                ->map(fn ($row) => [
                    'team_id' => (int) $row->team_id,
                    'team_name' => $row->team_name,
                    'member_count' => (int) $row->member_count,
                    'items_picked' => (int) $row->items_picked,
                    'items_packed' => (int) $row->items_packed,
                    'shipments_created' => (int) $row->shipments_created,
                    'total_errors' => (int) $row->total_errors,
                ])
                ->toArray();
        });
    }
}
