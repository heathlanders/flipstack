<?php

namespace Tests\Unit\Services;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Services\Shopify\ShopifyProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesShopifyFixtures;

class ShopifyProductServiceTest extends TestCase
{
    use CreatesShopifyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopifyFixtures();
        $this->mockShopifyRateLimiter();
    }

    public function test_to_shopify_product_data_maps_basic_fields(): void
    {
        $product = $this->createTestProduct([
            'name' => 'Widget Pro',
            'description' => '<p>Great widget</p>',
            'selling_price' => 49.99,
            'sku' => 'WID-001',
            'barcode' => '012345678901',
            'weight' => 2.5,
            'stock_quantity' => 15,
            'available_quantity' => 15,
            'manage_stock' => true,
        ]);

        Http::fake();
        $service = new ShopifyProductService($this->shopifyCredential);
        $data = $service->toShopifyProductData($product);

        $this->assertEquals('Widget Pro', $data['title']);
        $this->assertEquals('<p>Great widget</p>', $data['body_html']);
        $this->assertEquals('active', $data['status']);
        $this->assertEquals('49.99', $data['variants'][0]['price']);
        $this->assertEquals('WID-001', $data['variants'][0]['sku']);
        $this->assertEquals('012345678901', $data['variants'][0]['barcode']);
        $this->assertEquals(2.5, $data['variants'][0]['weight']);
        $this->assertEquals('lb', $data['variants'][0]['weight_unit']);
        $this->assertEquals('shopify', $data['variants'][0]['inventory_management']);
        $this->assertArrayNotHasKey('inventory_quantity', $data['variants'][0]);
    }

    public function test_compare_at_price_only_set_when_msrp_exceeds_selling_price(): void
    {
        $product = $this->createTestProduct([
            'selling_price' => 29.99,
            'msrp' => 49.99,
        ]);

        Http::fake();
        $service = new ShopifyProductService($this->shopifyCredential);
        $data = $service->toShopifyProductData($product);

        $this->assertEquals('49.99', $data['variants'][0]['compare_at_price']);
    }

    public function test_compare_at_price_null_when_msrp_not_greater(): void
    {
        $product = $this->createTestProduct([
            'selling_price' => 49.99,
            'msrp' => 29.99,
        ]);

        Http::fake();
        $service = new ShopifyProductService($this->shopifyCredential);
        $data = $service->toShopifyProductData($product);

        $this->assertNull($data['variants'][0]['compare_at_price']);
    }

    public function test_images_included_when_product_has_images(): void
    {
        $product = $this->createTestProduct([
            'featured_image' => 'https://example.com/image1.jpg',
            'gallery_images' => ['https://example.com/image2.jpg', 'https://example.com/image3.jpg'],
        ]);

        Http::fake();
        $service = new ShopifyProductService($this->shopifyCredential);
        $data = $service->toShopifyProductData($product);

        $this->assertArrayHasKey('images', $data);
        $this->assertCount(3, $data['images']);
        $this->assertEquals('https://example.com/image1.jpg', $data['images'][0]['src']);
    }

    public function test_images_omitted_when_product_has_no_images(): void
    {
        $product = $this->createTestProduct([
            'featured_image' => null,
            'gallery_images' => [],
        ]);

        Http::fake();
        $service = new ShopifyProductService($this->shopifyCredential);
        $data = $service->toShopifyProductData($product);

        $this->assertArrayNotHasKey('images', $data);
    }

    public function test_seo_metafields_mapped_when_present(): void
    {
        $product = $this->createTestProduct([
            'seo_title' => 'SEO Title Here',
            'seo_description' => 'SEO description here',
        ]);

        Http::fake();
        $service = new ShopifyProductService($this->shopifyCredential);
        $data = $service->toShopifyProductData($product);

        $this->assertEquals('SEO Title Here', $data['metafields_global_title_tag']);
        $this->assertEquals('SEO description here', $data['metafields_global_description_tag']);
    }

    public function test_inventory_management_null_when_manage_stock_false(): void
    {
        $product = $this->createTestProduct(['manage_stock' => false]);

        Http::fake();
        $service = new ShopifyProductService($this->shopifyCredential);
        $data = $service->toShopifyProductData($product);

        $this->assertNull($data['variants'][0]['inventory_management']);
    }

    public function test_vendor_and_product_type_from_relationships(): void
    {
        $brand = Brand::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'organization_id' => $this->shopifyOrganization->id,
        ]);

        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'organization_id' => $this->shopifyOrganization->id,
        ]);

        $product = $this->createTestProduct([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
        ]);

        Http::fake();
        $service = new ShopifyProductService($this->shopifyCredential);
        $data = $service->toShopifyProductData($product);

        $this->assertEquals('Acme Corp', $data['vendor']);
        $this->assertEquals('Electronics', $data['product_type']);
    }
}
