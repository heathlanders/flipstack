<?php

namespace App\Services\Amazon;

use App\Contracts\MarketplaceAdapter;
use App\Jobs\Amazon\CreateAmazonListingJob;
use App\Models\Amazon\AmazonListing;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AmazonMarketplaceAdapter implements MarketplaceAdapter
{
    public function __construct(
        protected IntegrationCredential $credential
    ) {}

    public function updatePrice(Product $product, Model $listing): bool
    {
        try {
            if ($listing->isFba()) {
                // FBA listings: only update price, not through full listing update
                $service = new AmazonPricingService($this->credential);
                $service->updatePrice(
                    $listing->amazon_sku,
                    (float) $product->selling_price,
                    $listing->minimum_price ? (float) $listing->minimum_price : null,
                    $listing->maximum_price ? (float) $listing->maximum_price : null
                );
            } else {
                $service = new AmazonListingsService($this->credential);
                $service->updateListing($product, $listing, true);
            }

            return true;
        } catch (Exception $e) {
            Log::error('AmazonMarketplaceAdapter: updatePrice failed', [
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
            // Skip FBA listings - Amazon manages FBA inventory
            if ($listing->isFba()) {
                return true;
            }

            $service = new AmazonInventoryService($this->credential);
            $service->updateFbmQuantity(
                $listing->amazon_sku,
                (int) ($product->available_quantity ?? 0)
            );

            return true;
        } catch (Exception $e) {
            Log::error('AmazonMarketplaceAdapter: updateQuantity failed', [
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
            $service = new AmazonListingsService($this->credential);
            $service->updateListing($product, $listing, true);

            return true;
        } catch (Exception $e) {
            Log::error('AmazonMarketplaceAdapter: updateListing failed', [
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
            $service = new AmazonListingsService($this->credential);
            $service->deleteListing($listing);

            return true;
        } catch (Exception $e) {
            Log::error('AmazonMarketplaceAdapter: endListing failed', [
                'listing_id' => $listing->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function isAvailable(): bool
    {
        return $this->credential->isAmazonReady();
    }

    public function getActiveListings(Product $product)
    {
        return $product->amazonListings()
            ->where('integration_credential_id', $this->credential->id)
            ->whereIn('status', [
                AmazonListing::STATUS_ACTIVE,
                AmazonListing::STATUS_CREATED, // accepted by SP-API, awaiting ASIN propagation
            ])
            ->get();
    }

    public function getPlatform(): string
    {
        return 'amazon';
    }

    public function getCredential(): IntegrationCredential
    {
        return $this->credential;
    }

    /**
     * Dispatch async listing creation. Async because Amazon ASIN propagation
     * is up to 24h and the SP-API listing PUT is itself eventually consistent.
     */
    public function createListing(Product $product, ?string $manualAsin = null, string $fulfillmentChannel = AmazonListing::FULFILLMENT_FBM): bool
    {
        try {
            CreateAmazonListingJob::dispatch(
                (string) $product->id,
                $this->credential->id,
                $manualAsin,
                $fulfillmentChannel,
            );

            return true;
        } catch (Exception $e) {
            Log::error('AmazonMarketplaceAdapter: createListing dispatch failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
