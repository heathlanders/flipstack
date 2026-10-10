<?php

namespace App\Services\Marketplace;

use App\Jobs\Amazon\CreateAmazonListingJob;
use App\Jobs\CreateEbayListingJob;
use App\Jobs\Shopify\SyncProductToShopify;
use App\Models\Amazon\AmazonListing;
use App\Models\Catalog\Product;
use App\Models\Ebay\EbayListing;
use App\Models\Integration\IntegrationCredential;
use App\Models\Shopify\ShopifyListing;
use Illuminate\Support\Facades\Log;

class CrossListingService
{
    public function __construct(
        protected ListingDataTransformer $transformer
    ) {}

    /**
     * Load product and existing listing data from the source marketplace.
     */
    public function getSourceListingData(string $productId, string $sourceMarketplace): array
    {
        $product = Product::with(['images', 'category', 'brand'])->findOrFail($productId);

        $productData = [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'upc' => $product->upc,
            'ean' => $product->ean,
            'mpn' => $product->mpn,
            'selling_price' => $product->selling_price,
            'cost_price' => $product->cost_price,
            'msrp' => $product->msrp,
            'currency' => $product->currency ?? 'USD',
            'weight' => $product->weight,
            'length' => $product->length,
            'width' => $product->width,
            'height' => $product->height,
            'condition' => $product->condition,
            'condition_description' => $product->condition_description,
            'stock_quantity' => $product->stock_quantity,
            'available_quantity' => $product->available_quantity,
            'brand' => $product->brand?->name,
            'category' => $product->category?->name,
            'featured_image' => $product->featured_image,
            'images' => $product->gallery_images ?? [],
        ];

        // Merge in marketplace-specific listing data
        $listingData = match ($sourceMarketplace) {
            'ebay' => $this->getEbayListingData($product),
            'shopify' => $this->getShopifyListingData($product),
            'amazon' => $this->getAmazonListingData($product),
            default => [],
        };

        return array_merge($productData, $listingData);
    }

    /**
     * Transform source listing data for the target marketplace format.
     */
    public function transformForTarget(array $sourceData, string $targetMarketplace): array
    {
        $sourceMarketplace = $sourceData['_source_marketplace'] ?? 'product';

        return $this->transformer->transform($sourceData, $sourceMarketplace, $targetMarketplace);
    }

    /**
     * Return source and transformed target data for preview.
     */
    public function preview(string $productId, string $sourceMarketplace, string $targetMarketplace): array
    {
        $sourceData = $this->getSourceListingData($productId, $sourceMarketplace);
        $sourceData['_source_marketplace'] = $sourceMarketplace;

        $targetData = $this->transformForTarget($sourceData, $targetMarketplace);

        $product = Product::with(['images'])->findOrFail($productId);

        return [
            'sourceData' => $sourceData,
            'targetData' => $targetData,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'featured_image' => $product->featured_image,
                'selling_price' => $product->selling_price,
                'cost_price' => $product->cost_price,
            ],
        ];
    }

    /**
     * Execute listing creation on the target marketplace.
     */
    public function execute(array $listingData, string $targetMarketplace, int $credentialId): void
    {
        $credential = IntegrationCredential::findOrFail($credentialId);

        Log::info('CrossListingService: executing cross-list', [
            'target_marketplace' => $targetMarketplace,
            'credential_id' => $credentialId,
            'title' => $listingData['title'] ?? $listingData['name'] ?? 'unknown',
        ]);

        match ($targetMarketplace) {
            'ebay' => $this->executeEbayListing($listingData, $credential),
            'shopify' => $this->executeShopifyListing($listingData, $credential),
            'amazon' => $this->executeAmazonListing($listingData, $credential),
            default => Log::warning("CrossListingService: unsupported target marketplace [{$targetMarketplace}]"),
        };
    }

    /**
     * Map eBay listing fields to Shopify format (convenience wrapper).
     */
    public function mapEbayToShopify(array $ebayData): array
    {
        return $this->transformer->ebayToShopify($ebayData);
    }

    /**
     * Map Shopify listing fields to eBay format (convenience wrapper).
     */
    public function mapShopifyToEbay(array $shopifyData): array
    {
        return $this->transformer->shopifyToEbay($shopifyData);
    }

    /**
     * Normalize data to a generic format from any marketplace.
     */
    public function mapToGeneric(array $data, string $marketplace): array
    {
        return match ($marketplace) {
            'ebay' => [
                'title' => $data['title'] ?? $data['name'] ?? '',
                'description' => $data['description'] ?? '',
                'price' => $data['listed_price'] ?? $data['selling_price'] ?? '0.00',
                'sku' => $data['sku'] ?? $data['ebay_sku'] ?? '',
                'condition' => $data['condition'] ?? 'NEW',
                'quantity' => $data['available_quantity'] ?? $data['listed_quantity'] ?? 1,
                'images' => $this->transformer->normalizeImages($data['images'] ?? []),
                'marketplace' => 'ebay',
            ],
            'shopify' => [
                'title' => $data['title'] ?? $data['name'] ?? '',
                'description' => $data['body_html'] ?? $data['description'] ?? '',
                'price' => $data['price'] ?? $data['listed_price'] ?? $data['selling_price'] ?? '0.00',
                'sku' => $data['sku'] ?? $data['shopify_sku'] ?? '',
                'condition' => $data['condition'] ?? 'NEW',
                'quantity' => $data['inventory_quantity'] ?? $data['available_quantity'] ?? 1,
                'images' => $this->transformer->normalizeImages($data['images'] ?? []),
                'marketplace' => 'shopify',
            ],
            'amazon' => [
                'title' => $data['title'] ?? $data['name'] ?? '',
                'description' => $data['description'] ?? '',
                'price' => $data['listed_price'] ?? $data['selling_price'] ?? '0.00',
                'sku' => $data['amazon_sku'] ?? $data['sku'] ?? '',
                'asin' => $data['asin'] ?? null,
                'condition' => $data['condition'] ?? 'NEW',
                'quantity' => $data['inventory_quantity'] ?? $data['available_quantity'] ?? 1,
                'images' => $this->transformer->normalizeImages($data['images'] ?? []),
                'marketplace' => 'amazon',
            ],
            default => $data,
        };
    }

    /**
     * Get eBay-specific listing data for a product.
     */
    private function getEbayListingData(Product $product): array
    {
        $listing = $product->ebayListings()
            ->whereNotIn('status', ['deleted'])
            ->latest()
            ->first();

        if (! $listing) {
            return ['_source_marketplace' => 'ebay'];
        }

        return [
            'title' => $listing->product?->name ?? $product->name,
            'listed_price' => $listing->listed_price,
            'currency' => $listing->currency ?? 'USD',
            'condition' => $listing->condition ?? $product->condition,
            'condition_description' => $listing->condition_description ?? $product->condition_description,
            'ebay_category_id' => $listing->ebay_category_id,
            'ebay_category_name' => $listing->ebay_category_name,
            'listing_format' => $listing->listing_format,
            'ebay_listing_id' => $listing->ebay_listing_id,
            'ebay_listing_url' => $listing->ebay_listing_url,
            'status' => $listing->status,
            '_source_marketplace' => 'ebay',
        ];
    }

    /**
     * Get Shopify-specific listing data for a product.
     */
    private function getShopifyListingData(Product $product): array
    {
        $listing = $product->shopifyListings()
            ->whereNotIn('status', ['deleted'])
            ->latest()
            ->first();

        if (! $listing) {
            return ['_source_marketplace' => 'shopify'];
        }

        return [
            'title' => $product->name,
            'body_html' => $product->description,
            'price' => $listing->listed_price,
            'compare_at_price' => $listing->compare_at_price,
            'listed_price' => $listing->listed_price,
            'currency' => $listing->currency ?? 'USD',
            'shopify_product_id' => $listing->shopify_product_id,
            'shopify_handle' => $listing->shopify_handle,
            'inventory_quantity' => $listing->inventory_quantity,
            'status' => $listing->status,
            '_source_marketplace' => 'shopify',
        ];
    }

    /**
     * Create an eBay listing via the existing CreateEbayListingJob.
     */
    private function executeEbayListing(array $listingData, IntegrationCredential $credential): void
    {
        $productId = $listingData['product_id'] ?? null;

        if (! $productId) {
            Log::error('CrossListingService: missing product_id for eBay listing');

            return;
        }

        $product = Product::find($productId);

        if (! $product) {
            Log::error('CrossListingService: product not found', ['product_id' => $productId]);

            return;
        }

        // Prevent duplicate: product can only have one active eBay listing across all accounts
        $existingListing = EbayListing::where('product_id', $product->id)
            ->whereNotIn('status', [EbayListing::STATUS_DELETED, EbayListing::STATUS_ENDED])
            ->first();

        if ($existingListing) {
            Log::warning('CrossListingService: product already has an active eBay listing', [
                'product_id' => $product->id,
                'existing_listing_id' => $existingListing->id,
                'existing_credential_id' => $existingListing->integration_credential_id,
            ]);

            throw new \Exception('This product already has an active eBay listing. End or delete the existing listing before listing on a different account.');
        }

        // Create the pending eBay listing record
        $ebayListing = EbayListing::create([
            'product_id' => $product->id,
            'integration_credential_id' => $credential->id,
            'team_id' => $credential->team_id,
            'ebay_sku' => $listingData['sku'] ?? $product->sku,
            'status' => EbayListing::STATUS_PENDING,
            'listed_price' => $listingData['listed_price'] ?? $listingData['price'] ?? $product->selling_price,
            'currency' => $listingData['currency'] ?? 'USD',
            'condition' => $listingData['condition'] ?? $product->condition ?? 'NEW',
            'condition_description' => $listingData['condition_description'] ?? $product->condition_description,
            'listed_quantity' => $listingData['listed_quantity'] ?? $product->available_quantity ?? 1,
        ]);

        // Dispatch the job
        CreateEbayListingJob::dispatch($product, $credential);

        Log::info('CrossListingService: dispatched CreateEbayListingJob', [
            'product_id' => $product->id,
            'listing_id' => $ebayListing->id,
            'credential_id' => $credential->id,
        ]);
    }

    /**
     * Read existing Amazon listing data for a product.
     */
    private function getAmazonListingData(Product $product): array
    {
        $listing = $product->amazonListings()
            ->whereNotIn('status', [AmazonListing::STATUS_DELETED])
            ->latest()
            ->first();

        if (! $listing) {
            return ['_source_marketplace' => 'amazon'];
        }

        return [
            'title' => $product->name,
            'description' => $product->description,
            'asin' => $listing->asin,
            'amazon_sku' => $listing->amazon_sku,
            'amazon_product_type' => $listing->amazon_product_type,
            'fulfillment_channel' => $listing->fulfillment_channel,
            'listed_price' => $listing->listed_price,
            'currency' => $listing->currency ?? 'USD',
            'inventory_quantity' => $listing->inventory_quantity,
            'status' => $listing->status,
            '_source_marketplace' => 'amazon',
        ];
    }

    /**
     * Dispatch Amazon listing creation job.
     */
    private function executeAmazonListing(array $listingData, IntegrationCredential $credential): void
    {
        $productId = $listingData['product_id'] ?? null;

        if (! $productId) {
            Log::error('CrossListingService: missing product_id for Amazon listing');

            return;
        }

        $product = Product::find($productId);

        if (! $product) {
            Log::error('CrossListingService: product not found', ['product_id' => $productId]);

            return;
        }

        $existingListing = AmazonListing::where('product_id', $product->id)
            ->where('integration_credential_id', $credential->id)
            ->whereNotIn('status', [AmazonListing::STATUS_DELETED])
            ->first();

        if ($existingListing) {
            Log::warning('CrossListingService: product already has an Amazon listing on this credential', [
                'product_id' => $product->id,
                'existing_listing_id' => $existingListing->id,
            ]);

            throw new \Exception('This product already has an Amazon listing on this seller account. Remove the existing offer before re-listing.');
        }

        CreateAmazonListingJob::dispatch(
            (string) $product->id,
            $credential->id,
            $listingData['asin'] ?? null,
            $listingData['fulfillment_channel'] ?? AmazonListing::FULFILLMENT_FBM,
        );

        Log::info('CrossListingService: dispatched CreateAmazonListingJob', [
            'product_id' => $product->id,
            'credential_id' => $credential->id,
        ]);
    }

    /**
     * Create a Shopify listing (placeholder - dispatches log entry until full Shopify create job exists).
     */
    private function executeShopifyListing(array $listingData, IntegrationCredential $credential): void
    {
        $productId = $listingData['product_id'] ?? null;

        if (! $productId) {
            Log::error('CrossListingService: missing product_id for Shopify listing');

            return;
        }

        $product = Product::find($productId);

        if (! $product) {
            Log::error('CrossListingService: product not found', ['product_id' => $productId]);

            return;
        }

        // Prevent duplicate: product can only have one active Shopify listing across all accounts
        $existingListing = ShopifyListing::where('product_id', $product->id)
            ->whereNotIn('status', [ShopifyListing::STATUS_DELETED, ShopifyListing::STATUS_ARCHIVED])
            ->first();

        if ($existingListing) {
            Log::warning('CrossListingService: product already has an active Shopify listing', [
                'product_id' => $product->id,
                'existing_listing_id' => $existingListing->id,
                'existing_credential_id' => $existingListing->integration_credential_id,
            ]);

            throw new \Exception('This product already has an active Shopify listing. Archive or delete the existing listing before listing on a different store.');
        }

        // Create pending Shopify listing record
        $shopifyListing = ShopifyListing::create([
            'product_id' => $product->id,
            'integration_credential_id' => $credential->id,
            'team_id' => $credential->team_id,
            'shopify_sku' => $listingData['sku'] ?? $product->sku,
            'status' => ShopifyListing::STATUS_PENDING,
            'listed_price' => $listingData['price'] ?? $listingData['listed_price'] ?? $product->selling_price,
            'compare_at_price' => $listingData['compare_at_price'] ?? null,
            'currency' => $listingData['currency'] ?? 'USD',
            'inventory_quantity' => $listingData['inventory_quantity'] ?? $product->available_quantity ?? 0,
        ]);

        // Dispatch job to create listing on Shopify
        SyncProductToShopify::dispatch($product, $credential);

        Log::info('CrossListingService: dispatched SyncProductToShopify', [
            'product_id' => $product->id,
            'listing_id' => $shopifyListing->id,
            'credential_id' => $credential->id,
        ]);
    }
}
