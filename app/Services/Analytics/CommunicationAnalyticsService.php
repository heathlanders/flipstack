<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Concerns\AnalyticsQueryTrait;
use Illuminate\Support\Facades\DB;

class CommunicationAnalyticsService
{
    use AnalyticsQueryTrait;

    public function getOverview(): array
    {
        return $this->cached('communication_overview', 900, function () {
            $total = DB::table('marketplace_messages')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->count();

            $avgResponse = DB::table('marketplace_messages')
                ->where('organization_id', $this->organizationId)
                ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->where('direction', 'inbound')
                ->whereNotNull('replied_at')
                ->whereNotNull('received_at')
                ->select([
                    DB::raw('AVG(EXTRACT(EPOCH FROM (replied_at - received_at)) / 3600) as avg_hours'),
                ])
                ->first();

            $sla = $this->getSLACompliance();

            $unread = DB::table('marketplace_messages')
                ->where('organization_id', $this->organizationId)
                ->where('status', 'unread')
                ->where('direction', 'inbound')
                ->count();

            return [
                'total_messages' => $total,
                'avg_response_hours' => round((float) ($avgResponse->avg_hours ?? 0), 1),
                'sla_compliance_pct' => $sla['rate'],
                'unread_count' => $unread,
            ];
        });
    }

    public function getResponseTime(): array
    {
        $stats = DB::table('marketplace_messages')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->where('direction', 'inbound')
            ->whereNotNull('replied_at')
            ->whereNotNull('received_at')
            ->select([
                DB::raw('AVG(EXTRACT(EPOCH FROM (replied_at - received_at)) / 3600) as avg_hours'),
                DB::raw('PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (replied_at - received_at)) / 3600) as median_hours'),
                DB::raw('PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (replied_at - received_at)) / 3600) as p95_hours'),
            ])
            ->first();

        return [
            'avg_hours' => round((float) ($stats->avg_hours ?? 0), 1),
            'median_hours' => round((float) ($stats->median_hours ?? 0), 1),
            'p95_hours' => round((float) ($stats->p95_hours ?? 0), 1),
        ];
    }

    public function getSLACompliance(int $hoursTarget = 24): array
    {
        $total = DB::table('marketplace_messages')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->where('direction', 'inbound')
            ->whereNotNull('replied_at')
            ->count();

        $withinTarget = DB::table('marketplace_messages')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->where('direction', 'inbound')
            ->whereNotNull('replied_at')
            ->whereNotNull('received_at')
            ->whereRaw('EXTRACT(EPOCH FROM (replied_at - received_at)) / 3600 <= ?', [$hoursTarget])
            ->count();

        return [
            'total' => $total,
            'within_target' => $withinTarget,
            'rate' => $total > 0 ? round(($withinTarget / $total) * 100, 1) : 100,
            'target_hours' => $hoursTarget,
        ];
    }

    public function getMessageVolumeTrend(): array
    {
        return DB::table('marketplace_messages')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw("SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound_count"),
                DB::raw("SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound_count"),
            ])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'inbound_count' => (int) $row->inbound_count,
                'outbound_count' => (int) $row->outbound_count,
            ])
            ->toArray();
    }

    public function getUnreadBacklog(): array
    {
        $buckets = DB::table('marketplace_messages')
            ->where('organization_id', $this->organizationId)
            ->where('status', 'unread')
            ->where('direction', 'inbound')
            ->select([
                DB::raw("SUM(CASE WHEN created_at >= NOW() - INTERVAL '1 hour' THEN 1 ELSE 0 END) as within_1h"),
                DB::raw("SUM(CASE WHEN created_at >= NOW() - INTERVAL '4 hours' AND created_at < NOW() - INTERVAL '1 hour' THEN 1 ELSE 0 END) as h1_to_4"),
                DB::raw("SUM(CASE WHEN created_at >= NOW() - INTERVAL '12 hours' AND created_at < NOW() - INTERVAL '4 hours' THEN 1 ELSE 0 END) as h4_to_12"),
                DB::raw("SUM(CASE WHEN created_at >= NOW() - INTERVAL '24 hours' AND created_at < NOW() - INTERVAL '12 hours' THEN 1 ELSE 0 END) as h12_to_24"),
                DB::raw("SUM(CASE WHEN created_at < NOW() - INTERVAL '24 hours' THEN 1 ELSE 0 END) as over_24h"),
            ])
            ->first();

        return [
            ['label' => '< 1 hour', 'count' => (int) ($buckets->within_1h ?? 0)],
            ['label' => '1-4 hours', 'count' => (int) ($buckets->h1_to_4 ?? 0)],
            ['label' => '4-12 hours', 'count' => (int) ($buckets->h4_to_12 ?? 0)],
            ['label' => '12-24 hours', 'count' => (int) ($buckets->h12_to_24 ?? 0)],
            ['label' => '24+ hours', 'count' => (int) ($buckets->over_24h ?? 0)],
        ];
    }

    public function getByMarketplace(): array
    {
        return DB::table('marketplace_messages')
            ->where('organization_id', $this->organizationId)
            ->whereBetween('created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->select([
                'marketplace',
                DB::raw('COUNT(*) as total_messages'),
                DB::raw("AVG(CASE WHEN direction = 'inbound' AND replied_at IS NOT NULL AND received_at IS NOT NULL THEN EXTRACT(EPOCH FROM (replied_at - received_at)) / 3600 END) as avg_response_hours"),
                DB::raw("SUM(CASE WHEN direction = 'inbound' AND replied_at IS NOT NULL AND received_at IS NOT NULL AND EXTRACT(EPOCH FROM (replied_at - received_at)) / 3600 <= 24 THEN 1 ELSE 0 END)::float / NULLIF(SUM(CASE WHEN direction = 'inbound' AND replied_at IS NOT NULL THEN 1 ELSE 0 END), 0) * 100 as sla_rate"),
            ])
            ->groupBy('marketplace')
            ->orderByDesc('total_messages')
            ->get()
            ->map(fn ($row) => [
                'marketplace' => $row->marketplace,
                'total_messages' => (int) $row->total_messages,
                'avg_response_hours' => round((float) ($row->avg_response_hours ?? 0), 1),
                'sla_rate' => round((float) ($row->sla_rate ?? 0), 1),
            ])
            ->toArray();
    }
}
