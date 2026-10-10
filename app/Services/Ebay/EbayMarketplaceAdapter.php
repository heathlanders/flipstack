<?php

namespace App\Services\Ebay;

use App\Contracts\MarketplaceAdapter;
use App\Jobs\Ebay\UpdateEbayInventory;
use App\Jobs\Ebay\UpdateEbayListing;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Services\EbayInventoryService;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class EbayMarketplaceAdapter implements MarketplaceAdapter
{
    public function __construct(
        protected IntegrationCredential $credential
    ) {}

    public function updatePrice(Product $product, Model $listing): bool
    {
        try {
            UpdateEbayInventory::dispatch($listing, 'price');

            return true;
        } catch (Exception $e) {
            Log::error('EbayMarketplaceAdapter: updatePrice failed', [
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
            UpdateEbayInventory::dispatch($listing, 'quantity');

            return true;
        } catch (Exception $e) {
            Log::error('EbayMarketplaceAdapter: updateQuantity failed', [
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
            UpdateEbayListing::dispatch($listing);

            return true;
        } catch (Exception $e) {
            Log::error('EbayMarketplaceAdapter: updateListing failed', [
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
            $service = new EbayInventoryService($this->credential);

            return $service->endListing($listing);
        } catch (Exception $e) {
            Log::error('EbayMarketplaceAdapter: endListing failed', [
                'listing_id' => $listing->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function isAvailable(): bool
    {
        return $this->credential->isEbayReady();
    }

    public function getActiveListings(Product $product)
    {
        return $product->ebayListings()
            ->where('integration_credential_id', $this->credential->id)
            ->where('status', 'published')
            ->get();
    }

    public function getPlatform(): string
    {
        return 'ebay';
    }

    public function getCredential(): IntegrationCredential
    {
        return $this->credential;
    }
}
