<?php

namespace App\Services;

use App\Models\Catalog\Product;
use Exception;
use Illuminate\Support\Facades\Log;

class MarketplaceInventorySync
{
    public function __construct(
        protected MarketplaceRegistry $registry = new MarketplaceRegistry,
        protected ?MarketplaceSyncRuleEngine $ruleEngine = null
    ) {
        $this->ruleEngine ??= new MarketplaceSyncRuleEngine;
    }

    /**
     * Push the current available_quantity for a product to all marketplace listings.
     *
     * Respects the auto_sync_enabled flag on each credential. Adapters whose
     * credential has auto_sync_enabled=false are skipped to prevent unintended
     * writes to marketplaces managed by external tools.
     */
    public function pushQuantityToAll(Product $product): void
    {
        $adapters = $this->registry->getAdaptersForOrganization($product->organization_id);

        foreach ($adapters as $adapter) {
            $credential = $adapter->getCredential();

            if (! $credential->auto_sync_enabled) {
                Log::info('MarketplaceInventorySync: skipped, auto_sync_enabled is off', [
                    'platform' => $adapter->getPlatform(),
                    'credential_id' => $credential->id,
                    'product_id' => $product->id,
                ]);

                continue;
            }

            if (! $this->ruleEngine->shouldSync($product, $adapter->getPlatform())) {
                Log::info('MarketplaceInventorySync: skipped by sync rule', [
                    'platform' => $adapter->getPlatform(),
                    'product_id' => $product->id,
                ]);

                continue;
            }

            try {
                $listings = $adapter->getActiveListings($product);

                foreach ($listings as $listing) {
                    try {
                        $adapter->updateQuantity($product, $listing);
                    } catch (Exception $e) {
                        Log::error('MarketplaceInventorySync: quantity update failed', [
                            'platform' => $adapter->getPlatform(),
                            'listing_id' => $listing->id,
                            'product_id' => $product->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (Exception $e) {
                Log::error('MarketplaceInventorySync: adapter failed', [
                    'platform' => $adapter->getPlatform(),
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
