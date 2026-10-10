<?php

namespace App\Services\Marketing;

use App\Models\Fulfillment\Order;
use App\Models\Marketing\EmailCampaign;
use App\Models\Marketing\EmailCampaignSend;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmailCampaignService
{
    public function create(array $data, string $orgId): EmailCampaign
    {
        $data['organization_id'] = $orgId;

        return EmailCampaign::create($data);
    }

    public function update(EmailCampaign $campaign, array $data): EmailCampaign
    {
        $campaign->update($data);

        return $campaign->fresh();
    }

    public function toggle(EmailCampaign $campaign): void
    {
        $campaign->update(['is_active' => ! $campaign->is_active]);
    }

    public function getStats(EmailCampaign $campaign): array
    {
        $counts = $campaign->sends()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $sent = $counts[EmailCampaignSend::STATUS_SENT] ?? 0;
        $delivered = $counts[EmailCampaignSend::STATUS_DELIVERED] ?? 0;
        $opened = $counts[EmailCampaignSend::STATUS_OPENED] ?? 0;
        $clicked = $counts[EmailCampaignSend::STATUS_CLICKED] ?? 0;
        $bounced = $counts[EmailCampaignSend::STATUS_BOUNCED] ?? 0;
        $unsubscribed = $counts[EmailCampaignSend::STATUS_UNSUBSCRIBED] ?? 0;
        $pending = $counts[EmailCampaignSend::STATUS_PENDING] ?? 0;

        $totalSent = $sent + $delivered + $opened + $clicked;

        return [
            'pending' => $pending,
            'sent' => $sent,
            'delivered' => $delivered,
            'opened' => $opened,
            'clicked' => $clicked,
            'bounced' => $bounced,
            'unsubscribed' => $unsubscribed,
            'total_sent' => $totalSent,
            'open_rate' => $totalSent > 0 ? round(($opened / $totalSent) * 100, 2) : 0,
            'click_rate' => $totalSent > 0 ? round(($clicked / $totalSent) * 100, 2) : 0,
            'bounce_rate' => $totalSent > 0 ? round(($bounced / $totalSent) * 100, 2) : 0,
        ];
    }

    /**
     * Find orders matching campaign trigger that haven't been sent yet.
     */
    public function findEligibleOrders(EmailCampaign $campaign): Collection
    {
        $alreadySentOrderIds = $campaign->sends()->pluck('order_id')->filter()->toArray();

        $query = Order::where('organization_id', $campaign->organization_id);

        switch ($campaign->trigger_event) {
            case EmailCampaign::TRIGGER_ORDER_SHIPPED:
                $query->whereNotNull('shipped_at')
                    ->where('shipped_at', '<=', now()->subHours($campaign->trigger_delay_hours));
                break;

            case EmailCampaign::TRIGGER_ORDER_DELIVERED:
                $query->whereNotNull('delivered_at')
                    ->where('delivered_at', '<=', now()->subHours($campaign->trigger_delay_hours));
                break;

            case EmailCampaign::TRIGGER_DAYS_AFTER_DELIVERY:
                $query->whereNotNull('delivered_at')
                    ->where('delivered_at', '<=', now()->subDays(max(1, (int) ($campaign->trigger_delay_hours / 24))));
                break;

            default:
                Log::warning('Unknown trigger event for campaign', [
                    'campaign_id' => $campaign->id,
                    'trigger_event' => $campaign->trigger_event,
                ]);

                return collect();
        }

        if (! empty($alreadySentOrderIds)) {
            $query->whereNotIn('id', $alreadySentOrderIds);
        }

        return $query->limit(100)->get();
    }
}
