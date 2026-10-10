<?php

namespace App\Jobs\Ebay;

use App\Models\Ebay\EbayListing;
use App\Services\EbayApiException;
use App\Services\EbayCategoryService;
use App\Services\EbayInventoryService;
use App\Services\EbayStoreImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UpdateEbayListing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public EbayListing $listing
    ) {
        $this->onQueue('ebay-sync');
    }

    public function handle(): void
    {
        Log::info('UpdateEbayListing: Starting listing detail sync', [
            'listing_id' => $this->listing->id,
            'ebay_sku' => $this->listing->ebay_sku,
        ]);

        if (! $this->listing->isPublished() && $this->listing->status !== EbayListing::STATUS_OUT_OF_STOCK) {
            Log::info('UpdateEbayListing: Listing not published, skipping', [
                'listing_id' => $this->listing->id,
                'status' => $this->listing->status,
            ]);

            return;
        }

        $credential = $this->listing->integrationCredential;
        $product = $this->listing->product;

        if (! $credential || ! $product) {
            Log::warning('UpdateEbayListing: Missing credential or product', [
                'listing_id' => $this->listing->id,
            ]);

            return;
        }

        try {
            if ($this->shouldUseTradingApi()) {
                $this->updateViaTradingApi();
            } else {
                $this->updateViaInventoryApi();
            }

            Log::info('UpdateEbayListing: Update completed', [
                'listing_id' => $this->listing->id,
            ]);

        } catch (EbayApiException $e) {
            $this->handleError($e);

            if ($e->isRetryable() && $this->attempts() < $this->tries) {
                throw $e;
            }

        } catch (Throwable $e) {
            $this->handleError($e);

            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }

    protected function updateViaInventoryApi(): void
    {
        $product = $this->listing->product;
        $credential = $this->listing->integrationCredential;

        $attributes = $product->attributes ?? [];

        $productData = [
            'title' => $product->name,
            'description' => $product->description ?? $product->name,
            'condition' => $product->condition,
            'condition_description' => $product->condition_description,
            'quantity' => $product->available_quantity ?? $product->stock_quantity ?? 1,
            'images' => $product->gallery_images ?: [],
            'brand' => $attributes['brand'] ?? null,
            'mpn' => $attributes['mpn'] ?? null,
            'model' => $attributes['model'] ?? null,
            'upc' => $attributes['upc'] ?? null,
            'ean' => $attributes['ean'] ?? null,
            'weight' => $product->weight,
            'length' => $product->length,
            'width' => $product->width,
            'height' => $product->height,
            'attributes' => $attributes,
            // Parity with create: send structured specifics + the eBay category's
            // required aspects so a full inventory replace doesn't strip specifics.
            'specifications' => $attributes['specifications'] ?? [],
            'required_aspects' => $this->fetchRequiredAspects(),
        ];

        $service = new EbayInventoryService($credential);
        $service->createOrUpdateInventoryItem($this->listing, $productData);
    }

    /**
     * Fetch the eBay category's required item specifics so the update payload can
     * fill mandatory aspects (cached 24h in EbayCategoryService). Best-effort:
     * returns [] on any failure or when no category is known.
     */
    protected function fetchRequiredAspects(): array
    {
        $categoryId = $this->listing->ebay_category_id;
        if (empty($categoryId)) {
            return [];
        }

        try {
            return app(EbayCategoryService::class)->getRequiredItemSpecifics((string) $categoryId);
        } catch (\Throwable $e) {
            Log::warning('UpdateEbayListing: could not fetch required aspects', [
                'listing_id' => $this->listing->id,
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function updateViaTradingApi(): void
    {
        $product = $this->listing->product;
        $credential = $this->listing->integrationCredential;

        $updates = [
            'title' => $product->name,
            'description' => $product->description ?? $product->name,
        ];

        if (! empty($product->gallery_images)) {
            $updates['picture_urls'] = collect($product->gallery_images)
                ->map(fn ($path) => Storage::disk('public')->url($path))
                ->filter()
                ->values()
                ->toArray();
        }

        $service = new EbayStoreImportService($credential);
        $service->reviseFixedPriceItem($this->listing, $updates);

        $this->listing->update([
            'product_data_hash' => $this->listing->generateProductHash(),
        ]);
    }

    protected function shouldUseTradingApi(): bool
    {
        return empty($this->listing->ebay_offer_id) && ! empty($this->listing->ebay_listing_id);
    }

    protected function handleError(Throwable $e): void
    {
        Log::error('UpdateEbayListing: Error occurred', [
            'listing_id' => $this->listing->id,
            'error' => $e->getMessage(),
            'attempt' => $this->attempts(),
        ]);

        $this->listing->recordError(
            $e->getMessage(),
            $e instanceof EbayApiException ? $e->getEbayErrorCode() : 'SYSTEM_ERROR'
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('UpdateEbayListing: Job failed permanently', [
            'listing_id' => $this->listing->id,
            'error' => $exception?->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'ebay-listing-sync',
            'listing:'.$this->listing->id,
            'product:'.$this->listing->product_id,
        ];
    }
}
