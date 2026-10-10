<?php

namespace App\Services;

use App\Models\Catalog\Product;
use Exception;
use Illuminate\Support\Facades\Log;

class MarketplaceListingSync
{
    public function __construct(
        protected MarketplaceRegistry $registry = new MarketplaceRegistry,
        protected ?MarketplaceSyncRuleEngine $ruleEngine = null
    ) {
        $this->ruleEngine ??= new MarketplaceSyncRuleEngine;
    }

    /**
     * Push product detail changes (title, description, images, etc.) to all marketplace listings.
     */
    public function pushListingToAll(Product $product): void
    {
        $adapters = $this->registry->getAdaptersForOrganization($product->organization_id);

        foreach ($adapters as $adapter) {
            $credential = $adapter->getCredential();

            if (! $credential->auto_sync_enabled) {
                Log::info('MarketplaceListingSync: skipped, auto_sync_enabled is off', [
                    'platform' => $adapter->getPlatform(),
                    'credential_id' => $credential->id,
                    'product_id' => $product->id,
                ]);

                continue;
            }

            if (! $this->ruleEngine->shouldSync($product, $adapter->getPlatform())) {
                Log::info('MarketplaceListingSync: skipped by sync rule', [
                    'platform' => $adapter->getPlatform(),
                    'product_id' => $product->id,
                ]);

                continue;
            }

            try {
                $listings = $adapter->getActiveListings($product);

                foreach ($listings as $listing) {
                    try {
                        if (! $listing->hasProductChanged()) {
                            Log::info('MarketplaceListingSync: listing hash unchanged, skipping', [
                                'platform' => $adapter->getPlatform(),
                                'listing_id' => $listing->id,
                                'product_id' => $product->id,
                            ]);

                            continue;
                        }

                        $adapter->updateListing($product, $listing);
                    } catch (Exception $e) {
                        Log::error('MarketplaceListingSync: listing update failed', [
                            'platform' => $adapter->getPlatform(),
                            'listing_id' => $listing->id,
                            'product_id' => $product->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (Exception $e) {
                Log::error('MarketplaceListingSync: adapter failed', [
                    'platform' => $adapter->getPlatform(),
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
