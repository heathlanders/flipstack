<?php

namespace Tests\Feature\Fulfillment;

use App\Jobs\Fulfillment\SyncShopifyOrders;
use App\Jobs\Inventory\PushInventoryToMarketplaces;
use App\Jobs\Shopify\ProcessShopifyWebhook;
use App\Models\Fulfillment\Order;
use App\Services\Fulfillment\ShopifyOrderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\CreatesShopifyFixtures;

class ShopifyOrderSyncTest extends TestCase
{
    use CreatesShopifyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopifyFixtures();
        $this->mockShopifyConfigured();
        $this->mockShopifyRateLimiter();
        Queue::fake();
    }

    public function test_import_order_creates_order_record(): void
    {
        $payload = static::shopifyOrderPayload();

        $service = new ShopifyOrderSyncService($this->shopifyCredential);
        $result = $service->importOrder($payload);

        $this->assertTrue($result['created']);
        $this->assertInstanceOf(Order::class, $result['order']);
        $this->assertEquals('shopify', $result['order']->marketplace);
        $this->assertEquals((string) $payload['id'], $result['order']->marketplace_order_id);
        $this->assertEquals(Order::STATUS_PENDING, $result['order']->status);
    }

    public function test_import_order_creates_order_items(): void
    {
        $payload = static::shopifyOrderPayload();

        $service = new ShopifyOrderSyncService($this->shopifyCredential);
        $result = $service->importOrder($payload);

        $order = $result['order'];
        $this->assertEquals(1, $order->items()->count());

        $item = $order->items()->first();
        $this->assertEquals('Test Product', $item->title);
        $this->assertEquals(1, $item->quantity);
        $this->assertEquals('29.99', $item->unit_price);
    }

    public function test_import_order_matches_product_by_variant_id(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'shopify_variant_id' => 9876543210,
        ]);

        $payload = static::shopifyOrderPayload();

        $service = new ShopifyOrderSyncService($this->shopifyCredential);
        $result = $service->importOrder($payload);

        $item = $result['order']->items()->first();
        $this->assertEquals($product->id, $item->product_id);
    }

    public function test_import_order_reserves_inventory(): void
    {
        $product = $this->createTestProduct(['available_quantity' => 10]);
        $this->createShopifyListing($product, [
            'shopify_variant_id' => 9876543210,
        ]);

        $payload = static::shopifyOrderPayload();

        $service = new ShopifyOrderSyncService($this->shopifyCredential);
        $service->importOrder($payload);

        $product->refresh();
        $this->assertEquals(9, $product->available_quantity);

        Queue::assertPushed(PushInventoryToMarketplaces::class);
    }

    public function test_import_order_upserts_existing_order(): void
    {
        $payload = static::shopifyOrderPayload();

        $service = new ShopifyOrderSyncService($this->shopifyCredential);
        $result1 = $service->importOrder($payload);
        $this->assertTrue($result1['created']);

        $result2 = $service->importOrder($payload);
        $this->assertFalse($result2['created']);
        $this->assertEquals($result1['order']->id, $result2['order']->id);
    }

    public function test_import_cancelled_order_sets_cancelled_status(): void
    {
        $payload = static::shopifyOrderPayload([
            'cancelled_at' => now()->toIso8601String(),
        ]);

        $service = new ShopifyOrderSyncService($this->shopifyCredential);
        $result = $service->importOrder($payload);

        $this->assertEquals(Order::STATUS_CANCELLED, $result['order']->status);
    }

    public function test_import_fulfilled_order_sets_shipped_status(): void
    {
        $payload = static::shopifyOrderPayload([
            'fulfillment_status' => 'fulfilled',
        ]);

        $service = new ShopifyOrderSyncService($this->shopifyCredential);
        $result = $service->importOrder($payload);

        $this->assertEquals(Order::STATUS_SHIPPED, $result['order']->status);
    }

    public function test_import_order_extracts_shipping_address(): void
    {
        $payload = static::shopifyOrderPayload([
            'shipping_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address1' => '123 Main St',
                'address2' => 'Apt 4',
                'city' => 'Springfield',
                'province_code' => 'IL',
                'zip' => '62701',
                'country_code' => 'US',
                'phone' => '555-0100',
                'name' => 'John Doe',
            ],
        ]);

        $service = new ShopifyOrderSyncService($this->shopifyCredential);
        $result = $service->importOrder($payload);

        $order = $result['order'];
        $this->assertEquals('John Doe', $order->shipping_address_name);
        $this->assertEquals('123 Main St', $order->shipping_address_line1);
        $this->assertEquals('Apt 4', $order->shipping_address_line2);
        $this->assertEquals('Springfield', $order->shipping_city);
        $this->assertEquals('IL', $order->shipping_state);
        $this->assertEquals('62701', $order->shipping_postal_code);
    }

    public function test_webhook_orders_create_imports_order(): void
    {
        $payload = static::shopifyOrderPayload();

        $job = new ProcessShopifyWebhook('orders/create', 'test-store.myshopify.com', $payload);
        $job->handle();

        $order = Order::where('marketplace_order_id', (string) $payload['id'])->first();
        $this->assertNotNull($order);
        $this->assertEquals('shopify', $order->marketplace);
    }

    public function test_webhook_orders_cancelled_cancels_order(): void
    {
        $payload = static::shopifyOrderPayload();
        $service = new ShopifyOrderSyncService($this->shopifyCredential);
        $result = $service->importOrder($payload);
        $order = $result['order'];

        $cancelPayload = static::shopifyOrderPayload([
            'cancelled_at' => now()->toIso8601String(),
        ]);

        $job = new ProcessShopifyWebhook('orders/cancelled', 'test-store.myshopify.com', $cancelPayload);
        $job->handle();

        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
    }

    public function test_sync_shopify_orders_job_dispatches(): void
    {
        Queue::fake();

        $this->shopifyCredential->update(['auto_sync_enabled' => true]);

        SyncShopifyOrders::dispatch();

        Queue::assertPushed(SyncShopifyOrders::class);
    }

    public function test_match_order_items_by_sku(): void
    {
        $product = $this->createTestProduct(['sku' => 'SHOP-TEST-001']);

        $payload = static::shopifyOrderPayload([
            'line_items' => [
                [
                    'id' => 1,
                    'variant_id' => 99999, // Non-matching variant ID
                    'title' => 'Test Product',
                    'quantity' => 1,
                    'price' => '29.99',
                    'sku' => 'SHOP-TEST-001',
                ],
            ],
        ]);

        $service = new ShopifyOrderSyncService($this->shopifyCredential);
        $result = $service->importOrder($payload);

        $item = $result['order']->items()->first();
        $this->assertEquals($product->id, $item->product_id);
    }
}
