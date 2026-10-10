<?php

namespace Tests\Unit\Models;

use App\Models\Shopify\ShopifyListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesShopifyFixtures;

class ShopifyListingTest extends TestCase
{
    use CreatesShopifyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopifyFixtures();
    }

    public function test_is_pending_returns_true_for_pending_status(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, ['status' => ShopifyListing::STATUS_PENDING]);

        $this->assertTrue($listing->isPending());
        $this->assertFalse($listing->isActive());
    }

    public function test_is_active_returns_true_for_active_status(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, ['status' => ShopifyListing::STATUS_ACTIVE]);

        $this->assertTrue($listing->isActive());
        $this->assertFalse($listing->isPending());
    }

    public function test_has_error_returns_true_for_error_status(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, ['status' => ShopifyListing::STATUS_ERROR]);

        $this->assertTrue($listing->hasError());
    }

    public function test_can_retry_returns_true_when_errors_below_threshold(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'status' => ShopifyListing::STATUS_ERROR,
            'consecutive_errors' => 3,
        ]);

        $this->assertTrue($listing->canRetry());
    }

    public function test_can_retry_returns_false_at_five_consecutive_errors(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'status' => ShopifyListing::STATUS_ERROR,
            'consecutive_errors' => 5,
        ]);

        $this->assertFalse($listing->canRetry());
    }

    public function test_can_retry_returns_false_for_non_error_status(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'status' => ShopifyListing::STATUS_ACTIVE,
            'consecutive_errors' => 0,
        ]);

        $this->assertFalse($listing->canRetry());
    }

    public function test_is_live_returns_true_for_active_and_draft(): void
    {
        $product = $this->createTestProduct();

        $active = $this->createShopifyListing($product, ['status' => ShopifyListing::STATUS_ACTIVE]);
        $this->assertTrue($active->isLive());

        $draft = $this->createShopifyListing($this->createTestProduct(), ['status' => ShopifyListing::STATUS_DRAFT]);
        $this->assertTrue($draft->isLive());

        $error = $this->createShopifyListing($this->createTestProduct(), ['status' => ShopifyListing::STATUS_ERROR]);
        $this->assertFalse($error->isLive());
    }

    public function test_record_error_increments_consecutive_errors(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'status' => ShopifyListing::STATUS_ACTIVE,
            'consecutive_errors' => 0,
        ]);

        $listing->recordError('API timeout', 'TIMEOUT');

        $listing->refresh();
        $this->assertEquals(ShopifyListing::STATUS_ERROR, $listing->status);
        $this->assertEquals(1, $listing->consecutive_errors);
        $this->assertEquals('API timeout', $listing->last_error_message);
        $this->assertEquals('TIMEOUT', $listing->last_error_code);
        $this->assertNotNull($listing->last_error_at);
    }

    public function test_mark_synced_resets_consecutive_errors(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'consecutive_errors' => 3,
        ]);

        $listing->markSynced();

        $listing->refresh();
        $this->assertEquals(0, $listing->consecutive_errors);
        $this->assertNotNull($listing->last_synced_at);
    }

    public function test_generate_product_hash_is_deterministic(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product);

        $hash1 = $listing->generateProductHash();
        $hash2 = $listing->generateProductHash();

        $this->assertEquals($hash1, $hash2);
    }

    public function test_generate_product_hash_changes_when_product_data_changes(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product);

        $hash1 = $listing->generateProductHash();

        $product->update(['name' => 'Updated Product Name']);
        $listing->refresh();

        $hash2 = $listing->generateProductHash();

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_has_product_changed_returns_true_with_null_hash(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, ['product_data_hash' => null]);

        $this->assertTrue($listing->hasProductChanged());
    }

    public function test_has_product_changed_returns_false_when_hash_matches(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product);

        $listing->update(['product_data_hash' => $listing->generateProductHash()]);

        $this->assertFalse($listing->hasProductChanged());
    }

    public function test_shopify_url_accessor_format(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'shopify_product_id' => 123456,
        ]);

        $this->assertEquals(
            'https://test-store.myshopify.com/admin/products/123456',
            $listing->shopify_url
        );
    }

    public function test_shopify_url_returns_null_without_product_id(): void
    {
        $product = $this->createTestProduct();
        $listing = $this->createShopifyListing($product, [
            'shopify_product_id' => null,
        ]);

        $this->assertNull($listing->shopify_url);
    }

    public function test_retryable_scope(): void
    {
        $product1 = $this->createTestProduct();
        $product2 = $this->createTestProduct();
        $product3 = $this->createTestProduct();

        $this->createShopifyListing($product1, [
            'status' => ShopifyListing::STATUS_ERROR,
            'consecutive_errors' => 2,
        ]);
        $this->createShopifyListing($product2, [
            'status' => ShopifyListing::STATUS_ERROR,
            'consecutive_errors' => 5,
        ]);
        $this->createShopifyListing($product3, [
            'status' => ShopifyListing::STATUS_ACTIVE,
        ]);

        $retryable = ShopifyListing::retryable()->get();
        $this->assertCount(1, $retryable);
    }

    public function test_needs_sync_scope(): void
    {
        $product1 = $this->createTestProduct();
        $product2 = $this->createTestProduct();
        $product3 = $this->createTestProduct();

        $this->createShopifyListing($product1, ['status' => ShopifyListing::STATUS_PENDING]);
        $this->createShopifyListing($product2, ['status' => ShopifyListing::STATUS_CREATED]);
        $this->createShopifyListing($product3, ['status' => ShopifyListing::STATUS_ACTIVE]);

        $needsSync = ShopifyListing::needsSync()->get();
        $this->assertCount(2, $needsSync);
    }

    public function test_published_scope(): void
    {
        $product1 = $this->createTestProduct();
        $product2 = $this->createTestProduct();

        $this->createShopifyListing($product1, ['status' => ShopifyListing::STATUS_ACTIVE]);
        $this->createShopifyListing($product2, ['status' => ShopifyListing::STATUS_DRAFT]);

        $published = ShopifyListing::published()->get();
        $this->assertCount(1, $published);
    }
}
