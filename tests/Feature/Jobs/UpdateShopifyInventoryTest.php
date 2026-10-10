<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Shopify\UpdateShopifyInventory;
use App\Models\Shopify\ShopifyListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesShopifyFixtures;

class UpdateShopifyInventoryTest extends TestCase
{
    use CreatesShopifyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopifyFixtures();
        $this->mockShopifyRateLimiter();
    }

    public function test_sets_inventory_level_when_quantity_differs(): void
    {
        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(self::shopifyInventoryLevelResponse(15)),
        ]);

        $product = $this->createTestProduct([
            'available_quantity' => 15,
            'stock_quantity' => 15,
        ]);
        $listing = $this->createShopifyListing($product, [
            'status' => ShopifyListing::STATUS_ACTIVE,
            'inventory_quantity' => 10,
            'shopify_inventory_item_id' => 5555,
        ]);

        $job = new UpdateShopifyInventory($listing);
        $job->handle();

        $listing->refresh();
        $this->assertEquals(15, $listing->inventory_quantity);
        $this->assertEquals(0, $listing->consecutive_errors);
    }

    public function test_skips_inactive_listing(): void
    {
        Http::fake();

        $product = $this->createTestProduct(['available_quantity' => 15]);
        $listing = $this->createShopifyListing($product, [
            'status' => ShopifyListing::STATUS_DRAFT,
            'inventory_quantity' => 10,
        ]);

        $job = new UpdateShopifyInventory($listing);
        $job->handle();

        Http::assertNothingSent();
    }

    public function test_skips_when_no_location_id(): void
    {
        Http::fake();

        // Remove primary_location_id from config
        $config = $this->shopifyCredential->config;
        unset($config['primary_location_id']);
        $this->shopifyCredential->update(['config' => $config]);

        $product = $this->createTestProduct(['available_quantity' => 15]);
        $listing = $this->createShopifyListing($product, [
            'status' => ShopifyListing::STATUS_ACTIVE,
            'inventory_quantity' => 10,
        ]);

        $job = new UpdateShopifyInventory($listing);
        $job->handle();

        Http::assertNothingSent();
    }

    public function test_skips_when_quantity_unchanged(): void
    {
        Http::fake();

        $product = $this->createTestProduct(['available_quantity' => 10]);
        $listing = $this->createShopifyListing($product, [
            'status' => ShopifyListing::STATUS_ACTIVE,
            'inventory_quantity' => 10,
        ]);

        $job = new UpdateShopifyInventory($listing);
        $job->handle();

        Http::assertNothingSent();
    }

    public function test_records_error_on_api_failure(): void
    {
        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(['errors' => 'Server error'], 500),
        ]);

        $product = $this->createTestProduct(['available_quantity' => 15]);
        $listing = $this->createShopifyListing($product, [
            'status' => ShopifyListing::STATUS_ACTIVE,
            'inventory_quantity' => 10,
            'shopify_inventory_item_id' => 5555,
            'consecutive_errors' => 0,
        ]);

        $job = new UpdateShopifyInventory($listing);

        try {
            $job->handle();
        } catch (\Throwable $e) {
            // Expected - retryable errors are thrown
        }

        $listing->refresh();
        $this->assertGreaterThan(0, $listing->consecutive_errors);
    }

    public function test_skips_when_no_inventory_item_id(): void
    {
        Http::fake();

        $product = $this->createTestProduct(['available_quantity' => 15]);
        $listing = $this->createShopifyListing($product, [
            'status' => ShopifyListing::STATUS_ACTIVE,
            'inventory_quantity' => 10,
            'shopify_inventory_item_id' => null,
        ]);

        $job = new UpdateShopifyInventory($listing);
        $job->handle();

        Http::assertNothingSent();
    }
}
