<?php

namespace App\Services\Ebay;

use App\Jobs\Ebay\UpdateEbayListing;
use App\Models\Catalog\Product;
use App\Models\Ebay\EbayListing;
use App\Models\Integration\IntegrationCredential;
use App\Services\EbayInventoryService;
use RuntimeException;

/**
 * Orchestrates create-or-update of an eBay listing for a Product.
 *
 * - Existing live listing -> the rich UpdateEbayListing path (images + item
 *   specifics; never strips them on eBay's full-replace PUT).
 * - No listing yet -> EbayInventoryService::syncProduct (create inventory item,
 *   offer, publish) using the enriched Product::toEbayInventoryData() payload.
 *
 * This replaces the long-missing class the eBay sync/retry/bulk jobs depended on.
 */
class EbayListingService
{
    /**
     * Create or update the product's eBay listing for the given (or resolved)
     * credential.
     */
    public function syncProduct(Product $product, ?IntegrationCredential $credential = null, ?string $categoryId = null): void
    {
        $credential = $credential ?: $this->resolveCredential($product);

        if (! $credential) {
            throw new RuntimeException("No active eBay credential for organization {$product->organization_id}.");
        }

        $listing = $product->ebayListingFor($credential->id)
            ?: $product->ebayListings()->whereNotIn('status', [EbayListing::STATUS_DELETED])->latest()->first();

        if ($listing && ! empty($listing->ebay_listing_id)) {
            // Existing live listing: rich update (images + specifics).
            $this->repushExisting($listing);

            return;
        }

        // No live listing yet: full create + publish from the product.
        (new EbayInventoryService($credential))->syncProduct($product, $categoryId);
    }

    /**
     * Update an existing listing (used by the legacy job entry point).
     */
    public function updateListing(Product $product, bool $forceUpdate = false, array $updateFields = []): void
    {
        $listing = $product->ebayListing;

        if ($listing && ! empty($listing->ebay_listing_id)) {
            $this->repushExisting($listing);

            return;
        }

        $this->syncProduct($product);
    }

    /**
     * Create a new listing from the product.
     */
    public function createListing(Product $product): void
    {
        $this->syncProduct($product);
    }

    /**
     * Re-push an existing listing via UpdateEbayListing, unsticking a listing
     * left non-published by a previously failed sync (UpdateEbayListing skips
     * anything that is not published).
     */
    protected function repushExisting(EbayListing $listing): void
    {
        if (! $listing->isPublished() && $listing->status !== EbayListing::STATUS_OUT_OF_STOCK) {
            $listing->update([
                'status' => EbayListing::STATUS_PUBLISHED,
                'consecutive_errors' => 0,
            ]);
        }

        UpdateEbayListing::dispatch($listing);
    }

    protected function resolveCredential(Product $product): ?IntegrationCredential
    {
        return IntegrationCredential::where('organization_id', $product->organization_id)
            ->where('platform', 'ebay')
            ->where('is_active', true)
            ->first();
    }
}
