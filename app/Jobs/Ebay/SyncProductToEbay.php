<?php

namespace App\Jobs\Ebay;

use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Services\Ebay\EbayListingService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductToEbay implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     *
     * Signature matches all dispatch sites (controllers, command, mobile API):
     * (product, credential?, categoryId?, forceUpdate?, userId?).
     */
    public function __construct(
        public Product $product,
        public ?IntegrationCredential $credential = null,
        public ?string $categoryId = null,
        public bool $forceUpdate = false,
        public ?int $userId = null,
    ) {
        $this->onQueue('ebay-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(EbayListingService $listingService): void
    {
        // Check if batch was cancelled (when part of a batch)
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        try {
            $listingService->syncProduct($this->product, $this->credential, $this->categoryId);
        } catch (\Exception $e) {
            Log::error('eBay sync failed for product', [
                'product_id' => $this->product->id,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('eBay sync job failed after all retries', [
            'product_id' => $this->product->id,
            'error' => $exception?->getMessage(),
        ]);

        if ($this->product->organization_id) {
            app(\App\Services\NotificationService::class)->dispatchEvent(
                eventKey: 'ebay.listing.sync_failed',
                organizationId: (int) $this->product->organization_id,
                args: [
                    (string) $this->product->id,
                    (string) ($this->product->name ?? 'Unknown product'),
                    (string) ($exception?->getMessage() ?? 'Unknown error'),
                    $this->batch()?->id,
                ],
                relatedId: (string) $this->product->id,
            );
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'ebay',
            'sync',
            'product:'.$this->product->id,
            'team:'.$this->product->team_id,
        ];
    }
}
