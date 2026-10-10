<?php

namespace App\Jobs\Marketing;

use App\Models\Marketing\EmailCampaign;
use App\Models\Marketing\EmailCampaignSend;
use App\Services\Marketing\EmailCampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessEmailCampaignTriggers implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(EmailCampaignService $campaignService): void
    {
        $activeCampaigns = EmailCampaign::active()->get();

        foreach ($activeCampaigns as $campaign) {
            try {
                $eligibleOrders = $campaignService->findEligibleOrders($campaign);

                foreach ($eligibleOrders as $order) {
                    $send = EmailCampaignSend::create([
                        'campaign_id' => $campaign->id,
                        'customer_id' => $order->customer_id,
                        'order_id' => $order->id,
                        'status' => EmailCampaignSend::STATUS_PENDING,
                    ]);

                    SendCampaignEmail::dispatch($send->id);
                }

                if ($eligibleOrders->isNotEmpty()) {
                    Log::info('Queued campaign emails', [
                        'campaign_id' => $campaign->id,
                        'campaign_name' => $campaign->name,
                        'count' => $eligibleOrders->count(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to process campaign triggers', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function tags(): array
    {
        return ['email-campaigns'];
    }
}
