<?php

namespace App\Jobs\Shopify;

use App\Models\Fulfillment\Order;
use App\Models\Integration\IntegrationCredential;
use App\Models\Shopify\ShopifyListing;
use App\Models\Shopify\ShopifySyncLog;
use App\Services\Fulfillment\ShopifyOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessShopifyWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected string $topic,
        protected string $shopDomain,
        protected array $payload
    ) {}

    public function handle(): void
    {
        Log::info('Processing Shopify webhook', [
            'topic' => $this->topic,
            'shop' => $this->shopDomain,
        ]);

        $credential = IntegrationCredential::where('platform', 'shopify')
            ->where('is_active', true)
            ->whereJsonContains('config->shop_domain', $this->shopDomain)
            ->first();

        if (! $credential) {
            Log::warning('No credential found for Shopify webhook', [
                'shop_domain' => $this->shopDomain,
            ]);

            return;
        }

        withAuditActor(null, function () use ($credential) {
            match ($this->topic) {
                'products/create',
                'products/update' => $this->handleProductUpdate($credential),
                'products/delete' => $this->handleProductDelete($credential),
                'orders/create' => $this->handleOrderCreate($credential),
                'orders/updated' => $this->handleOrderUpdated($credential),
                'orders/cancelled' => $this->handleOrderCancelled($credential),
                'inventory_levels/update' => $this->handleInventoryUpdate($credential),
                'app/uninstalled' => $this->handleAppUninstalled($credential),
                default => Log::info('Unhandled Shopify webhook topic', ['topic' => $this->topic]),
            };
        }, [
            'source' => 'webhook',
            'webhook_topic' => $this->topic,
            'shop_domain' => $this->shopDomain,
        ], $credential->organization);
    }

    protected function handleProductUpdate(IntegrationCredential $credential): void
    {
        $shopifyProductId = $this->payload['id'] ?? null;

        if (! $shopifyProductId) {
            return;
        }

        $listing = ShopifyListing::where('shopify_product_id', $shopifyProductId)
            ->where('integration_credential_id', $credential->id)
            ->first();

        if (! $listing) {
            return;
        }

        // Skip if outbound sync was more recent (prevent loops)
        if ($listing->sync_direction_lock === 'outbound' &&
            $listing->last_synced_at &&
            $listing->last_synced_at->gt(now()->subSeconds(30))) {
            Log::info('Shopify webhook skipped - recent outbound sync', [
                'listing_id' => $listing->id,
            ]);

            return;
        }

        $variant = $this->payload['variants'][0] ?? [];

        $listing->update([
            'status' => ($this->payload['status'] ?? 'active') === 'active'
                ? ShopifyListing::STATUS_ACTIVE
                : ShopifyListing::STATUS_DRAFT,
            'listed_price' => $variant['price'] ?? $listing->listed_price,
            'compare_at_price' => $variant['compare_at_price'] ?? $listing->compare_at_price,
            'inventory_quantity' => $variant['inventory_quantity'] ?? $listing->inventory_quantity,
            'shopify_handle' => $this->payload['handle'] ?? $listing->shopify_handle,
            'last_shopify_response' => $this->payload,
            'last_synced_at' => now(),
            'sync_direction_lock' => 'inbound',
        ]);

        ShopifySyncLog::create([
            'integration_credential_id' => $credential->id,
            'shopify_listing_id' => $listing->id,
            'product_id' => $listing->product_id,
            'team_id' => $credential->team_id,
            'operation' => ShopifySyncLog::OP_UPDATE_PRODUCT,
            'direction' => ShopifySyncLog::DIRECTION_INBOUND,
            'shopify_product_id' => $shopifyProductId,
            'status' => ShopifySyncLog::STATUS_SUCCESS,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    protected function handleProductDelete(IntegrationCredential $credential): void
    {
        $shopifyProductId = $this->payload['id'] ?? null;

        if (! $shopifyProductId) {
            return;
        }

        $listing = ShopifyListing::where('shopify_product_id', $shopifyProductId)
            ->where('integration_credential_id', $credential->id)
            ->first();

        if ($listing) {
            $listing->update(['status' => ShopifyListing::STATUS_DELETED]);
        }
    }

    protected function handleOrderCreate(IntegrationCredential $credential): void
    {
        $orderId = $this->payload['id'] ?? null;

        Log::info('Shopify order created webhook received', [
            'order_id' => $orderId,
            'shop' => $this->shopDomain,
        ]);

        try {
            $syncService = new ShopifyOrderSyncService($credential);
            $result = $syncService->importOrder($this->payload);

            Log::info('Shopify order imported via webhook', [
                'order_id' => $orderId,
                'created' => $result['created'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to import Shopify order from webhook', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function handleOrderUpdated(IntegrationCredential $credential): void
    {
        $orderId = $this->payload['id'] ?? null;

        Log::info('Shopify order updated webhook received', [
            'order_id' => $orderId,
            'shop' => $this->shopDomain,
        ]);

        $order = Order::where('marketplace_order_id', (string) $orderId)
            ->where('marketplace', 'shopify')
            ->first();

        if (! $order) {
            return;
        }

        try {
            $syncService = new ShopifyOrderSyncService($credential);
            $syncService->handleOrderUpdate($order, $this->payload);
        } catch (\Exception $e) {
            Log::error('Failed to update Shopify order from webhook', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function handleOrderCancelled(IntegrationCredential $credential): void
    {
        $orderId = $this->payload['id'] ?? null;

        Log::info('Shopify order cancelled webhook received', [
            'order_id' => $orderId,
            'shop' => $this->shopDomain,
        ]);

        $order = Order::where('marketplace_order_id', (string) $orderId)
            ->where('marketplace', 'shopify')
            ->first();

        if (! $order) {
            return;
        }

        if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])) {
            return;
        }

        try {
            $syncService = new ShopifyOrderSyncService($credential);
            $syncService->handleOrderCancellation($order);
        } catch (\Exception $e) {
            Log::error('Failed to cancel Shopify order from webhook', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function handleInventoryUpdate(IntegrationCredential $credential): void
    {
        $inventoryItemId = $this->payload['inventory_item_id'] ?? null;
        $available = $this->payload['available'] ?? null;

        if (! $inventoryItemId || $available === null) {
            return;
        }

        $listing = ShopifyListing::where('shopify_inventory_item_id', $inventoryItemId)
            ->where('integration_credential_id', $credential->id)
            ->first();

        if (! $listing) {
            return;
        }

        // Skip if outbound sync was recent
        if ($listing->sync_direction_lock === 'outbound' &&
            $listing->last_synced_at?->gt(now()->subSeconds(30))) {
            return;
        }

        $listing->update([
            'inventory_quantity' => $available,
            'last_synced_at' => now(),
            'sync_direction_lock' => 'inbound',
        ]);
    }

    protected function handleAppUninstalled(IntegrationCredential $credential): void
    {
        Log::warning('Shopify app uninstalled', [
            'credential_id' => $credential->id,
            'shop' => $this->shopDomain,
        ]);

        $credential->oauthTokens()
            ->where('platform', 'shopify')
            ->update(['is_active' => false]);

        $credential->update(['is_active' => false]);
    }

    public function tags(): array
    {
        return [
            'shopify-webhook',
            'topic:'.$this->topic,
            'shop:'.$this->shopDomain,
        ];
    }
}
