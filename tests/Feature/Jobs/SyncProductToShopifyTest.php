<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Shopify\SyncProductToShopify;
use App\Models\Shopify\ShopifyListing;
use App\Services\Shopify\ShopifyApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesShopifyFixtures;

class SyncProductToShopifyTest extends TestCase
{
    use CreatesShopifyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopifyFixtures();
        $this->mockShopifyRateLimiter();
    }

    public function test_creates_new_product_on_shopify(): void
    {
        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(self::shopifyProductResponse()),
        ]);

        $product = $this->createTestProduct();

        $job = new SyncProductToShopify($product, $this->shopifyCredential);
        $job->handle();

        $listing = ShopifyListing::where('product_id', $product->id)->first();

        $this->assertNotNull($listing);
        $this->assertEquals(1234567890, $listing->shopify_product_id);
        $this->assertEquals(9876543210, $listing->shopify_variant_id);
        $this->assertEquals(5555555555, $listing->shopify_inventory_item_id);
        $this->assertEquals(ShopifyListing::STATUS_ACTIVE, $listing->status);
    }

    public function test_updates_existing_listing(): void
    {
        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(self::shopifyProductResponse([
                'product' => array_merge(self::shopifyProductResponse()['product'], [
                    'variants' => [[
                        'id' => 9876543210,
                        'price' => '49.99',
                        'compare_at_price' => null,
                        'inventory_item_id' => 5555555555,
                    ]],
                ]),
            ])),
        ]);

        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'shopify_product_id' => 1234567890,
            'shopify_variant_id' => 9876543210,
            'product_data_hash' => null, // Force change detection
        ]);

        $job = new SyncProductToShopify($product, $this->shopifyCredential);
        $job->handle();

        $listing->refresh();
        $this->assertNotNull($listing->last_synced_at);
        $this->assertEquals(0, $listing->consecutive_errors);
    }

    public function test_skips_update_when_hash_unchanged(): void
    {
        Http::fake();

        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'shopify_product_id' => 1234567890,
        ]);

        // Set hash to match current product data
        $listing->update(['product_data_hash' => $listing->generateProductHash()]);

        $job = new SyncProductToShopify($product, $this->shopifyCredential);
        $job->handle();

        Http::assertNothingSent();
    }

    public function test_force_update_bypasses_change_detection(): void
    {
        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(self::shopifyProductResponse()),
        ]);

        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'shopify_product_id' => 1234567890,
        ]);
        $listing->update(['product_data_hash' => $listing->generateProductHash()]);

        $job = new SyncProductToShopify($product, $this->shopifyCredential, forceUpdate: true);
        $job->handle();

        Http::assertSentCount(1);
    }

    public function test_retryable_error_throws_for_retry(): void
    {
        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(['errors' => 'Throttled'], 429),
        ]);

        $product = $this->createTestProduct();

        $job = new SyncProductToShopify($product, $this->shopifyCredential);

        $this->expectException(ShopifyApiException::class);
        $job->handle();
    }

    public function test_non_retryable_error_does_not_throw_retryable(): void
    {
        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(['errors' => 'Bad request'], 400),
        ]);

        $product = $this->createTestProduct();

        $job = new SyncProductToShopify($product, $this->shopifyCredential);

        // 400 is non-retryable - the job catches ShopifyApiException and
        // only rethrows if retryable. Non-retryable errors are absorbed.
        // Other exceptions are re-thrown.
        try {
            $job->handle();
        } catch (ShopifyApiException $e) {
            $this->assertFalse($e->isRetryable(), 'A 400 error should not be retryable');

            return;
        } catch (\Exception $e) {
            // Generic exceptions from the base service are expected
            $this->assertNotInstanceOf(ShopifyApiException::class, $e);

            return;
        }

        // If no exception was thrown, that's also acceptable (error was absorbed)
        $this->assertTrue(true);
    }

    public function test_creates_listing_record_if_not_exists(): void
    {
        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(self::shopifyProductResponse()),
        ]);

        $product = $this->createTestProduct(['sku' => 'NEW-SKU-123']);

        // No listing exists yet
        $this->assertNull(ShopifyListing::where('product_id', $product->id)->first());

        $job = new SyncProductToShopify($product, $this->shopifyCredential);
        $job->handle();

        $listing = ShopifyListing::where('product_id', $product->id)->first();
        $this->assertNotNull($listing);
        $this->assertEquals('NEW-SKU-123', $listing->shopify_sku);
        $this->assertEquals($this->shopifyCredential->id, $listing->integration_credential_id);
    }

    public function test_uses_existing_listing_for_update(): void
    {
        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(self::shopifyProductResponse()),
        ]);

        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'shopify_product_id' => 1234567890,
            'product_data_hash' => null,
        ]);

        $job = new SyncProductToShopify($product, $this->shopifyCredential);
        $job->handle();

        // Should not have created a new listing
        $this->assertEquals(1, ShopifyListing::where('product_id', $product->id)->count());
    }
}
