<?php

namespace App\Jobs\Amazon;

use App\Models\Amazon\AmazonListing;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Services\Amazon\AmazonApiException;
use App\Services\Amazon\AmazonCatalogService;
use App\Services\Amazon\AmazonListingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateAmazonListingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $productId,
        public int $credentialId,
        public ?string $manualAsin = null,
        public string $fulfillmentChannel = AmazonListing::FULFILLMENT_FBM,
    ) {
        $this->onQueue('amazon-listings');
    }

    public function handle(): void
    {
        $product = Product::find($this->productId);
        $credential = IntegrationCredential::find($this->credentialId);

        if (! $product || ! $credential || ! $credential->is_active) {
            Log::warning('CreateAmazonListingJob: missing product or inactive credential', [
                'product_id' => $this->productId,
                'credential_id' => $this->credentialId,
            ]);

            return;
        }

        if (! $credential->isAmazonReady()) {
            Log::warning('CreateAmazonListingJob: Amazon credential not ready', [
                'credential_id' => $credential->id,
            ]);

            return;
        }

        $listing = AmazonListing::where('product_id', $product->id)
            ->where('integration_credential_id', $credential->id)
            ->whereNotIn('status', [AmazonListing::STATUS_DELETED])
            ->first();

        if ($listing && in_array($listing->status, [AmazonListing::STATUS_ACTIVE, AmazonListing::STATUS_CREATED, AmazonListing::STATUS_PENDING])) {
            Log::info('CreateAmazonListingJob: listing already exists, dispatching update instead', [
                'listing_id' => $listing->id,
                'status' => $listing->status,
            ]);

            $listingsService = new AmazonListingsService($credential);
            $listingsService->updateListing($product, $listing, force: true);

            return;
        }

        $listing ??= new AmazonListing([
            'product_id' => $product->id,
            'integration_credential_id' => $credential->id,
            'team_id' => $credential->team_id,
            'amazon_sku' => $product->generateEbaySku(),
            'fulfillment_channel' => $this->fulfillmentChannel,
            'status' => AmazonListing::STATUS_PENDING,
            'currency' => $product->currency ?? 'USD',
        ]);

        $match = $this->resolveAsin($product, $credential);

        if (! $match || empty($match['asin'])) {
            $listing->fill([
                'status' => AmazonListing::STATUS_INCOMPLETE,
                'last_error_message' => 'No ASIN match found. Provide a manual ASIN or supply UPC/EAN.',
                'last_error_at' => now(),
            ])->save();

            Log::info('CreateAmazonListingJob: no ASIN match', [
                'product_id' => $product->id,
                'credential_id' => $credential->id,
            ]);

            return;
        }

        $listing->fill([
            'asin' => $match['asin'],
            'amazon_product_type' => $match['product_type'] ?? 'PRODUCT',
            'asin_match_source' => $match['match_source'] ?? AmazonListing::MATCH_SOURCE_GTIN,
            'asin_match_confidence' => $match['match_confidence'] ?? null,
            'listed_price' => $product->selling_price,
            'inventory_quantity' => $listing->fulfillment_channel === AmazonListing::FULFILLMENT_FBM
                ? ($product->available_quantity ?? $product->stock_quantity)
                : null,
        ])->save();

        try {
            $listingsService = new AmazonListingsService($credential);
            $listingsService->createListing($product, $listing);
        } catch (AmazonApiException $e) {
            $listing->fill([
                'status' => AmazonListing::STATUS_ERROR,
                'last_error_message' => $e->getMessage(),
                'last_error_code' => (string) $e->getCode(),
                'last_error_at' => now(),
                'consecutive_errors' => ($listing->consecutive_errors ?? 0) + 1,
            ])->save();

            Log::error('CreateAmazonListingJob: SP-API error', [
                'listing_id' => $listing->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function resolveAsin(Product $product, IntegrationCredential $credential): ?array
    {
        if ($this->manualAsin) {
            return [
                'asin' => $this->manualAsin,
                'product_type' => 'PRODUCT',
                'match_source' => AmazonListing::MATCH_SOURCE_MANUAL,
                'match_confidence' => 100.0,
            ];
        }

        $catalogService = new AmazonCatalogService($credential);

        return $catalogService->matchProduct($product);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('CreateAmazonListingJob failed', [
            'product_id' => $this->productId,
            'credential_id' => $this->credentialId,
            'error' => $exception->getMessage(),
        ]);
    }
}
