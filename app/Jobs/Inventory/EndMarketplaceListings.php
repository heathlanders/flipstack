<?php

namespace App\Jobs\Inventory;

use App\Models\Catalog\Product;
use App\Services\MarketplaceRegistry;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EndMarketplaceListings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(protected string $productId)
    {
        $this->onQueue('default');
    }

    public function handle(MarketplaceRegistry $registry): void
    {
        // Use withTrashed() because this job may run after the product
        // has been soft-deleted (archived) by ProductDeletionService
        $product = Product::withTrashed()->find($this->productId);

        if (! $product) {
            return;
        }

        Log::info('EndMarketplaceListings: ending all marketplace listings for deactivated product', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'status' => $product->status,
        ]);

        $adapters = $registry->getAdaptersForOrganization($product->organization_id);

        foreach ($adapters as $adapter) {
            $credential = $adapter->getCredential();

            if (! $credential->auto_sync_enabled) {
                Log::info('EndMarketplaceListings: skipped, auto_sync_enabled is off', [
                    'platform' => $adapter->getPlatform(),
                    'credential_id' => $credential->id,
                    'product_id' => $product->id,
                ]);

                continue;
            }

            try {
                $listings = $adapter->getActiveListings($product);

                foreach ($listings as $listing) {
                    try {
                        $adapter->endListing($listing);
                    } catch (Exception $e) {
                        Log::error('EndMarketplaceListings: failed to end listing', [
                            'platform' => $adapter->getPlatform(),
                            'listing_id' => $listing->id,
                            'product_id' => $product->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (Exception $e) {
                Log::error('EndMarketplaceListings: adapter failed', [
                    'platform' => $adapter->getPlatform(),
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
