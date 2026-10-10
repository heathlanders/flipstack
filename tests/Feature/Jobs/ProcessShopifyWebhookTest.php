<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Inventory\PushInventoryToMarketplaces;
use App\Jobs\Shopify\ProcessShopifyWebhook;
use App\Models\Shopify\ShopifyListing;
use App\Models\Shopify\ShopifySyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\CreatesShopifyFixtures;

class ProcessShopifyWebhookTest extends TestCase
{
    use CreatesShopifyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopifyFixtures();
    }

    public function test_products_update_updates_listing(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'shopify_product_id' => 12345,
            'status' => ShopifyListing::STATUS_ACTIVE,
            'sync_direction_lock' => null,
            'last_synced_at' => now()->subMinutes(5),
        ]);

        $payload = [
            'id' => 12345,
            'status' => 'active',
            'handle' => 'updated-handle',
            'variants' => [
                [
                    'price' => '39.99',
                    'compare_at_price' => '59.99',
                    'inventory_quantity' => 20,
                ],
            ],
        ];

        $job = new ProcessShopifyWebhook('products/update', 'test-store.myshopify.com', $payload);
        $job->handle();

        $listing->refresh();
        $this->assertEquals('39.99', $listing->listed_price);
        $this->assertEquals('59.99', $listing->compare_at_price);
        $this->assertEquals(20, $listing->inventory_quantity);
        $this->assertEquals('updated-handle', $listing->shopify_handle);
        $this->assertEquals('inbound', $listing->sync_direction_lock);
    }

    public function test_products_update_skips_recent_outbound_sync(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'shopify_product_id' => 12345,
            'listed_price' => '29.99',
            'sync_direction_lock' => 'outbound',
            'last_synced_at' => now()->subSeconds(10), // Within 30s window
        ]);

        $payload = [
            'id' => 12345,
            'status' => 'active',
            'variants' => [
                ['price' => '99.99', 'inventory_quantity' => 50],
            ],
        ];

        $job = new ProcessShopifyWebhook('products/update', 'test-store.myshopify.com', $payload);
        $job->handle();

        $listing->refresh();
        $this->assertEquals('29.99', $listing->listed_price); // Unchanged
    }

    public function test_products_update_processes_old_outbound_sync(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'shopify_product_id' => 12345,
            'listed_price' => '29.99',
            'sync_direction_lock' => 'outbound',
            'last_synced_at' => now()->subSeconds(60), // Beyond 30s window
        ]);

        $payload = [
            'id' => 12345,
            'status' => 'active',
            'variants' => [
                ['price' => '99.99', 'inventory_quantity' => 50],
            ],
        ];

        $job = new ProcessShopifyWebhook('products/update', 'test-store.myshopify.com', $payload);
        $job->handle();

        $listing->refresh();
        $this->assertEquals('99.99', $listing->listed_price); // Updated
    }

    public function test_products_update_creates_sync_log(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'shopify_product_id' => 12345,
            'last_synced_at' => now()->subMinutes(5),
        ]);

        $payload = [
            'id' => 12345,
            'status' => 'active',
            'variants' => [['price' => '29.99']],
        ];

        $job = new ProcessShopifyWebhook('products/update', 'test-store.myshopify.com', $payload);
        $job->handle();

        $this->assertDatabaseHas('shopify_sync_logs', [
            'shopify_listing_id' => $listing->id,
            'operation' => ShopifySyncLog::OP_UPDATE_PRODUCT,
            'direction' => ShopifySyncLog::DIRECTION_INBOUND,
            'status' => ShopifySyncLog::STATUS_SUCCESS,
        ]);
    }

    public function test_products_delete_marks_listing_as_deleted(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'shopify_product_id' => 12345,
            'status' => ShopifyListing::STATUS_ACTIVE,
        ]);

        $payload = ['id' => 12345];

        $job = new ProcessShopifyWebhook('products/delete', 'test-store.myshopify.com', $payload);
        $job->handle();

        $listing->refresh();
        $this->assertEquals(ShopifyListing::STATUS_DELETED, $listing->status);
    }

    public function test_orders_create_decrements_available_quantity(): void
    {
        Queue::fake();

        $product = $this->createTestProduct([
            'available_quantity' => 10,
            'stock_quantity' => 10,
        ]);
        $listing = $this->createShopifyListing($product, [
            'shopify_product_id' => 1234,
            'shopify_variant_id' => 5678,
        ]);

        $payload = self::shopifyOrderPayload([
            'line_items' => [
                [
                    'variant_id' => 5678,
                    'product_id' => 1234,
                    'quantity' => 2,
                    'price' => '29.99',
                ],
            ],
        ]);

        $job = new ProcessShopifyWebhook('orders/create', 'test-store.myshopify.com', $payload);
        $job->handle();

        $product->refresh();
        $this->assertEquals(8, $product->available_quantity);

        Queue::assertPushed(PushInventoryToMarketplaces::class);
    }

    public function test_orders_create_handles_multiple_line_items(): void
    {
        Queue::fake();

        $product1 = $this->createTestProduct(['available_quantity' => 10]);
        $product2 = $this->createTestProduct(['available_quantity' => 5]);
        $listing1 = $this->createShopifyListing($product1, ['shopify_variant_id' => 1111]);
        $listing2 = $this->createShopifyListing($product2, ['shopify_variant_id' => 2222]);

        $payload = self::shopifyOrderPayload([
            'line_items' => [
                ['variant_id' => 1111, 'quantity' => 3, 'price' => '10.00'],
                ['variant_id' => 2222, 'quantity' => 1, 'price' => '20.00'],
            ],
        ]);

        $job = new ProcessShopifyWebhook('orders/create', 'test-store.myshopify.com', $payload);
        $job->handle();

        $product1->refresh();
        $product2->refresh();
        $this->assertEquals(7, $product1->available_quantity);
        $this->assertEquals(4, $product2->available_quantity);

        Queue::assertPushed(PushInventoryToMarketplaces::class, 2);
    }

    public function test_orders_create_skips_unknown_variant_ids(): void
    {
        Queue::fake();

        $payload = self::shopifyOrderPayload([
            'line_items' => [
                ['variant_id' => 999999, 'quantity' => 1, 'price' => '10.00'],
            ],
        ]);

        $job = new ProcessShopifyWebhook('orders/create', 'test-store.myshopify.com', $payload);
        $job->handle();

        Queue::assertNotPushed(PushInventoryToMarketplaces::class);
    }

    public function test_orders_updated_does_not_reserve_inventory(): void
    {
        Queue::fake();

        $product = $this->createTestProduct(['available_quantity' => 10]);
        $listing = $this->createShopifyListing($product, ['shopify_variant_id' => 5678]);

        $payload = self::shopifyOrderPayload([
            'line_items' => [
                ['variant_id' => 5678, 'quantity' => 1, 'price' => '29.99'],
            ],
        ]);

        $job = new ProcessShopifyWebhook('orders/updated', 'test-store.myshopify.com', $payload);
        $job->handle();

        $product->refresh();
        $this->assertEquals(10, $product->available_quantity); // Unchanged
        Queue::assertNotPushed(PushInventoryToMarketplaces::class);
    }

    public function test_inventory_levels_update_updates_listing_quantity(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'shopify_inventory_item_id' => 44444,
            'inventory_quantity' => 10,
            'sync_direction_lock' => null,
            'last_synced_at' => now()->subMinutes(5),
        ]);

        $payload = [
            'inventory_item_id' => 44444,
            'location_id' => 67890,
            'available' => 25,
        ];

        $job = new ProcessShopifyWebhook('inventory_levels/update', 'test-store.myshopify.com', $payload);
        $job->handle();

        $listing->refresh();
        $this->assertEquals(25, $listing->inventory_quantity);
        $this->assertEquals('inbound', $listing->sync_direction_lock);
    }

    public function test_inventory_levels_update_respects_sync_lock(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'shopify_inventory_item_id' => 44444,
            'inventory_quantity' => 10,
            'sync_direction_lock' => 'outbound',
            'last_synced_at' => now()->subSeconds(10),
        ]);

        $payload = [
            'inventory_item_id' => 44444,
            'location_id' => 67890,
            'available' => 25,
        ];

        $job = new ProcessShopifyWebhook('inventory_levels/update', 'test-store.myshopify.com', $payload);
        $job->handle();

        $listing->refresh();
        $this->assertEquals(10, $listing->inventory_quantity); // Unchanged
    }

    public function test_app_uninstalled_deactivates_credential_and_tokens(): void
    {
        $job = new ProcessShopifyWebhook('app/uninstalled', 'test-store.myshopify.com', []);
        $job->handle();

        $this->shopifyCredential->refresh();
        $this->shopifyToken->refresh();

        $this->assertFalse($this->shopifyCredential->is_active);
        $this->assertFalse($this->shopifyToken->is_active);
    }

    public function test_unknown_topic_handled_gracefully(): void
    {
        $job = new ProcessShopifyWebhook('some/unknown-topic', 'test-store.myshopify.com', ['id' => 1]);
        $job->handle();

        // Should not throw, just logs
        $this->assertTrue(true);
    }
}
