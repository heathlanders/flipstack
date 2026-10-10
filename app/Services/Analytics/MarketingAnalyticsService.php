<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Concerns\AnalyticsQueryTrait;
use Illuminate\Support\Facades\DB;

class MarketingAnalyticsService
{
    use AnalyticsQueryTrait;

    public function getOverview(): array
    {
        return $this->cached('marketing_overview', 900, function () {
            $campaignsSent = DB::table('email_campaign_sends')
                ->join('email_campaigns', 'email_campaign_sends.campaign_id', '=', 'email_campaigns.id')
                ->where('email_campaigns.organization_id', $this->organizationId)
                ->whereBetween('email_campaign_sends.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->distinct('email_campaign_sends.campaign_id')
                ->count('email_campaign_sends.campaign_id');

            $sends = DB::table('email_campaign_sends')
                ->join('email_campaigns', 'email_campaign_sends.campaign_id', '=', 'email_campaigns.id')
                ->where('email_campaigns.organization_id', $this->organizationId)
                ->whereBetween('email_campaign_sends.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
                ->select([
                    DB::raw('COUNT(*) as total_sends'),
                    DB::raw("SUM(CASE WHEN email_campaign_sends.status IN ('opened', 'clicked') THEN 1 ELSE 0 END) as opened"),
                    DB::raw("SUM(CASE WHEN email_campaign_sends.status = 'clicked' THEN 1 ELSE 0 END) as clicked"),
                    DB::raw("SUM(CASE WHEN email_campaign_sends.status = 'bounced' THEN 1 ELSE 0 END) as bounced"),
                ])
                ->first();

            $totalSends = (int) ($sends->total_sends ?? 0);

            return [
                'campaigns_sent' => $campaignsSent,
                'total_sends' => $totalSends,
                'open_rate' => $totalSends > 0 ? round(((int) $sends->opened / $totalSends) * 100, 1) : 0,
                'click_rate' => $totalSends > 0 ? round(((int) $sends->clicked / $totalSends) * 100, 1) : 0,
                'bounce_rate' => $totalSends > 0 ? round(((int) $sends->bounced / $totalSends) * 100, 1) : 0,
            ];
        });
    }

    public function getCampaignPerformance(): array
    {
        return DB::table('email_campaigns')
            ->where('email_campaigns.organization_id', $this->organizationId)
            ->whereBetween('email_campaigns.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->leftJoin('email_campaign_sends', 'email_campaigns.id', '=', 'email_campaign_sends.campaign_id')
            ->select([
                'email_campaigns.id',
                'email_campaigns.name',
                'email_campaigns.is_active',
                DB::raw('COUNT(email_campaign_sends.id) as sends'),
                DB::raw("SUM(CASE WHEN email_campaign_sends.status IN ('delivered', 'opened', 'clicked') THEN 1 ELSE 0 END) as delivered"),
                DB::raw("SUM(CASE WHEN email_campaign_sends.status IN ('opened', 'clicked') THEN 1 ELSE 0 END) as opened"),
                DB::raw("SUM(CASE WHEN email_campaign_sends.status = 'clicked' THEN 1 ELSE 0 END) as clicked"),
                DB::raw("SUM(CASE WHEN email_campaign_sends.status = 'bounced' THEN 1 ELSE 0 END) as bounced"),
            ])
            ->groupBy('email_campaigns.id', 'email_campaigns.name', 'email_campaigns.is_active')
            ->orderByDesc(DB::raw('COUNT(email_campaign_sends.id)'))
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'status' => $row->is_active ? 'active' : 'inactive',
                'sends' => (int) $row->sends,
                'delivered' => (int) $row->delivered,
                'opened' => (int) $row->opened,
                'clicked' => (int) $row->clicked,
                'bounced' => (int) $row->bounced,
                'open_rate' => (int) $row->sends > 0 ? round(((int) $row->opened / (int) $row->sends) * 100, 1) : 0,
                'click_rate' => (int) $row->sends > 0 ? round(((int) $row->clicked / (int) $row->sends) * 100, 1) : 0,
            ])
            ->toArray();
    }

    public function getDeliverability(): array
    {
        $stats = DB::table('email_campaign_sends')
            ->join('email_campaigns', 'email_campaign_sends.campaign_id', '=', 'email_campaigns.id')
            ->where('email_campaigns.organization_id', $this->organizationId)
            ->whereBetween('email_campaign_sends.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->select([
                DB::raw('COUNT(*) as sent'),
                DB::raw("SUM(CASE WHEN email_campaign_sends.status IN ('delivered', 'opened', 'clicked') THEN 1 ELSE 0 END) as delivered"),
                DB::raw("SUM(CASE WHEN email_campaign_sends.status = 'bounced' THEN 1 ELSE 0 END) as bounced"),
                DB::raw("SUM(CASE WHEN email_campaign_sends.status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed"),
            ])
            ->first();

        $sent = (int) ($stats->sent ?? 0);

        return [
            'sent' => $sent,
            'delivered' => (int) ($stats->delivered ?? 0),
            'bounced' => (int) ($stats->bounced ?? 0),
            'unsubscribed' => (int) ($stats->unsubscribed ?? 0),
            'delivery_rate' => $sent > 0 ? round(((int) $stats->delivered / $sent) * 100, 1) : 0,
            'bounce_rate' => $sent > 0 ? round(((int) $stats->bounced / $sent) * 100, 1) : 0,
        ];
    }

    public function getEngagementTrend(): array
    {
        return DB::table('email_campaign_sends')
            ->join('email_campaigns', 'email_campaign_sends.campaign_id', '=', 'email_campaigns.id')
            ->where('email_campaigns.organization_id', $this->organizationId)
            ->whereBetween('email_campaign_sends.created_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->select([
                DB::raw('DATE(email_campaign_sends.created_at) as date'),
                DB::raw('COUNT(*) as sends'),
                DB::raw("SUM(CASE WHEN email_campaign_sends.status IN ('opened', 'clicked') THEN 1 ELSE 0 END)::float / NULLIF(COUNT(*), 0) * 100 as open_rate"),
                DB::raw("SUM(CASE WHEN email_campaign_sends.status = 'clicked' THEN 1 ELSE 0 END)::float / NULLIF(COUNT(*), 0) * 100 as click_rate"),
            ])
            ->groupBy(DB::raw('DATE(email_campaign_sends.created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'sends' => (int) $row->sends,
                'open_rate' => round((float) ($row->open_rate ?? 0), 1),
                'click_rate' => round((float) ($row->click_rate ?? 0), 1),
            ])
            ->toArray();
    }
}
