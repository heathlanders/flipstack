<?php

namespace App\Services\Shopify;

use App\Contracts\MarketplaceAdapter;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ShopifyMarketplaceAdapter implements MarketplaceAdapter
{
    public function __construct(
        protected IntegrationCredential $credential
    ) {}

    public function updatePrice(Product $product, Model $listing): bool
    {
        try {
            $service = new ShopifyProductService($this->credential);
            $service->updateProduct($product, $listing, true);

            return true;
        } catch (Exception $e) {
            Log::error('ShopifyMarketplaceAdapter: updatePrice failed', [
                'listing_id' => $listing->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function updateQuantity(Product $product, Model $listing): bool
    {
        try {
            if (! $listing->shopify_inventory_item_id) {
                return false;
            }

            $inventoryService = new ShopifyInventoryService($this->credential);
            $primaryLocationId = $this->resolveLocationId($inventoryService);

            if (! $primaryLocationId) {
                Log::warning('ShopifyMarketplaceAdapter: no location found', [
                    'credential_id' => $this->credential->id,
                ]);

                return false;
            }

            $desiredQuantity = (int) ($product->available_quantity ?? 0);
            $inventoryItemId = (int) $listing->shopify_inventory_item_id;

            // Set desired quantity at primary location
            $inventoryService->setInventoryLevel($primaryLocationId, $inventoryItemId, $desiredQuantity);

            // Zero out all other locations to prevent double-counting
            try {
                $locations = $inventoryService->getLocations();
                foreach ($locations as $location) {
                    $locId = (int) $location['id'];
                    if ($locId !== $primaryLocationId) {
                        $inventoryService->setInventoryLevel($locId, $inventoryItemId, 0);
                    }
                }
            } catch (Exception $e) {
                Log::warning('ShopifyMarketplaceAdapter: failed to zero other locations', [
                    'error' => $e->getMessage(),
                ]);
            }

            return true;
        } catch (Exception $e) {
            Log::error('ShopifyMarketplaceAdapter: updateQuantity failed', [
                'listing_id' => $listing->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function updateListing(Product $product, Model $listing): bool
    {
        try {
            $service = new ShopifyProductService($this->credential);
            $service->updateProduct($product, $listing, true);

            return true;
        } catch (Exception $e) {
            Log::error('ShopifyMarketplaceAdapter: updateListing failed', [
                'listing_id' => $listing->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function endListing(Model $listing): bool
    {
        try {
            $service = new ShopifyProductService($this->credential);
            $service->archiveProduct($listing);

            return true;
        } catch (Exception $e) {
            Log::error('ShopifyMarketplaceAdapter: endListing failed', [
                'listing_id' => $listing->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function isAvailable(): bool
    {
        return $this->credential->isShopifyReady();
    }

    public function getActiveListings(Product $product)
    {
        return $product->shopifyListings()
            ->where('integration_credential_id', $this->credential->id)
            ->where('status', 'active')
            ->get();
    }

    public function getPlatform(): string
    {
        return 'shopify';
    }

    public function getCredential(): IntegrationCredential
    {
        return $this->credential;
    }

    protected function resolveLocationId(ShopifyInventoryService $inventoryService): ?int
    {
        $config = $this->credential->config ?? [];

        if (! empty($config['shopify_location_id'])) {
            return (int) $config['shopify_location_id'];
        }

        try {
            $locations = $inventoryService->getLocations();
            $firstLocation = $locations[0] ?? null;

            if (! $firstLocation) {
                return null;
            }

            $locationId = (int) $firstLocation['id'];

            $this->credential->update([
                'config' => array_merge($config, ['shopify_location_id' => $locationId]),
            ]);

            return $locationId;
        } catch (Exception $e) {
            Log::warning('ShopifyMarketplaceAdapter: could not fetch locations', [
                'credential_id' => $this->credential->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
