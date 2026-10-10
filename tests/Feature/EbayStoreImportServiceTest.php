<?php

namespace Tests\Feature;

use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\SupplierProduct;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\User\User;
use App\Services\EbayStoreImportService;
use App\Services\SkuParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EbayStoreImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected User $user;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();
        $this->team = Team::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    protected function skuParserConfig(): array
    {
        return [
            'enabled' => true,
            'mode' => 'regex',
            'regex' => [
                'pattern' => '/^(?P<owner>\S+)\s+(?P<sku>\S+)\s+(?P<location>.+)$/',
            ],
        ];
    }

    protected function makeCredential(?array $skuParserConfig = null): IntegrationCredential
    {
        return IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => 'ebay',
            'environment' => 'sandbox',
            'name' => 'Test eBay',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'is_active' => true,
            'config' => $skuParserConfig ? ['sku_parser' => $skuParserConfig] : [],
        ]);
    }

    protected function makeEbayItem(array $overrides = []): array
    {
        return array_merge([
            'item_id' => '123456789',
            'title' => 'Test Product Title',
            'sku' => 'ARC IND-18979-9 SHELF A1',
            'start_price' => 29.99,
            'currency' => 'USD',
            'available_quantity' => 5,
            'quantity_sold' => 0,
            'condition_id' => '1000',
            'condition_display_name' => 'New',
            'category_id' => '12345',
            'category_name' => 'Test Category',
            'listing_type' => 'FixedPriceItem',
            'view_item_url' => 'https://www.ebay.com/itm/123456789',
            'description' => 'A test product',
            'pictures' => ['https://example.com/img1.jpg'],
            'item_specifics' => [],
        ], $overrides);
    }

    protected function makeService(?array $skuParserConfig = null): EbayStoreImportService
    {
        $credential = $this->makeCredential($skuParserConfig);

        return new EbayStoreImportService($credential);
    }

    // -- Test 1: SKU parser produces correct owner/sku/location --

    public function test_sku_parser_extracts_owner_sku_and_location(): void
    {
        $parser = new SkuParserService;
        $config = $this->skuParserConfig();

        $result = $parser->parse('ARC IND-18979-9 SHELF A1', $config);

        $this->assertNotNull($result);
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('IND-18979-9', $result['sku']);
        $this->assertEquals('SHELF A1', $result['location']);
    }

    public function test_sku_parser_with_template_delimiter_mode(): void
    {
        $parser = new SkuParserService;
        $config = [
            'enabled' => true,
            'mode' => 'template',
            'template' => [
                'type' => 'delimiter',
                'delimiter' => ' ',
                'segments' => [
                    ['field' => 'owner', 'position' => 0],
                    ['field' => 'sku', 'position' => 1],
                    ['field' => 'location', 'position' => 2],
                ],
            ],
        ];

        $result = $parser->parse('ARC IND-18979-9 SHELF', $config);

        $this->assertNotNull($result);
        $this->assertEquals('ARC', $result['owner']);
        $this->assertEquals('IND-18979-9', $result['sku']);
        $this->assertEquals('SHELF', $result['location']);
    }

    public function test_sku_parser_returns_null_when_disabled(): void
    {
        $parser = new SkuParserService;
        $config = ['enabled' => false];

        $this->assertNull($parser->parse('ARC IND-18979-9 SHELF A1', $config));
    }

    // -- Test 2: createProductFromEbayItem creates Product with clean SKU --

    public function test_create_product_stores_parsed_sku(): void
    {
        $service = $this->makeService($this->skuParserConfig());
        $item = $this->makeEbayItem();

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);

        $this->assertNotNull($product);
        $this->assertEquals('IND-18979-9', $product->sku);
    }

    // -- Test 3: Supplier is created and linked via SupplierProduct --

    public function test_supplier_created_and_linked_from_parsed_owner(): void
    {
        $service = $this->makeService($this->skuParserConfig());
        $item = $this->makeEbayItem();

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);

        $this->assertDatabaseHas('suppliers', [
            'code' => 'ARC',
            'organization_id' => $this->organization->id,
        ]);

        $supplier = Supplier::where('code', 'ARC')
            ->where('organization_id', $this->organization->id)
            ->first();

        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_sku' => 'ARC IND-18979-9 SHELF A1',
        ]);
    }

    // -- Test 4: InventoryLocation created and InventoryLevel linked --

    public function test_inventory_location_created_and_level_linked(): void
    {
        $service = $this->makeService($this->skuParserConfig());
        $item = $this->makeEbayItem();

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);

        // Structured parser creates a WAREHOUSE location (not flat 'SHELF A1')
        $this->assertDatabaseHas('inventory_locations', [
            'code' => 'WAREHOUSE',
            'organization_id' => $this->organization->id,
        ]);

        $location = InventoryLocation::where('code', 'WAREHOUSE')
            ->where('organization_id', $this->organization->id)
            ->first();

        $this->assertDatabaseHas('inventory_levels', [
            'product_id' => $product->id,
            'location_id' => $location->id,
            'organization_id' => $this->organization->id,
            'quantity_on_hand' => 5,
        ]);

        // Shelf data stored in product attributes
        $product->refresh();
        $this->assertEquals('A1', $product->attributes['warehouse_shelf'] ?? null);
    }

    // -- Test 5: Duplicate import reuses existing supplier/location --

    public function test_duplicate_import_reuses_existing_supplier_and_location(): void
    {
        $service = $this->makeService($this->skuParserConfig());

        $item1 = $this->makeEbayItem(['item_id' => '111', 'sku' => 'ARC SKU-001 SHELF A1']);
        $item2 = $this->makeEbayItem(['item_id' => '222', 'sku' => 'ARC SKU-002 SHELF A1']);

        $product1 = $service->createProductFromEbayItem($item1, $this->organization->id, $this->user->id);
        $product2 = $service->createProductFromEbayItem($item2, $this->organization->id, $this->user->id);

        $this->assertEquals(1, Supplier::where('code', 'ARC')->where('organization_id', $this->organization->id)->count());
        // Structured parser creates a single WAREHOUSE location (not flat 'SHELF A1')
        $this->assertEquals(1, InventoryLocation::where('code', 'WAREHOUSE')->where('organization_id', $this->organization->id)->count());

        $supplier = Supplier::where('code', 'ARC')->where('organization_id', $this->organization->id)->first();
        $location = InventoryLocation::where('code', 'WAREHOUSE')->where('organization_id', $this->organization->id)->first();

        $this->assertEquals(2, SupplierProduct::where('supplier_id', $supplier->id)->count());
        $this->assertEquals(2, InventoryLevel::where('location_id', $location->id)->count());
    }

    // -- Test 6: Missing owner/location gracefully skipped --

    public function test_missing_owner_and_location_skips_supplier_and_inventory(): void
    {
        $service = $this->makeService($this->skuParserConfig());
        // SKU that does not match the regex pattern (no spaces = no groups)
        $item = $this->makeEbayItem(['sku' => 'SIMPLE-SKU']);

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);

        $this->assertNotNull($product);
        $this->assertDatabaseMissing('supplier_products', [
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseMissing('inventory_levels', [
            'product_id' => $product->id,
        ]);
    }

    public function test_no_sku_parser_config_uses_location_parser(): void
    {
        $service = $this->makeService();
        $item = $this->makeEbayItem(['sku' => 'ARC IND-18979-9 SHELF A1']);

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);

        $this->assertNotNull($product);
        // Location parser extracts product_sku from ARC pattern even without config parser
        $this->assertEquals('IND-18979-9', $product->sku);
        // Location parser finds owner=ARC, so supplier and inventory are created
        $this->assertDatabaseHas('suppliers', ['code' => 'ARC', 'organization_id' => $this->organization->id]);
        $this->assertDatabaseHas('supplier_products', ['product_id' => $product->id]);
        $this->assertDatabaseHas('inventory_levels', ['product_id' => $product->id]);
    }

    // -- Test: Completeness score calculated on product creation --

    public function test_completeness_score_calculated_on_create(): void
    {
        $service = $this->makeService($this->skuParserConfig());
        $item = $this->makeEbayItem([
            'item_specifics' => [
                'Brand' => 'TestBrand',
                'UPC' => '012345678901',
            ],
        ]);

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);

        $this->assertNotNull($product->attributes['completeness_score'] ?? null);
        $score = $product->attributes['completeness_score'];
        $this->assertIsInt($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(10, $score);
        // Has: image(1), description(1), condition(1), category(1), UPC(1), brand(1), supplier(1), location(1) = 8
        // Missing: weight(0), dimensions(0) = 0
        $this->assertEquals(8, $score);
    }

    public function test_completeness_score_updated_on_product_update(): void
    {
        $service = $this->makeService($this->skuParserConfig());
        $item = $this->makeEbayItem();

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);
        $initialScore = $product->attributes['completeness_score'] ?? 0;

        // Update with more data (add weight)
        $updatedItem = $this->makeEbayItem([
            'item_specifics' => [
                'Brand' => 'TestBrand',
                'UPC' => '012345678901',
                'Item Weight' => '2.5 lbs',
            ],
        ]);

        $product = $service->updateProductFromEbayItem($product, $updatedItem, $this->user->id);
        $updatedScore = $product->attributes['completeness_score'] ?? 0;

        $this->assertGreaterThanOrEqual($initialScore, $updatedScore);
    }

    // -- House-stock prefix: skip Supplier + SupplierProduct creation --

    protected function makeServiceWithHousePrefixes(array $housePrefixes): EbayStoreImportService
    {
        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => 'ebay',
            'environment' => 'sandbox',
            'name' => 'Test eBay House',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'is_active' => true,
            'config' => ['house_prefixes' => $housePrefixes],
        ]);

        return new EbayStoreImportService($credential);
    }

    public function test_house_prefix_skips_supplier_creation_and_flags_product(): void
    {
        $service = $this->makeServiceWithHousePrefixes(['HAWK']);
        $item = $this->makeEbayItem([
            'sku' => 'HAWK RM 132 SHELF M GROUND LVL',
        ]);

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);

        // Product attributes flag house stock
        $this->assertTrue($product->attributes['is_house_stock'] ?? false);

        // No Supplier row was created for HAWK
        $this->assertDatabaseMissing('suppliers', [
            'code' => 'HAWK',
            'organization_id' => $this->organization->id,
        ]);

        // No SupplierProduct link
        $this->assertDatabaseMissing('supplier_products', [
            'product_id' => $product->id,
        ]);
    }

    public function test_house_prefix_match_is_case_insensitive(): void
    {
        $service = $this->makeServiceWithHousePrefixes(['hawk']);
        $item = $this->makeEbayItem([
            'sku' => 'HAWK RM 132 SHELF M',
        ]);

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);
        $this->assertTrue($product->attributes['is_house_stock'] ?? false);
        $this->assertDatabaseMissing('suppliers', ['code' => 'HAWK', 'organization_id' => $this->organization->id]);
    }

    public function test_non_house_prefix_still_creates_supplier(): void
    {
        $service = $this->makeServiceWithHousePrefixes(['HAWK']);
        $item = $this->makeEbayItem([
            'sku' => 'PINE RM 134 SHELF B3',
        ]);

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);

        $this->assertFalse($product->attributes['is_house_stock'] ?? false);
        $this->assertDatabaseHas('suppliers', [
            'code' => 'PINE',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_arc_pending_skips_consignor_supplier(): void
    {
        $service = $this->makeServiceWithHousePrefixes([]);
        $item = $this->makeEbayItem([
            'sku' => 'ARC ID PENDING RM 132 Shelf 7',
        ]);

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);

        $this->assertSame('pending', $product->attributes['intake_status'] ?? null);
        // ARC owner-supplier IS created (ARC is the marketplace owner; not house stock).
        $this->assertDatabaseHas('suppliers', [
            'code' => 'ARC',
            'organization_id' => $this->organization->id,
        ]);
        // But no consignor supplier — "ID" and "PO" should never become consignors.
        $this->assertDatabaseMissing('suppliers', [
            'code' => 'ID',
            'organization_id' => $this->organization->id,
        ]);
        $this->assertDatabaseMissing('suppliers', [
            'code' => 'PO',
            'organization_id' => $this->organization->id,
        ]);
    }

    // -- Gap #2: known_listers config routes initials to lister_initials, no Supplier --

    protected function makeServiceWithListers(array $knownListers, array $housePrefixes = []): EbayStoreImportService
    {
        $config = ['known_listers' => $knownListers];
        if ($housePrefixes !== []) {
            $config['house_prefixes'] = $housePrefixes;
        }

        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => 'ebay',
            'environment' => 'sandbox',
            'name' => 'Test eBay Listers',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'is_active' => true,
            'config' => $config,
        ]);

        return new EbayStoreImportService($credential);
    }

    public function test_known_lister_does_not_create_supplier_and_writes_attribute(): void
    {
        $service = $this->makeServiceWithListers(['ABC', 'DEF', 'GHI']);
        $item = $this->makeEbayItem([
            'sku' => 'HAWK ABC SHELF 28 LVL 3',
        ]);

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);

        $this->assertEquals('ABC', $product->attributes['lister_initials'] ?? null);
        // HAWK (owner) is still a Supplier.
        $this->assertDatabaseHas('suppliers', [
            'code' => 'HAWK',
            'organization_id' => $this->organization->id,
        ]);
        // ABC must NOT spawn a Supplier row.
        $this->assertDatabaseMissing('suppliers', [
            'code' => 'ABC',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_unknown_lister_token_still_falls_back_to_consignor_supplier(): void
    {
        $service = $this->makeServiceWithListers(['ABC']);
        $item = $this->makeEbayItem([
            'sku' => 'HAWK COOP 58585 SHELF 29 TUB ELTONJOHN',
        ]);

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);

        $this->assertNull($product->attributes['lister_initials'] ?? null);
        $this->assertEquals('COOP', $product->attributes['consignor_code'] ?? null);
        $this->assertEquals('58585', $product->attributes['consignor_item_id'] ?? null);
        $this->assertDatabaseHas('suppliers', [
            'code' => 'COOP',
            'organization_id' => $this->organization->id,
        ]);
    }

    // -- Gap #3: bracket prefix captured into Product.attributes --

    public function test_bracket_prefix_persisted_to_product_attributes(): void
    {
        $service = $this->makeServiceWithListers([]);
        $item = $this->makeEbayItem([
            'sku' => '(C) PINE Tub Morrigan Shelf B3 21655',
        ]);

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);
        $product->refresh();

        $this->assertEquals('C', $product->attributes['bracket_prefix'] ?? null);
        $this->assertDatabaseHas('suppliers', [
            'code' => 'PINE',
            'organization_id' => $this->organization->id,
        ]);
        $this->assertEquals('MORRIGAN', $product->attributes['warehouse_container_name'] ?? null);
    }

    // -- Gap #6: lowercase owner now matched --

    public function test_lowercase_owner_is_matched_and_creates_supplier(): void
    {
        $service = $this->makeServiceWithListers(['ABC']);
        $item = $this->makeEbayItem([
            'sku' => 'arc abc PO-1043-1020-101 FOH',
        ]);

        $product = $service->createProductFromEbayItem($item, $this->organization->id, $this->user->id);

        $this->assertDatabaseHas('suppliers', [
            'code' => 'ARC',
            'organization_id' => $this->organization->id,
        ]);
        $this->assertEquals('ABC', $product->attributes['lister_initials'] ?? null);
        $this->assertDatabaseMissing('suppliers', [
            'code' => 'ABC',
            'organization_id' => $this->organization->id,
        ]);
    }
}
