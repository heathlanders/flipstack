<?php

namespace App\Jobs\Ebay;

use App\Models\Ebay\EbayPromotedCampaign;
use App\Services\Ebay\EbayPromotedListingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncPromotedListingStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300;

    public function __construct(
        protected string $campaignId
    ) {
        $this->onQueue('ebay');
    }

    public function handle(EbayPromotedListingsService $service): void
    {
        $campaign = EbayPromotedCampaign::find($this->campaignId);

        if (! $campaign) {
            Log::warning('SyncPromotedListingStats: campaign not found', [
                'campaign_id' => $this->campaignId,
            ]);

            return;
        }

        $service->syncStats($campaign);
    }

    public function tags(): array
    {
        return ['promoted-listings', 'campaign:'.$this->campaignId];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncPromotedListingStats: job failed', [
            'campaign_id' => $this->campaignId,
            'error' => $exception->getMessage(),
        ]);
    }
}
