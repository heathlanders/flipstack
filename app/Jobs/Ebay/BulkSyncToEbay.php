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
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class BulkSyncToEbay implements ShouldQueue
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
     * The product IDs to sync.
     */
    protected array $productIds;

    /**
     * The team ID for the sync operation.
     */
    protected int $teamId;

    /**
     * Force update even if no changes detected.
     */
    protected bool $forceUpdate;

    /**
     * Create a new job instance.
     */
    public function __construct(array $productIds, int $teamId, bool $forceUpdate = false)
    {
        $this->productIds = $productIds;
        $this->teamId = $teamId;
        $this->forceUpdate = $forceUpdate;
    }

    /**
     * Queue a batch of per-product sync jobs for the given product IDs.
     * Returns the batch id.
     */
    public static function syncProducts(IntegrationCredential $credential, array $productIds, ?string $categoryId = null, ?int $userId = null): string
    {
        $jobs = Product::whereIn('id', $productIds)->get()
            ->map(fn (Product $p) => new SyncProductToEbay($p, $credential, $categoryId, false, $userId))
            ->all();

        if (empty($jobs)) {
            return '';
        }

        return Bus::batch($jobs)
            ->name('ebay-bulk-sync')
            ->onQueue('ebay-sync')
            ->dispatch()
            ->id;
    }

    /**
     * Queue a batch syncing every product that is ready for eBay but not yet
     * listed under this credential. Returns the batch id.
     */
    public static function syncAllPending(IntegrationCredential $credential): string
    {
        $query = Product::readyForEbay()->notOnEbay($credential->id);

        if ($credential->organization_id) {
            $query->where('organization_id', $credential->organization_id);
        }

        $jobs = $query->limit(500)->get()
            ->map(fn (Product $p) => new SyncProductToEbay($p, $credential))
            ->all();

        if (empty($jobs)) {
            return '';
        }

        return Bus::batch($jobs)
            ->name('ebay-bulk-sync-pending')
            ->onQueue('ebay-sync')
            ->dispatch()
            ->id;
    }

    /**
     * Execute the job.
     */
    public function handle(EbayListingService $listingService): void
    {
        // Check if batch was cancelled
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $products = Product::whereIn('id', $this->productIds)
            ->where('team_id', $this->teamId)
            ->where('ebay_enabled', true)
            ->get();

        foreach ($products as $product) {
            try {
                if ($product->ebayListing) {
                    // Update existing listing
                    $listingService->updateListing($product, $this->forceUpdate);
                } else {
                    // Create new listing
                    $listingService->createListing($product);
                }
            } catch (\Exception $e) {
                Log::error('eBay bulk sync failed for product', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                    'batch_id' => $this->batch()?->id,
                ]);

                if ($product->organization_id) {
                    app(\App\Services\NotificationService::class)->dispatchEvent(
                        eventKey: 'ebay.listing.sync_failed',
                        organizationId: (int) $product->organization_id,
                        args: [
                            (string) $product->id,
                            (string) ($product->name ?? 'Unknown product'),
                            (string) $e->getMessage(),
                            $this->batch()?->id,
                        ],
                        relatedId: (string) $product->id,
                    );
                }
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('eBay bulk sync job failed', [
            'product_ids' => $this->productIds,
            'team_id' => $this->teamId,
            'error' => $exception?->getMessage(),
            'batch_id' => $this->batch()?->id,
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'ebay',
            'bulk-sync',
            'team:'.$this->teamId,
        ];
    }
}
