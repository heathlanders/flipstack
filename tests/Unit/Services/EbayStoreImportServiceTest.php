<?php

namespace Tests\Unit\Services;

use App\Models\Catalog\Product;
use App\Models\Ebay\EbayListing;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Services\EbayStoreImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EbayStoreImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Team $team;

    protected IntegrationCredential $credential;

    protected EbayStoreImportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->team = Team::factory()->create(['organization_id' => $this->organization->id]);

        $this->credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => 'ebay',
            'environment' => 'production',
            'name' => 'Test eBay',
            'is_active' => true,
            'client_id' => 'test-id',
            'client_secret' => 'test-secret',
        ]);

        $this->service = new EbayStoreImportService($this->credential);
    }

    protected function sampleEbayItem(array $overrides = []): array
    {
        return array_merge([
            'item_id' => '123456789012',
            'title' => 'Test Product Title',
            'sku' => 'TEST-SKU-001',
            'description' => '<p>A great product</p>',
            'start_price' => 29.99,
            'currency' => 'USD',
            'quantity' => 10,
            'quantity_sold' => 3,
            'available_quantity' => 7,
            'condition_id' => '1000',
            'condition_display_name' => 'New',
            'category_id' => '12345',
            'category_name' => 'Electronics > Phones',
            'listing_type' => 'FixedPriceItem',
            'view_item_url' => 'https://www.ebay.com/itm/123456789012',
            'pictures' => [
                'https://i.ebayimg.com/images/g/main.jpg',
                'https://i.ebayimg.com/images/g/second.jpg',
                'https://i.ebayimg.com/images/g/third.jpg',
            ],
            'item_specifics' => [
                'Brand' => 'TestBrand',
                'UPC' => '012345678901',
                'MPN' => 'TB-100',
            ],
            'start_time' => '2026-01-15T10:00:00.000Z',
            'end_time' => '2026-07-15T10:00:00.000Z',
            'hit_count' => 142,
            'watch_count' => 8,
            'shipping_details' => [
                'shipping_type' => 'Flat',
                'handling_time' => 1,
                'services' => [
                    ['service' => 'USPSPriority', 'cost' => 5.99, 'additional_cost' => 0, 'free_shipping' => false],
                ],
            ],
            'return_policy' => [
                'returns_accepted' => 'ReturnsAccepted',
                'returns_within' => 'Days_30',
                'refund_option' => 'MoneyBack',
                'shipping_cost_paid_by' => 'Buyer',
                'description' => '',
            ],
            'best_offer' => [
                'enabled' => true,
                'auto_accept_price' => 25.00,
                'minimum_accept_price' => 20.00,
            ],
            'seller_profiles' => [
                'shipping_profile_id' => '12345',
                'shipping_profile_name' => 'Standard',
                'return_profile_id' => '67890',
                'return_profile_name' => '30 Day',
                'payment_profile_id' => '11111',
                'payment_profile_name' => 'Default',
            ],
            'store_category_id' => '999',
            'store_category_name' => 'Electronics',
            'secondary_category_id' => '54321',
            'secondary_category_name' => 'Cell Phones',
            'listing_duration' => 'GTC',
            'epid' => '12345678',
        ], $overrides);
    }

    public function test_maps_ebay_item_to_product_data(): void
    {
        $item = $this->sampleEbayItem();
        $result = $this->service->mapToProductData($item);

        $this->assertEquals('Test Product Title', $result['name']);
        $this->assertEquals('<p>A great product</p>', $result['description']);
        $this->assertEquals('TEST-SKU-001', $result['sku']);
        $this->assertEquals(29.99, $result['selling_price']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals(7, $result['stock_quantity']);
        $this->assertEquals('NEW', $result['condition']);
        $this->assertEquals('draft', $result['status']);
        $this->assertEquals('https://i.ebayimg.com/images/g/main.jpg', $result['featured_image']);
        $this->assertEquals('Electronics > Phones', $result['attributes']['ebay_category']);
        $this->assertEquals(142, $result['view_count']);
    }

    public function test_maps_phase4_fields_to_attributes(): void
    {
        $item = $this->sampleEbayItem();
        $result = $this->service->mapToProductData($item);

        $attrs = $result['attributes'];
        $this->assertIsArray($attrs['shipping_details']);
        $this->assertEquals('Flat', $attrs['shipping_details']['shipping_type']);
        $this->assertIsArray($attrs['return_policy']);
        $this->assertEquals('ReturnsAccepted', $attrs['return_policy']['returns_accepted']);
        $this->assertIsArray($attrs['best_offer']);
        $this->assertTrue($attrs['best_offer']['enabled']);
        $this->assertEquals(8, $attrs['watch_count']);
        $this->assertEquals('999', $attrs['store_category_id']);
        $this->assertEquals('Electronics', $attrs['store_category_name']);
        $this->assertEquals('54321', $attrs['ebay_secondary_category_id']);
        $this->assertEquals('Cell Phones', $attrs['ebay_secondary_category_name']);
        $this->assertEquals('GTC', $attrs['listing_duration']);
        $this->assertEquals('12345678', $attrs['ebay_epid']);
        $this->assertEquals('2026-07-15T10:00:00.000Z', $result['end_time']);
        $this->assertEquals('2026-01-15T10:00:00.000Z', $result['start_time']);
    }

    public function test_maps_condition_id_to_condition_string(): void
    {
        $this->assertEquals('NEW', $this->service->mapCondition('1000'));
        $this->assertEquals('NEW_OTHER', $this->service->mapCondition('1500'));
        $this->assertEquals('CERTIFIED_REFURBISHED', $this->service->mapCondition('2000'));
        $this->assertEquals('USED_VERY_GOOD', $this->service->mapCondition('3000'));
        $this->assertEquals('USED_GOOD', $this->service->mapCondition('4000'));
        $this->assertEquals('USED_ACCEPTABLE', $this->service->mapCondition('5000'));
        $this->assertEquals('USED_ACCEPTABLE', $this->service->mapCondition('6000'));
        $this->assertEquals('FOR_PARTS_OR_NOT_WORKING', $this->service->mapCondition('7000'));
        $this->assertEquals('USED_GOOD', $this->service->mapCondition('9999')); // Unknown defaults to USED_GOOD
    }

    public function test_extracts_identifiers_from_item_specifics(): void
    {
        $specifics = [
            'Brand' => 'Samsung',
            'UPC' => '887276543210',
            'MPN' => 'SM-G950U',
            'Color' => 'Black',
        ];

        $result = $this->service->extractIdentifiers($specifics);

        $this->assertEquals('887276543210', $result['upc']);
        $this->assertEquals('SM-G950U', $result['mpn']);
        $this->assertEquals('Samsung', $result['brand']);
    }

    public function test_uses_item_id_as_sku_when_sku_missing(): void
    {
        $item = $this->sampleEbayItem(['sku' => '']);
        $result = $this->service->mapToProductData($item);

        $this->assertEquals('EBAY-123456789012', $result['sku']);
    }

    public function test_calculates_available_quantity(): void
    {
        $item = $this->sampleEbayItem([
            'quantity' => 20,
            'quantity_sold' => 8,
            'available_quantity' => 12,
        ]);
        $result = $this->service->mapToProductData($item);

        $this->assertEquals(12, $result['stock_quantity']);
    }

    public function test_extracts_multiple_image_urls(): void
    {
        $item = $this->sampleEbayItem([
            'pictures' => [
                'https://example.com/1.jpg',
                'https://example.com/2.jpg',
                'https://example.com/3.jpg',
            ],
        ]);
        $result = $this->service->mapToProductData($item);

        $this->assertEquals('https://example.com/1.jpg', $result['featured_image']);
        $this->assertEquals(['https://example.com/1.jpg', 'https://example.com/2.jpg', 'https://example.com/3.jpg'], $result['gallery_images']);
    }

    public function test_handles_missing_optional_fields(): void
    {
        $item = [
            'item_id' => '999888777',
            'title' => 'Minimal Item',
            'sku' => '',
            'description' => '',
            'start_price' => 5.00,
            'currency' => 'USD',
            'quantity' => 1,
            'quantity_sold' => 0,
            'available_quantity' => 1,
            'condition_id' => '',
            'condition_display_name' => '',
            'category_id' => '',
            'category_name' => '',
            'listing_type' => '',
            'view_item_url' => '',
            'pictures' => [],
            'item_specifics' => [],
        ];

        $result = $this->service->mapToProductData($item);

        $this->assertEquals('Minimal Item', $result['name']);
        $this->assertNull($result['description']);
        $this->assertEquals('EBAY-999888777', $result['sku']);
        $this->assertNull($result['featured_image']);
        $this->assertNull($result['gallery_images']);
        $this->assertEquals('USED_GOOD', $result['condition']);
    }

    public function test_product_exists_by_sku_checks_organization_scope(): void
    {
        $otherOrg = Organization::factory()->create();

        Product::factory()->create([
            'organization_id' => $otherOrg->id,
            'sku' => 'SHARED-SKU',
        ]);

        $this->assertFalse($this->service->productExistsBySku($this->organization->id, 'SHARED-SKU'));

        Product::factory()->create([
            'organization_id' => $this->organization->id,
            'sku' => 'SHARED-SKU',
        ]);

        $this->assertTrue($this->service->productExistsBySku($this->organization->id, 'SHARED-SKU'));
    }

    public function test_ebay_listing_exists_by_item_id(): void
    {
        $this->assertFalse($this->service->ebayListingExistsByItemId($this->credential->id, '111222333'));

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'sku' => 'LISTING-TEST',
        ]);

        EbayListing::create([
            'product_id' => $product->id,
            'integration_credential_id' => $this->credential->id,
            'team_id' => $this->team->id,
            'ebay_listing_id' => '111222333',
            'ebay_sku' => 'TEST',
            'status' => 'published',
        ]);

        $this->assertTrue($this->service->ebayListingExistsByItemId($this->credential->id, '111222333'));
    }

    public function test_ignores_does_not_apply_identifiers(): void
    {
        $specifics = [
            'Brand' => 'Does not apply',
            'UPC' => 'Does not apply',
            'MPN' => 'N/A',
        ];

        $result = $this->service->extractIdentifiers($specifics);

        $this->assertArrayNotHasKey('upc', $result);
        $this->assertArrayNotHasKey('mpn', $result);
        $this->assertArrayNotHasKey('brand', $result);
    }

    public function test_extracts_ean_separately(): void
    {
        $specifics = [
            'EAN' => '4901234567890',
        ];

        $result = $this->service->extractIdentifiers($specifics);

        $this->assertArrayNotHasKey('upc', $result);
        $this->assertEquals('4901234567890', $result['ean']);
    }

    public function test_extracts_upc_and_ean_independently(): void
    {
        $specifics = [
            'UPC' => '012345678901',
            'EAN' => '4901234567890',
        ];

        $result = $this->service->extractIdentifiers($specifics);

        $this->assertEquals('012345678901', $result['upc']);
        $this->assertEquals('4901234567890', $result['ean']);
    }
}
