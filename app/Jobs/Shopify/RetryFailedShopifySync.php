<?php

namespace App\Jobs\Shopify;

use App\Models\Integration\IntegrationCredential;
use App\Models\Shopify\ShopifyListing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedShopifySync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    protected int $maxConsecutiveErrors = 5;

    public function __construct(
        public ?IntegrationCredential $credential = null,
        public int $limit = 50
    ) {
        $this->onQueue('shopify-retry');
    }

    public function handle(): void
    {
        $query = ShopifyListing::query()
            ->where('status', ShopifyListing::STATUS_ERROR)
            ->where('consecutive_errors', '<', $this->maxConsecutiveErrors)
            ->whereNotNull('last_error_at')
            ->where('last_error_at', '<', now()->subMinutes(5))
            ->orderBy('last_error_at', 'asc');

        if ($this->credential) {
            $query->where('integration_credential_id', $this->credential->id);
        }

        $listings = $query->limit($this->limit)->get();

        if ($listings->isEmpty()) {
            return;
        }

        Log::info('RetryFailedShopifySync: Found listings to retry', [
            'count' => $listings->count(),
        ]);

        $retried = 0;

        foreach ($listings as $index => $listing) {
            $credential = $listing->integrationCredential;
            $product = $listing->product;

            if (! $credential?->is_active || ! $product || $product->status !== 'active') {
                continue;
            }

            $listing->update(['status' => ShopifyListing::STATUS_PENDING]);

            SyncProductToShopify::dispatch($product, $credential)
                ->delay(now()->addSeconds($index * 3));

            $retried++;
        }

        Log::info('RetryFailedShopifySync: Retry jobs dispatched', [
            'retried' => $retried,
        ]);
    }

    public function tags(): array
    {
        return ['shopify-retry'];
    }
}
