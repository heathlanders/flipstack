<?php

namespace App\Jobs\Ebay;

use App\Models\Catalog\Product;
use App\Models\Ebay\EbayListing;
use App\Models\Integration\IntegrationCredential;
use App\Services\EbayApiException;
use App\Services\EbayInventoryService;
use App\Services\EbayStoreImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateEbayInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying
     */
    public int $backoff = 30;

    /**
     * Delete the job if its models no longer exist
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * What to update: 'quantity', 'price', or 'both'
     */
    public string $updateType;

    /**
     * Create a new job instance
     */
    public function __construct(
        public EbayListing $listing,
        string $updateType = 'both'
    ) {
        $this->updateType = $updateType;
        $this->onQueue('ebay-sync');
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        Log::info('UpdateEbayInventory: Starting update', [
            'listing_id' => $this->listing->id,
            'ebay_sku' => $this->listing->ebay_sku,
            'update_type' => $this->updateType,
        ]);

        // Skip if listing is not published
        if (! $this->listing->isPublished() && ! $this->listing->status === EbayListing::STATUS_OUT_OF_STOCK) {
            Log::info('UpdateEbayInventory: Listing not published, skipping', [
                'listing_id' => $this->listing->id,
                'status' => $this->listing->status,
            ]);

            return;
        }

        $credential = $this->listing->integrationCredential;
        $product = $this->listing->product;

        if (! $credential || ! $product) {
            Log::warning('UpdateEbayInventory: Missing credential or product', [
                'listing_id' => $this->listing->id,
            ]);

            return;
        }

        try {
            $service = new EbayInventoryService($credential);

            switch ($this->updateType) {
                case 'quantity':
                    $this->updateQuantity($service, $product);
                    break;

                case 'price':
                    $this->updatePrice($service, $product);
                    break;

                case 'both':
                default:
                    $this->updateBoth($service, $product);
                    break;
            }

            Log::info('UpdateEbayInventory: Update completed', [
                'listing_id' => $this->listing->id,
                'update_type' => $this->updateType,
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

    /**
     * Update quantity only
     */
    protected function updateQuantity(EbayInventoryService $service, Product $product): void
    {
        $newQuantity = $product->available_quantity ?? $product->stock_quantity;

        // Check if quantity actually changed
        if ($this->listing->available_quantity === $newQuantity) {
            Log::info('UpdateEbayInventory: Quantity unchanged, skipping', [
                'listing_id' => $this->listing->id,
                'quantity' => $newQuantity,
            ]);

            return;
        }

        if ($this->shouldUseTradingApi()) {
            $this->reviseViaTrading(['quantity' => $newQuantity]);
        } elseif ($this->listing->ebay_offer_id) {
            // Published listing: must update quantity via Offer API
            $service->updateOffer($this->listing, [
                'quantity' => $newQuantity,
                'price' => $this->listing->listed_price,
                'categoryId' => $this->listing->ebay_category_id,
                'currency' => $this->listing->currency ?? 'USD',
            ]);
        } else {
            $service->updateQuantity($this->listing, $newQuantity);
        }

        Log::info('UpdateEbayInventory: Quantity updated', [
            'listing_id' => $this->listing->id,
            'old_quantity' => $this->listing->available_quantity,
            'new_quantity' => $newQuantity,
        ]);
    }

    /**
     * Update price only
     */
    protected function updatePrice(EbayInventoryService $service, Product $product): void
    {
        $newPrice = $product->selling_price;

        // Check if price actually changed
        if ((float) $this->listing->listed_price === (float) $newPrice) {
            Log::info('UpdateEbayInventory: Price unchanged, skipping', [
                'listing_id' => $this->listing->id,
                'price' => $newPrice,
            ]);

            return;
        }

        if ($this->shouldUseTradingApi()) {
            $this->reviseViaTrading([
                'start_price' => $newPrice,
                'currency' => $product->currency ?? 'USD',
            ]);
        } else {
            $offerData = [
                'price' => $newPrice,
                'quantity' => $this->listing->available_quantity,
                'categoryId' => $this->listing->ebay_category_id,
                'currency' => $product->currency ?? 'USD',
            ];

            $service->updateOffer($this->listing, $offerData);
        }

        Log::info('UpdateEbayInventory: Price updated', [
            'listing_id' => $this->listing->id,
            'old_price' => $this->listing->listed_price,
            'new_price' => $newPrice,
        ]);
    }

    /**
     * Update both quantity and price
     */
    protected function updateBoth(EbayInventoryService $service, Product $product): void
    {
        $newQuantity = $product->available_quantity ?? $product->stock_quantity;
        $newPrice = $product->selling_price;

        $quantityChanged = $this->listing->available_quantity !== $newQuantity;
        $priceChanged = (float) $this->listing->listed_price !== (float) $newPrice;

        if (! $quantityChanged && ! $priceChanged) {
            Log::info('UpdateEbayInventory: No changes detected, skipping', [
                'listing_id' => $this->listing->id,
            ]);

            return;
        }

        if ($this->shouldUseTradingApi()) {
            $updates = [];
            if ($quantityChanged) {
                $updates['quantity'] = $newQuantity;
            }
            if ($priceChanged) {
                $updates['start_price'] = $newPrice;
                $updates['currency'] = $product->currency ?? 'USD';
            }
            $this->reviseViaTrading($updates);
        } else {
            // For published listings with an offer, use Offer API for both qty and price
            if ($this->listing->ebay_offer_id && ($quantityChanged || $priceChanged)) {
                $service->updateOffer($this->listing, [
                    'quantity' => $newQuantity,
                    'price' => $priceChanged ? $newPrice : $this->listing->listed_price,
                    'categoryId' => $this->listing->ebay_category_id,
                    'currency' => $product->currency ?? 'USD',
                ]);
            } else {
                if ($quantityChanged) {
                    $service->updateQuantity($this->listing, $newQuantity);
                }

                if ($priceChanged) {
                    $service->updateOffer($this->listing, [
                        'price' => $newPrice,
                        'quantity' => $newQuantity,
                        'categoryId' => $this->listing->ebay_category_id,
                        'currency' => $product->currency ?? 'USD',
                    ]);
                }
            }
        }

        Log::info('UpdateEbayInventory: Both updated', [
            'listing_id' => $this->listing->id,
            'quantity_changed' => $quantityChanged,
            'price_changed' => $priceChanged,
        ]);
    }

    /**
     * Check if this listing should use the Trading API (imported listing without offer ID)
     */
    protected function shouldUseTradingApi(): bool
    {
        return empty($this->listing->ebay_offer_id) && ! empty($this->listing->ebay_listing_id);
    }

    /**
     * Use the Trading API ReviseFixedPriceItem to update an imported listing
     */
    protected function reviseViaTrading(array $updates): void
    {
        $credential = $this->listing->integrationCredential;
        $service = new EbayStoreImportService($credential);
        $service->reviseFixedPriceItem($this->listing, $updates);
    }

    /**
     * Handle errors
     */
    protected function handleError(Throwable $e): void
    {
        Log::error('UpdateEbayInventory: Error occurred', [
            'listing_id' => $this->listing->id,
            'error' => $e->getMessage(),
            'attempt' => $this->attempts(),
        ]);

        $this->listing->recordError(
            $e->getMessage(),
            $e instanceof EbayApiException ? $e->getEbayErrorCode() : 'SYSTEM_ERROR'
        );
    }

    /**
     * Handle job failure
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('UpdateEbayInventory: Job failed permanently', [
            'listing_id' => $this->listing->id,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Get the tags for the job
     */
    public function tags(): array
    {
        return [
            'ebay-update',
            'listing:'.$this->listing->id,
            'product:'.$this->listing->product_id,
            'update-type:'.$this->updateType,
        ];
    }

    /**
     * Static helper to update all stale listings
     */
    public static function updateAllStale(IntegrationCredential $credential, int $hoursOld = 24): int
    {
        $listings = EbayListing::forCredential($credential->id)
            ->stale($hoursOld)
            ->get();

        foreach ($listings as $listing) {
            dispatch(new self($listing, 'both'));
        }

        return $listings->count();
    }

    /**
     * Static helper to sync quantities for all published listings
     */
    public static function syncAllQuantities(IntegrationCredential $credential): int
    {
        $listings = EbayListing::forCredential($credential->id)
            ->published()
            ->get();

        foreach ($listings as $index => $listing) {
            dispatch((new self($listing, 'quantity'))->delay(now()->addSeconds($index)));
        }

        return $listings->count();
    }
}
