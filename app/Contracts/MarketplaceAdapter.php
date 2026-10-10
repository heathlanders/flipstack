<?php

namespace App\Contracts;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;

interface MarketplaceAdapter
{
    /**
     * Sync the current selling_price to a marketplace listing.
     */
    public function updatePrice(Product $product, Model $listing): bool;

    /**
     * Sync the current available_quantity to a marketplace listing.
     */
    public function updateQuantity(Product $product, Model $listing): bool;

    /**
     * Sync product detail changes (title, description, images, etc.) to a marketplace listing.
     */
    public function updateListing(Product $product, Model $listing): bool;

    /**
     * Remove/end a listing on the marketplace.
     */
    public function endListing(Model $listing): bool;

    /**
     * Check if this adapter is configured and ready.
     */
    public function isAvailable(): bool;

    /**
     * Get the underlying IntegrationCredential for this adapter.
     */
    public function getCredential(): \App\Models\Integration\IntegrationCredential;

    /**
     * Get all active/published listings for a product.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getActiveListings(Product $product);

    /**
     * Get the platform identifier (e.g., 'ebay', 'shopify').
     */
    public function getPlatform(): string;
}
