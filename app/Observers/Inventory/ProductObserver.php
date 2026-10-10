<?php

namespace App\Observers\Inventory;

use App\Jobs\Inventory\EndMarketplaceListings;
use App\Jobs\Inventory\PushListingToMarketplaces;
use App\Jobs\Inventory\PushPriceToMarketplaces;
use App\Jobs\Inventory\UpdateProductSearchIndex;
use App\Models\Catalog\PriceHistory;
use App\Models\Catalog\Product;
use Illuminate\Support\Str;

class ProductObserver
{
    public function creating(Product $product): void
    {
        // Generate slug if not provided
        if (empty($product->slug)) {
            $product->slug = $this->generateUniqueSlug($product->name, $product->organization_id);
        }

        // Generate SKU if not provided
        if (empty($product->sku)) {
            $product->sku = $this->generateUniqueSku($product->organization_id);
        }
    }

    public function created(Product $product): void
    {
        // Dispatch search index update
        UpdateProductSearchIndex::dispatch($product);
    }

    public function updated(Product $product): void
    {
        // Update search index if searchable fields changed
        if ($product->wasChanged(['name', 'description', 'search_keywords', 'status'])) {
            UpdateProductSearchIndex::dispatch($product);
        }

        // Track and sync price changes
        $priceFields = ['selling_price', 'cost_price', 'msrp'];
        foreach ($priceFields as $field) {
            if ($product->wasChanged($field)) {
                PriceHistory::create([
                    'product_id' => $product->id,
                    'organization_id' => $product->organization_id,
                    'price_field' => $field,
                    'old_price' => $product->getOriginal($field),
                    'new_price' => $product->{$field},
                    'source' => 'manual',
                    'changed_by' => auth()->id(),
                ]);
            }
        }

        // End marketplace listings when product is deactivated
        $inactiveStatuses = ['inactive', 'archived', 'deleted'];
        if ($product->wasChanged('status') && in_array($product->status, $inactiveStatuses)) {
            EndMarketplaceListings::dispatch($product->id);

            return;
        }

        // Push listing detail changes to marketplaces
        $listingFields = ['name', 'description', 'gallery_images', 'condition', 'condition_description', 'weight', 'length', 'width', 'height'];
        $listingChanged = $product->wasChanged($listingFields);

        if ($listingChanged) {
            PushListingToMarketplaces::dispatch($product->id);
        }

        // Only push price separately if listing details didn't also change
        // (updateListing already includes price in the full sync)
        if ($product->wasChanged('selling_price') && ! $listingChanged) {
            PushPriceToMarketplaces::dispatch($product->id);
        }
    }

    public function deleted(Product $product): void
    {
        // Remove from search index (fires on both soft and hard delete)
        UpdateProductSearchIndex::dispatch($product, 'delete');
    }

    public function restored(Product $product): void
    {
        // Re-index restored product in search
        UpdateProductSearchIndex::dispatch($product);
    }

    public function forceDeleted(Product $product): void
    {
        // Ensure search index is cleaned up on permanent deletion
        UpdateProductSearchIndex::dispatch($product, 'delete');
    }

    private function generateUniqueSlug(string $name, int $organizationId): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Product::where('organization_id', $organizationId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function generateUniqueSku(int $organizationId): string
    {
        do {
            $sku = 'PRD-'.strtoupper(Str::random(8));
        } while (Product::where('organization_id', $organizationId)->where('sku', $sku)->exists());

        return $sku;
    }
}
