<?php

namespace Tests\Unit\Services;

use App\Models\Catalog\Product;
use App\Models\Shopify\ShopifyListing;
use App\Services\Shopify\ShopifyStoreImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesShopifyFixtures;

class ShopifyStoreImportServiceTest extends TestCase
{
    use CreatesShopifyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopifyFixtures();
        $this->mockShopifyRateLimiter();
    }

    public function test_create_product_from_shopify_maps_basic_fields(): void
    {
        $service = new ShopifyStoreImportService($this->shopifyCredential);

        $shopifyProduct = [
            'id' => 1234567,
            'title' => 'Imported Widget',
            'body_html' => '<p>Widget description</p>',
            'product_type' => 'Gadgets',
            'status' => 'active',
            'variants' => [
                [
                    'id' => 9876,
                    'price' => '39.99',
                    'compare_at_price' => '59.99',
                    'sku' => 'IMP-001',
                    'barcode' => '111222333444',
                    'weight' => 1.5,
                    'inventory_item_id' => 5555,
                    'inventory_quantity' => 20,
                    'inventory_management' => 'shopify',
                    'requires_shipping' => true,
                ],
            ],
            'image' => ['src' => 'https://cdn.shopify.com/widget.jpg'],
        ];

        $product = $service->createProductFromShopify(
            $shopifyProduct,
            $this->shopifyOrganization->id,
            $this->shopifyUser->id
        );

        $this->assertNotNull($product);
        $this->assertEquals('Imported Widget', $product->name);
        $this->assertEquals('<p>Widget description</p>', $product->description);
        $this->assertEquals('IMP-001', $product->sku);
        $this->assertEquals(39.99, (float) $product->selling_price);
        $this->assertEquals(59.99, (float) $product->msrp);
        $this->assertEquals('111222333444', $product->barcode);
        $this->assertTrue($product->manage_stock);
        $this->assertEquals(20, $product->stock_quantity);
        $this->assertEquals('https://cdn.shopify.com/widget.jpg', $product->featured_image);
    }

    public function test_fallback_sku_when_variant_sku_missing(): void
    {
        $service = new ShopifyStoreImportService($this->shopifyCredential);

        $shopifyProduct = [
            'id' => 7777777,
            'title' => 'No SKU Product',
            'body_html' => '',
            'product_type' => 'physical',
            'status' => 'active',
            'variants' => [
                [
                    'id' => 8888,
                    'price' => '10.00',
                    'inventory_management' => null,
                    'inventory_quantity' => 0,
                    'requires_shipping' => true,
                ],
            ],
        ];

        $product = $service->createProductFromShopify(
            $shopifyProduct,
            $this->shopifyOrganization->id,
            $this->shopifyUser->id
        );

        $this->assertEquals('SHOPIFY-7777777', $product->sku);
    }

    public function test_status_mapping_active(): void
    {
        $service = new ShopifyStoreImportService($this->shopifyCredential);

        $shopifyProduct = [
            'id' => 111,
            'title' => 'Active Product',
            'body_html' => '',
            'product_type' => 'physical',
            'status' => 'active',
            'variants' => [
                [
                    'id' => 222,
                    'price' => '10.00',
                    'sku' => 'ACT-1',
                    'inventory_management' => null,
                    'inventory_quantity' => 0,
                    'requires_shipping' => true,
                ],
            ],
        ];

        $product = $service->createProductFromShopify(
            $shopifyProduct,
            $this->shopifyOrganization->id,
            $this->shopifyUser->id
        );

        $this->assertEquals('active', $product->status);
    }

    public function test_create_listing_record_maps_shopify_ids(): void
    {
        $service = new ShopifyStoreImportService($this->shopifyCredential);
        $product = $this->createTestProduct();

        $shopifyProduct = [
            'id' => 5555555,
            'handle' => 'my-product',
            'status' => 'active',
            'variants' => [
                [
                    'id' => 6666666,
                    'price' => '25.00',
                    'compare_at_price' => null,
                    'sku' => 'LST-001',
                    'inventory_item_id' => 7777777,
                    'inventory_quantity' => 8,
                ],
            ],
        ];

        $listing = $service->createListingRecord($product, $shopifyProduct);

        $this->assertEquals($product->id, $listing->product_id);
        $this->assertEquals($this->shopifyCredential->id, $listing->integration_credential_id);
        $this->assertEquals(5555555, $listing->shopify_product_id);
        $this->assertEquals(6666666, $listing->shopify_variant_id);
        $this->assertEquals(7777777, $listing->shopify_inventory_item_id);
        $this->assertEquals('my-product', $listing->shopify_handle);
        $this->assertEquals(ShopifyListing::STATUS_ACTIVE, $listing->status);
        $this->assertEquals('25.00', $listing->listed_price);
        $this->assertEquals(8, $listing->inventory_quantity);
    }

    public function test_create_listing_record_draft_status(): void
    {
        $service = new ShopifyStoreImportService($this->shopifyCredential);
        $product = $this->createTestProduct();

        $shopifyProduct = [
            'id' => 8888888,
            'handle' => 'draft-product',
            'status' => 'draft',
            'variants' => [
                [
                    'id' => 9999999,
                    'price' => '15.00',
                    'sku' => 'DRF-001',
                    'inventory_item_id' => 1111111,
                    'inventory_quantity' => 0,
                ],
            ],
        ];

        $listing = $service->createListingRecord($product, $shopifyProduct);

        $this->assertEquals(ShopifyListing::STATUS_DRAFT, $listing->status);
    }

    public function test_import_all_products_skips_existing_listings(): void
    {
        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(self::shopifyProductListResponse(1)),
        ]);

        $service = new ShopifyStoreImportService($this->shopifyCredential);
        $product = $this->createTestProduct();

        // Create an existing listing for the Shopify product
        $this->createShopifyListing($product, ['shopify_product_id' => 1000001]);

        $result = $service->importAllProducts(
            $this->shopifyOrganization->id,
            $this->shopifyUser->id
        );

        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertEquals(0, $result['imported']);
    }

    public function test_import_links_existing_product_by_sku(): void
    {
        $existingProduct = $this->createTestProduct(['sku' => 'PROD-1']);

        $productList = self::shopifyProductListResponse(1);
        $productList['products'][0]['variants'][0]['sku'] = 'PROD-1';

        Http::fake([
            '*.myshopify.com/admin/*' => Http::response($productList),
        ]);

        $service = new ShopifyStoreImportService($this->shopifyCredential);

        $result = $service->importAllProducts(
            $this->shopifyOrganization->id,
            $this->shopifyUser->id
        );

        $this->assertEquals(1, $result['imported']);

        // Should have created a listing linking to existing product
        $listing = ShopifyListing::where('product_id', $existingProduct->id)->first();
        $this->assertNotNull($listing);
    }

    public function test_inventory_management_flag_mapping(): void
    {
        $service = new ShopifyStoreImportService($this->shopifyCredential);

        $shopifyProduct = [
            'id' => 333,
            'title' => 'Managed Product',
            'body_html' => '',
            'product_type' => 'physical',
            'status' => 'active',
            'variants' => [
                [
                    'id' => 444,
                    'price' => '20.00',
                    'sku' => 'MGD-1',
                    'inventory_management' => 'shopify',
                    'inventory_quantity' => 5,
                    'requires_shipping' => true,
                ],
            ],
        ];

        $product = $service->createProductFromShopify(
            $shopifyProduct,
            $this->shopifyOrganization->id,
            $this->shopifyUser->id
        );

        $this->assertTrue($product->manage_stock);
        $this->assertTrue($product->track_inventory);
    }
}
