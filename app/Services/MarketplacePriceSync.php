<?php

namespace App\Services;

use App\Models\Catalog\Product;
use Exception;
use Illuminate\Support\Facades\Log;

class MarketplacePriceSync
{
    public function __construct(
        protected MarketplaceRegistry $registry = new MarketplaceRegistry,
        protected ?MarketplaceSyncRuleEngine $ruleEngine = null
    ) {
        $this->ruleEngine ??= new MarketplaceSyncRuleEngine;
    }

    /**
     * Push the current selling_price for a product to all marketplace listings.
     */
    public function pushPriceToAll(Product $product): void
    {
        $adapters = $this->registry->getAdaptersForOrganization($product->organization_id);

        foreach ($adapters as $adapter) {
            $credential = $adapter->getCredential();

            if (! $credential->auto_sync_enabled) {
                Log::info('MarketplacePriceSync: skipped, auto_sync_enabled is off', [
                    'platform' => $adapter->getPlatform(),
                    'credential_id' => $credential->id,
                    'product_id' => $product->id,
                ]);

                continue;
            }

            if (! $this->ruleEngine->shouldSync($product, $adapter->getPlatform())) {
                Log::info('MarketplacePriceSync: skipped by sync rule', [
                    'platform' => $adapter->getPlatform(),
                    'product_id' => $product->id,
                ]);

                continue;
            }

            try {
                $listings = $adapter->getActiveListings($product);

                foreach ($listings as $listing) {
                    try {
                        $adapter->updatePrice($product, $listing);
                    } catch (Exception $e) {
                        Log::error('MarketplacePriceSync: price update failed', [
                            'platform' => $adapter->getPlatform(),
                            'listing_id' => $listing->id,
                            'product_id' => $product->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (Exception $e) {
                Log::error('MarketplacePriceSync: adapter failed', [
                    'platform' => $adapter->getPlatform(),
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
