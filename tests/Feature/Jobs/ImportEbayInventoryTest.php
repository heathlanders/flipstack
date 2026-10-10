<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Ebay\ImportEbayInventory;
use App\Models\Catalog\Product;
use App\Models\Ebay\EbayListing;
use App\Models\Ebay\EbaySyncLog;
use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\BulkOperationJob;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportEbayInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected IntegrationCredential $credential;

    protected OAuthToken $token;

    protected BulkOperationJob $bulkJob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->team = Team::factory()->create(['organization_id' => $this->organization->id]);

        $this->credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => 'ebay',
            'environment' => 'production',
            'name' => 'eBay Production',
            'is_active' => true,
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);

        $this->token = OAuthToken::create([
            'organization_id' => $this->organization->id,
            'integration_credential_id' => $this->credential->id,
            'platform' => 'ebay',
            'environment' => 'production',
            'access_token' => 'test-access-token-value',
            'refresh_token' => 'test-refresh-token-value',
            'expires_at' => now()->addHours(2),
            'is_active' => true,
        ]);

        $this->bulkJob = BulkOperationJob::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'operation_type' => BulkOperationJob::TYPE_EBAY_IMPORT,
            'status' => BulkOperationJob::STATUS_PENDING,
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
            'parameters' => ['skip_existing_skus' => true],
        ]);
    }

    protected function buildXmlResponse(array $items = [], ?int $totalItems = null, int $totalPages = 1, int $currentPage = 1): string
    {
        $totalItems = $totalItems ?? count($items);
        $itemXml = '';
        foreach ($items as $item) {
            $picturesXml = '';
            foreach ($item['pictures'] ?? [] as $pic) {
                $picturesXml .= "<PictureURL>{$pic}</PictureURL>";
            }

            $specificsXml = '';
            foreach ($item['item_specifics'] ?? [] as $name => $value) {
                $specificsXml .= "<NameValueList><Name>{$name}</Name><Value>{$value}</Value></NameValueList>";
            }

            $itemXml .= "
            <Item>
                <ItemID>{$item['item_id']}</ItemID>
                <Title>".htmlspecialchars($item['title'] ?? 'Test Item').'</Title>
                <SKU>'.($item['sku'] ?? '').'</SKU>
                <Description>'.htmlspecialchars($item['description'] ?? '').'</Description>
                <StartPrice currencyID="'.($item['currency'] ?? 'USD').'">'.($item['start_price'] ?? '9.99').'</StartPrice>
                <Quantity>'.($item['quantity'] ?? 1).'</Quantity>
                <ConditionID>'.($item['condition_id'] ?? '3000').'</ConditionID>
                <ConditionDisplayName>'.($item['condition_display_name'] ?? 'Used').'</ConditionDisplayName>
                <ListingType>'.($item['listing_type'] ?? 'FixedPriceItem').'</ListingType>
                <ViewItemURL>'.($item['view_item_url'] ?? 'https://www.ebay.com/itm/'.$item['item_id']).'</ViewItemURL>
                <PrimaryCategory>
                    <CategoryID>'.($item['category_id'] ?? '12345').'</CategoryID>
                    <CategoryName>'.($item['category_name'] ?? 'Test Category').'</CategoryName>
                </PrimaryCategory>
                <SellingStatus>
                    <QuantitySold>'.($item['quantity_sold'] ?? 0)."</QuantitySold>
                </SellingStatus>
                <PictureDetails>{$picturesXml}</PictureDetails>
                <ItemSpecifics>{$specificsXml}</ItemSpecifics>
            </Item>";
        }

        return '<?xml version="1.0" encoding="utf-8"?>
        <GetSellerListResponse xmlns="urn:ebay:apis:eBLBaseComponents">
            <Ack>Success</Ack>
            <PaginationResult>
                <TotalNumberOfEntries>'.$totalItems.'</TotalNumberOfEntries>
                <TotalNumberOfPages>'.$totalPages.'</TotalNumberOfPages>
            </PaginationResult>
            <PageNumber>'.$currentPage.'</PageNumber>
            <ItemArray>'.$itemXml.'</ItemArray>
        </GetSellerListResponse>';
    }

    public function test_import_fetches_and_creates_products(): void
    {
        $items = [
            [
                'item_id' => '111222333444',
                'title' => 'Imported Widget',
                'sku' => 'WIDGET-001',
                'description' => 'A nice widget',
                'start_price' => '24.99',
                'quantity' => 5,
                'quantity_sold' => 1,
                'condition_id' => '1000',
                'condition_display_name' => 'New',
                'pictures' => ['https://i.ebayimg.com/pic1.jpg'],
                'item_specifics' => ['Brand' => 'WidgetCo'],
            ],
        ];

        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response($this->buildXmlResponse($items, 1, 1, 1), 200),
        ]);

        $job = new ImportEbayInventory(
            $this->credential->id,
            $this->organization->id,
            $this->user->id,
            $this->bulkJob->id,
            ['skip_existing_skus' => true]
        );
        $job->handle();

        $this->assertDatabaseHas('products', [
            'organization_id' => $this->organization->id,
            'name' => 'Imported Widget',
            'sku' => 'WIDGET-001',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('ebay_listings', [
            'ebay_listing_id' => '111222333444',
            'integration_credential_id' => $this->credential->id,
        ]);
    }

    public function test_import_skips_existing_sku(): void
    {
        Product::factory()->create([
            'organization_id' => $this->organization->id,
            'sku' => 'EXISTING-SKU',
        ]);

        $items = [
            [
                'item_id' => '555666777',
                'title' => 'Duplicate SKU Item',
                'sku' => 'EXISTING-SKU',
                'start_price' => '10.00',
                'quantity' => 1,
                'quantity_sold' => 0,
                'pictures' => [],
                'item_specifics' => [],
            ],
        ];

        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response($this->buildXmlResponse($items, 1, 1, 1), 200),
        ]);

        $job = new ImportEbayInventory(
            $this->credential->id,
            $this->organization->id,
            $this->user->id,
            $this->bulkJob->id,
            ['skip_existing_skus' => true]
        );
        $job->handle();

        // Should not create a second product with same SKU
        $this->assertEquals(1, Product::where('sku', 'EXISTING-SKU')->count());
    }

    public function test_import_skips_existing_ebay_listing(): void
    {
        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'sku' => 'ALREADY-LISTED',
        ]);

        EbayListing::create([
            'product_id' => $product->id,
            'integration_credential_id' => $this->credential->id,
            'team_id' => $this->team->id,
            'ebay_listing_id' => '888999000',
            'ebay_sku' => 'ALREADY-LISTED',
            'status' => 'published',
        ]);

        $items = [
            [
                'item_id' => '888999000',
                'title' => 'Already Listed Item',
                'sku' => 'NEW-SKU-FOR-EXISTING',
                'start_price' => '15.00',
                'quantity' => 2,
                'quantity_sold' => 0,
                'pictures' => [],
                'item_specifics' => [],
            ],
        ];

        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response($this->buildXmlResponse($items, 1, 1, 1), 200),
        ]);

        $job = new ImportEbayInventory(
            $this->credential->id,
            $this->organization->id,
            $this->user->id,
            $this->bulkJob->id,
            ['skip_existing_skus' => true]
        );
        $job->handle();

        // Should not create a new listing with same eBay item ID
        $this->assertEquals(1, EbayListing::where('ebay_listing_id', '888999000')->count());
    }

    public function test_import_creates_product_identifiers(): void
    {
        $items = [
            [
                'item_id' => '444555666',
                'title' => 'Item With Identifiers',
                'sku' => 'ID-ITEM-001',
                'start_price' => '19.99',
                'quantity' => 3,
                'quantity_sold' => 0,
                'pictures' => [],
                'item_specifics' => [
                    'UPC' => '012345678901',
                    'MPN' => 'MODEL-X100',
                    'Brand' => 'TestBrand',
                ],
            ],
        ];

        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response($this->buildXmlResponse($items, 1, 1, 1), 200),
        ]);

        $job = new ImportEbayInventory(
            $this->credential->id,
            $this->organization->id,
            $this->user->id,
            $this->bulkJob->id,
            ['skip_existing_skus' => true]
        );
        $job->handle();

        $product = Product::where('sku', 'ID-ITEM-001')->first();
        $this->assertNotNull($product);

        $this->assertDatabaseHas('product_identifiers', [
            'product_id' => $product->id,
            'type' => 'upc',
            'value' => '012345678901',
            'source' => 'ebay_import',
        ]);

        $this->assertDatabaseHas('product_identifiers', [
            'product_id' => $product->id,
            'type' => 'mpn',
            'value' => 'MODEL-X100',
        ]);
    }

    public function test_import_updates_bulk_operation_progress(): void
    {
        $items = [
            ['item_id' => '100', 'title' => 'Item 1', 'sku' => 'SKU-1', 'start_price' => '10', 'quantity' => 1, 'quantity_sold' => 0, 'pictures' => [], 'item_specifics' => []],
            ['item_id' => '200', 'title' => 'Item 2', 'sku' => 'SKU-2', 'start_price' => '20', 'quantity' => 1, 'quantity_sold' => 0, 'pictures' => [], 'item_specifics' => []],
        ];

        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response($this->buildXmlResponse($items, 2, 1, 1), 200),
        ]);

        $job = new ImportEbayInventory(
            $this->credential->id,
            $this->organization->id,
            $this->user->id,
            $this->bulkJob->id,
            ['skip_existing_skus' => true]
        );
        $job->handle();

        $this->bulkJob->refresh();
        $this->assertEquals(2, $this->bulkJob->processed_items);
        $this->assertEquals(BulkOperationJob::STATUS_COMPLETED, $this->bulkJob->status);
    }

    public function test_import_handles_api_error_gracefully(): void
    {
        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response('Internal Server Error', 500),
        ]);

        $job = new ImportEbayInventory(
            $this->credential->id,
            $this->organization->id,
            $this->user->id,
            $this->bulkJob->id,
            ['skip_existing_skus' => true]
        );
        $job->handle();

        $this->bulkJob->refresh();
        $this->assertEquals(BulkOperationJob::STATUS_FAILED, $this->bulkJob->status);
    }

    public function test_import_respects_cancellation(): void
    {
        $page1Items = [
            ['item_id' => '001', 'title' => 'Page 1 Item', 'sku' => 'P1-SKU', 'start_price' => '10', 'quantity' => 1, 'quantity_sold' => 0, 'pictures' => [], 'item_specifics' => []],
        ];
        $page2Items = [
            ['item_id' => '002', 'title' => 'Page 2 Item', 'sku' => 'P2-SKU', 'start_price' => '20', 'quantity' => 1, 'quantity_sold' => 0, 'pictures' => [], 'item_specifics' => []],
        ];
        $page3Items = [
            ['item_id' => '003', 'title' => 'Page 3 Item', 'sku' => 'P3-SKU', 'start_price' => '30', 'quantity' => 1, 'quantity_sold' => 0, 'pictures' => [], 'item_specifics' => []],
        ];

        $bulkJobId = $this->bulkJob->id;
        $callCount = 0;

        Http::fake(function ($request) use (&$callCount, $page1Items, $page2Items, $page3Items, $bulkJobId) {
            $callCount++;
            if ($callCount === 1) {
                return Http::response($this->buildXmlResponse($page1Items, 3, 3, 1), 200);
            }
            if ($callCount === 2) {
                // Set cancelled during page 2 fetch. The cancel check already passed
                // for this iteration, so page 2 items still get processed. But iteration
                // 3 will refresh and see cancelled before page 3 is fetched.
                BulkOperationJob::where('id', $bulkJobId)
                    ->update(['status' => BulkOperationJob::STATUS_CANCELLED]);

                return Http::response($this->buildXmlResponse($page2Items, 3, 3, 2), 200);
            }

            return Http::response($this->buildXmlResponse($page3Items, 3, 3, 3), 200);
        });

        $job = new ImportEbayInventory(
            $this->credential->id,
            $this->organization->id,
            $this->user->id,
            $this->bulkJob->id,
            ['skip_existing_skus' => true]
        );
        $job->handle();

        // Pages 1 and 2 should be processed, but page 3 should be skipped
        $this->assertDatabaseHas('products', ['sku' => 'P1-SKU']);
        $this->assertDatabaseHas('products', ['sku' => 'P2-SKU']);
        $this->assertDatabaseMissing('products', ['sku' => 'P3-SKU']);
    }

    public function test_import_creates_products_as_draft(): void
    {
        $items = [
            ['item_id' => '777', 'title' => 'Draft Item', 'sku' => 'DRAFT-001', 'start_price' => '5.99', 'quantity' => 1, 'quantity_sold' => 0, 'pictures' => [], 'item_specifics' => []],
        ];

        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response($this->buildXmlResponse($items, 1, 1, 1), 200),
        ]);

        $job = new ImportEbayInventory(
            $this->credential->id,
            $this->organization->id,
            $this->user->id,
            $this->bulkJob->id,
            ['skip_existing_skus' => true]
        );
        $job->handle();

        $product = Product::where('sku', 'DRAFT-001')->first();
        $this->assertNotNull($product);
        $this->assertEquals('draft', $product->status);
    }

    public function test_import_logs_to_ebay_sync_log(): void
    {
        $items = [
            ['item_id' => '999', 'title' => 'Logged Item', 'sku' => 'LOG-001', 'start_price' => '12.00', 'quantity' => 1, 'quantity_sold' => 0, 'pictures' => [], 'item_specifics' => []],
        ];

        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response($this->buildXmlResponse($items, 1, 1, 1), 200),
        ]);

        $job = new ImportEbayInventory(
            $this->credential->id,
            $this->organization->id,
            $this->user->id,
            $this->bulkJob->id,
            ['skip_existing_skus' => true]
        );
        $job->handle();

        $this->assertDatabaseHas('ebay_sync_logs', [
            'integration_credential_id' => $this->credential->id,
            'operation' => EbaySyncLog::OP_IMPORT_INVENTORY,
            'direction' => EbaySyncLog::DIRECTION_INBOUND,
            'status' => EbaySyncLog::STATUS_SUCCESS,
            'ebay_item_id' => '999',
        ]);
    }

    public function test_import_paginates_through_multiple_pages(): void
    {
        $page1Items = [
            ['item_id' => 'P1-001', 'title' => 'Page 1 Item', 'sku' => 'P1-SKU', 'start_price' => '10', 'quantity' => 1, 'quantity_sold' => 0, 'pictures' => [], 'item_specifics' => []],
        ];
        $page2Items = [
            ['item_id' => 'P2-001', 'title' => 'Page 2 Item', 'sku' => 'P2-SKU', 'start_price' => '20', 'quantity' => 1, 'quantity_sold' => 0, 'pictures' => [], 'item_specifics' => []],
        ];

        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::sequence()
                ->push($this->buildXmlResponse($page1Items, 2, 2, 1), 200)
                ->push($this->buildXmlResponse($page2Items, 2, 2, 2), 200),
        ]);

        $job = new ImportEbayInventory(
            $this->credential->id,
            $this->organization->id,
            $this->user->id,
            $this->bulkJob->id,
            ['skip_existing_skus' => true]
        );
        $job->handle();

        $this->assertDatabaseHas('products', ['sku' => 'P1-SKU']);
        $this->assertDatabaseHas('products', ['sku' => 'P2-SKU']);
        $this->assertEquals(2, Product::where('organization_id', $this->organization->id)->count());
    }
}
