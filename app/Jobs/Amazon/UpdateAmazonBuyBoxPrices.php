<?php

namespace App\Jobs\Amazon;

use App\Models\Amazon\AmazonListing;
use App\Models\Integration\IntegrationCredential;
use App\Services\Amazon\AmazonPricingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Refresh AmazonListing.buy_box_price for active listings via the SP-API
 * Pricing API. Fed into RepricingEngine's STRATEGY_MATCH_BUY_BOX +
 * STRATEGY_BEAT_BUY_BOX strategies.
 *
 * Operates per-credential, chunks ASINs at 20/req (SP-API cap), handles
 * partial failures so one bad ASIN doesn't poison the whole batch.
 */
class UpdateAmazonBuyBoxPrices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public ?int $credentialId = null) {}

    public function handle(): void
    {
        $credentials = $this->credentialId
            ? IntegrationCredential::where('id', $this->credentialId)->get()
            : IntegrationCredential::where('platform', 'amazon')
                ->where('is_active', true)
                ->get();

        foreach ($credentials as $credential) {
            if (! $credential->isAmazonReady()) {
                continue;
            }

            $this->syncCredential($credential);
        }
    }

    protected function syncCredential(IntegrationCredential $credential): void
    {
        $listings = AmazonListing::where('integration_credential_id', $credential->id)
            ->whereIn('status', [AmazonListing::STATUS_ACTIVE, AmazonListing::STATUS_CREATED])
            ->whereNotNull('asin')
            ->get();

        if ($listings->isEmpty()) {
            return;
        }

        $service = new AmazonPricingService($credential);
        $byAsin = $listings->keyBy('asin');

        $listings->pluck('asin')->unique()->values()->chunk(20)->each(function ($asinChunk) use ($service, $byAsin) {
            try {
                $payload = $service->getCompetitivePricing($asinChunk->all());
            } catch (\Throwable $e) {
                Log::warning('UpdateAmazonBuyBoxPrices: chunk fetch failed', [
                    'asin_count' => $asinChunk->count(),
                    'error' => $e->getMessage(),
                ]);

                return;
            }

            foreach ($payload as $entry) {
                $asin = $entry['ASIN'] ?? $entry['Product']['Identifiers']['MarketplaceASIN']['ASIN'] ?? null;
                if (! $asin || ! $byAsin->has($asin)) {
                    continue;
                }

                $price = $this->extractBuyBoxPrice($entry);
                if ($price === null) {
                    continue;
                }

                $byAsin->get($asin)->update([
                    'buy_box_price' => $price,
                    'last_synced_at' => now(),
                ]);
            }
        });
    }

    /**
     * SP-API competitivePrice payload nests offer prices under
     * Product.CompetitivePricing.CompetitivePrices[]. The Buy Box winner
     * has CompetitivePriceId = '1'.
     */
    protected function extractBuyBoxPrice(array $entry): ?float
    {
        $prices = $entry['Product']['CompetitivePricing']['CompetitivePrices']
            ?? $entry['CompetitivePricing']['CompetitivePrices']
            ?? [];

        foreach ($prices as $price) {
            if (($price['CompetitivePriceId'] ?? null) !== '1') {
                continue;
            }

            $listing = $price['Price']['ListingPrice']['Amount']
                ?? $price['ListingPrice']['Amount']
                ?? null;

            if ($listing !== null) {
                return (float) $listing;
            }
        }

        return null;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('UpdateAmazonBuyBoxPrices failed', [
            'credential_id' => $this->credentialId,
            'error' => $exception->getMessage(),
        ]);
    }
}
