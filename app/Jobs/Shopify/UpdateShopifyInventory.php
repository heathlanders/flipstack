<?php

namespace App\Jobs\Shopify;

use App\Models\Shopify\ShopifyListing;
use App\Models\User\User;
use App\Notifications\Shopify\ShopifySyncNotification;
use App\Services\Shopify\ShopifyApiException;
use App\Services\Shopify\ShopifyInventoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateShopifyInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public ShopifyListing $listing,
        public string $updateType = 'quantity'
    ) {
        $this->onQueue('shopify-sync');
    }

    public function handle(): void
    {
        if (! $this->listing->isActive()) {
            return;
        }

        $credential = $this->listing->integrationCredential;
        $product = $this->listing->product;

        if (! $credential || ! $product) {
            return;
        }

        $config = $credential->config ?? [];
        $locationId = $config['primary_location_id'] ?? null;

        if (! $locationId || ! $this->listing->shopify_inventory_item_id) {
            Log::warning('UpdateShopifyInventory: Missing location or inventory item', [
                'listing_id' => $this->listing->id,
            ]);

            return;
        }

        try {
            $service = new ShopifyInventoryService($credential);
            $newQuantity = $product->available_quantity ?? $product->stock_quantity;

            if ($this->listing->inventory_quantity === $newQuantity) {
                return;
            }

            $inventoryItemId = (int) $this->listing->shopify_inventory_item_id;

            // Set desired quantity at primary location
            $service->setInventoryLevel($locationId, $inventoryItemId, $newQuantity);

            // Zero out all other locations to prevent double-counting
            try {
                $locations = $service->getLocations();
                foreach ($locations as $location) {
                    $locId = (int) $location['id'];
                    if ($locId !== (int) $locationId) {
                        try {
                            $service->setInventoryLevel($locId, $inventoryItemId, 0);
                        } catch (\Exception $e) {
                            // Location may not stock this item
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('UpdateShopifyInventory: failed to zero other locations', [
                    'listing_id' => $this->listing->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->listing->update([
                'inventory_quantity' => $newQuantity,
                'last_synced_at' => now(),
                'consecutive_errors' => 0,
            ]);

            Log::info('UpdateShopifyInventory: Quantity updated', [
                'listing_id' => $this->listing->id,
                'old_quantity' => $this->listing->inventory_quantity,
                'new_quantity' => $newQuantity,
            ]);

        } catch (ShopifyApiException $e) {
            $this->listing->recordError($e->getMessage(), $e->getShopifyErrorCode());

            if ($e->isRetryable() && $this->attempts() < $this->tries) {
                throw $e;
            }
        } catch (\Throwable $e) {
            $this->listing->recordError($e->getMessage(), 'SYSTEM_ERROR');

            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('UpdateShopifyInventory: Job failed permanently', [
            'listing_id' => $this->listing->id,
            'error' => $exception?->getMessage(),
        ]);

        $product = $this->listing->product;
        if ($product) {
            $admins = User::where('organization_id', $product->organization_id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'owner']))
                ->get();

            $notification = new ShopifySyncNotification(
                action: 'inventory_sync',
                success: false,
                detail: "Failed to sync inventory for \"{$product->name}\" to Shopify: ".($exception?->getMessage() ?? 'Unknown error'),
                productName: $product->name,
                productId: (string) $product->id,
            );

            foreach ($admins as $admin) {
                $admin->notify($notification);
            }
        }
    }

    public function tags(): array
    {
        return [
            'shopify-update',
            'listing:'.$this->listing->id,
            'product:'.$this->listing->product_id,
        ];
    }
}
