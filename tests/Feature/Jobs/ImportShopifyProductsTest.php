<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Shopify\ImportShopifyProducts;
use App\Models\Catalog\Product;
use App\Models\Inventory\LabelingWorkOrder;
use App\Models\Organization\OrganizationSettings;
use App\Models\Shopify\ShopifyListing;
use App\Models\System\BulkOperationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesShopifyFixtures;

class ImportShopifyProductsTest extends TestCase
{
    use CreatesShopifyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopifyFixtures();
        $this->mockShopifyRateLimiter();
    }

    public function test_import_creates_products_and_listings(): void
    {
        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(self::shopifyProductListResponse(2)),
        ]);

        $bulkJob = BulkOperationJob::create([
            'organization_id' => $this->shopifyOrganization->id,
            'user_id' => $this->shopifyUser->id,
            'operation_type' => 'shopify_import',
            'status' => 'pending',
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        $job = new ImportShopifyProducts(
            $this->shopifyCredential->id,
            $this->shopifyOrganization->id,
            $this->shopifyUser->id,
            $bulkJob->id
        );
        $job->handle();

        $bulkJob->refresh();
        $this->assertEquals('completed', $bulkJob->status);
        $this->assertEquals(2, $bulkJob->total_items);
        $this->assertEquals(2, $bulkJob->parameters['imported']);

        $this->assertEquals(2, Product::where('organization_id', $this->shopifyOrganization->id)
            ->where('sku', 'like', 'PROD-%')
            ->count());
        $this->assertEquals(2, ShopifyListing::where('integration_credential_id', $this->shopifyCredential->id)->count());
    }

    public function test_import_skips_existing_listings(): void
    {
        $product = $this->createTestProduct(['sku' => 'EXISTING']);
        $this->createShopifyListing($product, ['shopify_product_id' => 1000001]);

        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(self::shopifyProductListResponse(1)),
        ]);

        $bulkJob = BulkOperationJob::create([
            'organization_id' => $this->shopifyOrganization->id,
            'user_id' => $this->shopifyUser->id,
            'operation_type' => 'shopify_import',
            'status' => 'pending',
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        $job = new ImportShopifyProducts(
            $this->shopifyCredential->id,
            $this->shopifyOrganization->id,
            $this->shopifyUser->id,
            $bulkJob->id
        );
        $job->handle();

        $bulkJob->refresh();
        $this->assertEquals(1, $bulkJob->parameters['skipped']);
    }

    public function test_import_links_existing_product_by_sku(): void
    {
        $existingProduct = $this->createTestProduct(['sku' => 'PROD-1']);

        $productList = self::shopifyProductListResponse(1);
        $productList['products'][0]['variants'][0]['sku'] = 'PROD-1';

        Http::fake([
            '*.myshopify.com/admin/*' => Http::response($productList),
        ]);

        $bulkJob = BulkOperationJob::create([
            'organization_id' => $this->shopifyOrganization->id,
            'user_id' => $this->shopifyUser->id,
            'operation_type' => 'shopify_import',
            'status' => 'pending',
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        $job = new ImportShopifyProducts(
            $this->shopifyCredential->id,
            $this->shopifyOrganization->id,
            $this->shopifyUser->id,
            $bulkJob->id,
            ['skip_existing_skus' => true]
        );
        $job->handle();

        $listing = ShopifyListing::where('product_id', $existingProduct->id)->first();
        $this->assertNotNull($listing);
    }

    public function test_import_marks_bulk_job_as_processing(): void
    {
        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(self::shopifyProductListResponse(1)),
        ]);

        $bulkJob = BulkOperationJob::create([
            'organization_id' => $this->shopifyOrganization->id,
            'user_id' => $this->shopifyUser->id,
            'operation_type' => 'shopify_import',
            'status' => 'pending',
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        $job = new ImportShopifyProducts(
            $this->shopifyCredential->id,
            $this->shopifyOrganization->id,
            $this->shopifyUser->id,
            $bulkJob->id
        );
        $job->handle();

        $bulkJob->refresh();
        $this->assertEquals('completed', $bulkJob->status);
    }

    public function test_import_handles_missing_credential(): void
    {
        $bulkJob = BulkOperationJob::create([
            'organization_id' => $this->shopifyOrganization->id,
            'user_id' => $this->shopifyUser->id,
            'operation_type' => 'shopify_import',
            'status' => 'pending',
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        $job = new ImportShopifyProducts(
            99999, // Non-existent credential
            $this->shopifyOrganization->id,
            $this->shopifyUser->id,
            $bulkJob->id
        );
        $job->handle();

        $bulkJob->refresh();
        $this->assertEquals('failed', $bulkJob->status);
    }

    public function test_import_handles_missing_bulk_job(): void
    {
        $job = new ImportShopifyProducts(
            $this->shopifyCredential->id,
            $this->shopifyOrganization->id,
            $this->shopifyUser->id,
            99999 // Non-existent bulk job
        );

        // Should not throw
        $job->handle();
        $this->assertTrue(true);
    }

    public function test_import_creates_labeling_work_order_for_new_products(): void
    {
        OrganizationSettings::set($this->shopifyOrganization->id, 'auto_labeling_enabled', true);

        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(self::shopifyProductListResponse(2)),
        ]);

        $bulkJob = BulkOperationJob::create([
            'organization_id' => $this->shopifyOrganization->id,
            'user_id' => $this->shopifyUser->id,
            'operation_type' => 'shopify_import',
            'status' => 'pending',
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
            'parameters' => ['location_id' => null],
        ]);

        (new ImportShopifyProducts(
            $this->shopifyCredential->id,
            $this->shopifyOrganization->id,
            $this->shopifyUser->id,
            $bulkJob->id
        ))->handle();

        $wo = LabelingWorkOrder::withoutGlobalScopes()
            ->where('organization_id', $this->shopifyOrganization->id)
            ->where('source_type', LabelingWorkOrder::SOURCE_SHOPIFY_IMPORT)
            ->first();

        $this->assertNotNull($wo, 'Labeling work order should be created for Shopify import');
        $this->assertEquals(2, $wo->items()->count());
        $this->assertEquals(2, count($bulkJob->fresh()->parameters['created_product_ids']));
    }

    public function test_import_skips_labeling_work_order_when_disabled(): void
    {
        // auto_labeling_enabled defaults to false

        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(self::shopifyProductListResponse(1)),
        ]);

        $bulkJob = BulkOperationJob::create([
            'organization_id' => $this->shopifyOrganization->id,
            'user_id' => $this->shopifyUser->id,
            'operation_type' => 'shopify_import',
            'status' => 'pending',
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        (new ImportShopifyProducts(
            $this->shopifyCredential->id,
            $this->shopifyOrganization->id,
            $this->shopifyUser->id,
            $bulkJob->id
        ))->handle();

        $this->assertEquals(0, LabelingWorkOrder::withoutGlobalScopes()
            ->where('organization_id', $this->shopifyOrganization->id)
            ->count());
    }

    public function test_import_only_includes_newly_created_products_in_label_wo(): void
    {
        OrganizationSettings::set($this->shopifyOrganization->id, 'auto_labeling_enabled', true);

        // Pre-create product matching SKU of first Shopify product
        $existing = $this->createTestProduct(['sku' => 'PROD-1']);

        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(self::shopifyProductListResponse(2)),
        ]);

        $bulkJob = BulkOperationJob::create([
            'organization_id' => $this->shopifyOrganization->id,
            'user_id' => $this->shopifyUser->id,
            'operation_type' => 'shopify_import',
            'status' => 'pending',
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        (new ImportShopifyProducts(
            $this->shopifyCredential->id,
            $this->shopifyOrganization->id,
            $this->shopifyUser->id,
            $bulkJob->id,
            ['skip_existing_skus' => true]
        ))->handle();

        $wo = LabelingWorkOrder::withoutGlobalScopes()
            ->where('source_type', LabelingWorkOrder::SOURCE_SHOPIFY_IMPORT)
            ->first();

        $this->assertNotNull($wo);
        // Only the 1 newly created product should be in WO; existing is linked, not labeled
        $this->assertEquals(1, $wo->items()->count());
        $this->assertFalse($wo->items()->where('product_id', $existing->id)->exists());
    }

    public function test_import_handles_api_error(): void
    {
        Http::fake([
            '*.myshopify.com/admin/*' => Http::response(['errors' => 'Server error'], 500),
        ]);

        $bulkJob = BulkOperationJob::create([
            'organization_id' => $this->shopifyOrganization->id,
            'user_id' => $this->shopifyUser->id,
            'operation_type' => 'shopify_import',
            'status' => 'pending',
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        $job = new ImportShopifyProducts(
            $this->shopifyCredential->id,
            $this->shopifyOrganization->id,
            $this->shopifyUser->id,
            $bulkJob->id
        );
        $job->handle();

        $bulkJob->refresh();
        $this->assertEquals('failed', $bulkJob->status);
    }
}
