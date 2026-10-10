<?php

namespace App\Jobs\Amazon;

use App\Models\Amazon\AmazonListing;
use App\Models\Integration\IntegrationCredential;
use App\Services\Amazon\AmazonInventoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Pull FBA inventory summaries for all active Amazon credentials.
 *
 * FBA quantity is Amazon-authoritative — we never push, we only mirror.
 * Updates AmazonListing.fba_quantity on rows whose fulfillment_channel = FBA.
 */
class PullFbaInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public ?int $credentialId = null)
    {
        $this->onQueue('amazon-inventory');
    }

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
        $fbaListings = AmazonListing::where('integration_credential_id', $credential->id)
            ->where('fulfillment_channel', AmazonListing::FULFILLMENT_FBA)
            ->whereNotIn('status', [AmazonListing::STATUS_DELETED])
            ->get();

        if ($fbaListings->isEmpty()) {
            return;
        }

        try {
            $service = new AmazonInventoryService($credential);

            // Chunk SKUs to avoid SP-API URL length limits (50 SKUs/req is safe)
            $fbaListings->chunk(50)->each(function ($chunk) use ($service) {
                $skus = $chunk->pluck('amazon_sku')->filter()->values()->all();

                if (empty($skus)) {
                    return;
                }

                $summaries = $service->getFbaInventory($skus);
                $bySku = collect($summaries)->keyBy('sellerSku');

                foreach ($chunk as $listing) {
                    $summary = $bySku->get($listing->amazon_sku);
                    if (! $summary) {
                        continue;
                    }

                    $fulfillable = $summary['inventoryDetails']['fulfillableQuantity'] ?? null;
                    $totalQty = $summary['totalQuantity'] ?? $fulfillable;

                    if ($totalQty === null) {
                        continue;
                    }

                    $listing->update([
                        'fba_quantity' => (int) $totalQty,
                        'last_synced_at' => now(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('PullFbaInventoryJob: sync failed', [
                'credential_id' => $credential->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PullFbaInventoryJob failed', [
            'credential_id' => $this->credentialId,
            'error' => $exception->getMessage(),
        ]);
    }
}
