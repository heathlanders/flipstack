<?php

namespace App\Jobs\Shopify;

use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Shopify\ShopifyCollectionMapping;
use App\Models\Shopify\ShopifyListing;
use App\Models\User\User;
use App\Notifications\Shopify\ShopifySyncNotification;
use App\Services\Shopify\ShopifyApiException;
use App\Services\Shopify\ShopifyCollectionService;
use App\Services\Shopify\ShopifyProductService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductToShopify implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        protected Product $product,
        protected IntegrationCredential $credential,
        protected bool $forceUpdate = false
    ) {}

    public function handle(): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $this->product->loadMissing(['images', 'brand', 'category', 'ebayListings']);

        try {
            $service = new ShopifyProductService($this->credential);

            $listing = ShopifyListing::where('product_id', $this->product->id)
                ->where('integration_credential_id', $this->credential->id)
                ->first();

            if ($listing && $listing->shopify_product_id) {
                // Update existing
                $service->updateProduct($this->product, $listing, $this->forceUpdate);
            } else {
                // Create new listing record if needed
                if (! $listing) {
                    $listing = ShopifyListing::create([
                        'product_id' => $this->product->id,
                        'integration_credential_id' => $this->credential->id,
                        'team_id' => $this->credential->team_id,
                        'shopify_sku' => $this->product->sku ?? $this->product->generateEbaySku(),
                        'status' => ShopifyListing::STATUS_PENDING,
                        'currency' => $this->product->currency ?? 'USD',
                    ]);
                }

                $service->createProduct($this->product, $listing);

                // Set inventory at primary location and zero out others
                // (Shopify auto-distributes to all locations on create)
                $this->setCorrectInventoryLevels($listing);
            }

            $this->assignToCollections($listing);
        } catch (ShopifyApiException $e) {
            Log::error('Shopify sync failed for product', [
                'product_id' => $this->product->id,
                'error' => $e->getMessage(),
                'status_code' => $e->getHttpStatusCode(),
            ]);

            if ($e->isRetryable() && $this->attempts() < $this->tries) {
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Shopify sync failed for product', [
                'product_id' => $this->product->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function setCorrectInventoryLevels(ShopifyListing $listing): void
    {
        if (! $listing->shopify_inventory_item_id) {
            return;
        }

        try {
            $inventoryService = new \App\Services\Shopify\ShopifyInventoryService($this->credential);
            $config = $this->credential->config ?? [];
            $primaryLocationId = (int) ($config['shopify_location_id'] ?? 0);

            if (! $primaryLocationId) {
                $locations = $inventoryService->getLocations();
                $primaryLocationId = (int) ($locations[0]['id'] ?? 0);
            }

            if (! $primaryLocationId) {
                return;
            }

            $desiredQty = (int) ($this->product->available_quantity ?? $this->product->stock_quantity ?? 0);
            $inventoryItemId = (int) $listing->shopify_inventory_item_id;

            // Set desired quantity at primary location
            $inventoryService->setInventoryLevel($primaryLocationId, $inventoryItemId, $desiredQty);

            // Zero out all other locations
            $locations = $inventoryService->getLocations();
            foreach ($locations as $location) {
                $locId = (int) $location['id'];
                if ($locId !== $primaryLocationId) {
                    try {
                        $inventoryService->setInventoryLevel($locId, $inventoryItemId, 0);
                    } catch (\Exception $e) {
                        // Location may not have this item stocked -- that's fine
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('SyncProductToShopify: failed to set inventory levels after create', [
                'product_id' => $this->product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function assignToCollections(ShopifyListing $listing): void
    {
        if (empty($this->credential->config['shopify_collection_mapping_enabled'])) {
            return;
        }

        if (! $listing->shopify_product_id) {
            return;
        }

        $mappings = ShopifyCollectionMapping::forCredential($this->credential->id)
            ->where(fn ($q) => $q->where('category_id', $this->product->category_id)
                ->orWhere(fn ($q2) => $q2->whereNull('category_id')->where('is_default', true))
            )->get();

        // Prefer specific category match over default
        $specific = $mappings->whereNotNull('category_id');
        $toAssign = $specific->isNotEmpty() ? $specific : $mappings->where('is_default', true);

        $collectionService = new ShopifyCollectionService($this->credential);
        foreach ($toAssign as $mapping) {
            try {
                $collectionService->addProductToCollection(
                    $mapping->shopify_collection_id,
                    $listing->shopify_product_id
                );
            } catch (\Exception $e) {
                // 422 = already in collection (idempotent), skip it
                if (! ($e instanceof ShopifyApiException && $e->getHttpStatusCode() === 422)) {
                    Log::warning('Failed to add product to Shopify collection', [
                        'product_id' => $this->product->id,
                        'collection_id' => $mapping->shopify_collection_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Shopify sync job failed after all retries', [
            'product_id' => $this->product->id,
            'error' => $exception?->getMessage(),
        ]);

        $this->notifyOrgAdmins(
            $this->product->organization_id,
            new ShopifySyncNotification(
                action: 'product_sync',
                success: false,
                detail: "Failed to sync \"{$this->product->name}\" to Shopify: ".($exception?->getMessage() ?? 'Unknown error'),
                productName: $this->product->name,
                productId: (string) $this->product->id,
            )
        );
    }

    protected function notifyOrgAdmins(int $organizationId, ShopifySyncNotification $notification): void
    {
        $admins = User::where('organization_id', $organizationId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'owner']))
            ->get();

        foreach ($admins as $admin) {
            $admin->notify($notification);
        }
    }

    public function tags(): array
    {
        return [
            'shopify',
            'sync',
            'product:'.$this->product->id,
        ];
    }
}
