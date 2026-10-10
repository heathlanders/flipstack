<?php

namespace Tests\Feature\Amazon;

use App\Jobs\Amazon\CreateAmazonListingJob;
use App\Models\Amazon\AmazonListing;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmazonListingSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Team $team;

    protected IntegrationCredential $credential;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->team = Team::factory()->create(['organization_id' => $this->organization->id]);

        $this->credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => 'amazon',
            'environment' => 'sandbox',
            'name' => 'Amazon Test',
            'is_active' => true,
            'config' => [
                'amazon_seller_id' => 'A1SELLER1',
                'amazon_marketplace_id' => 'ATVPDKIKX0DER',
            ],
        ]);

        OAuthToken::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'integration_credential_id' => $this->credential->id,
            'platform' => 'amazon',
            'environment' => 'sandbox',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $this->mockAmazonRateLimiter();
    }

    protected function mockAmazonRateLimiter(): void
    {
        $this->app->singleton(\App\Services\Amazon\AmazonRateLimiter::class, function () {
            $mock = \Mockery::mock(\App\Services\Amazon\AmazonRateLimiter::class);
            $mock->shouldReceive('waitIfNeeded')->andReturnNull();
            $mock->shouldReceive('recordResponse')->andReturnNull();
            $mock->shouldReceive('shouldThrottle')->andReturn(false);

            return $mock;
        });
    }

    protected function makeProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'name' => 'Test Widget',
            'sku' => 'TEST-WIDGET',
            'upc' => '012345678905',
            'selling_price' => 29.99,
            'available_quantity' => 10,
            'stock_quantity' => 10,
        ], $overrides));
    }

    public function test_create_listing_job_sets_status_created_on_accepted(): void
    {
        $product = $this->makeProduct();

        Http::fake([
            '*sellingpartnerapi*/catalog/2022-04-01/items*' => Http::response([
                'items' => [[
                    'asin' => 'B0000ASIN1',
                    'summaries' => [['itemName' => 'Catalog Hit', 'productType' => 'TOY']],
                ]],
            ], 200),
            '*sellingpartnerapi*/listings/2021-08-01/items/A1SELLER1/*' => Http::response([
                'sku' => 'TEST-WIDGET',
                'status' => 'ACCEPTED',
                'submissionId' => 'sub-1',
            ], 200),
        ]);

        (new CreateAmazonListingJob((string) $product->id, $this->credential->id))->handle();

        $listing = AmazonListing::where('product_id', $product->id)->first();
        $this->assertNotNull($listing);
        $this->assertSame('B0000ASIN1', $listing->asin);
        $this->assertSame(AmazonListing::STATUS_CREATED, $listing->status);
        $this->assertSame(AmazonListing::MATCH_SOURCE_GTIN, $listing->asin_match_source);
    }

    public function test_create_listing_marks_incomplete_when_no_match(): void
    {
        $product = $this->makeProduct(['upc' => null, 'ean' => null, 'barcode' => null]);

        Http::fake([
            '*sellingpartnerapi*/catalog/2022-04-01/items*' => Http::response([
                'items' => [],
            ], 200),
        ]);

        (new CreateAmazonListingJob((string) $product->id, $this->credential->id))->handle();

        $listing = AmazonListing::where('product_id', $product->id)->first();
        $this->assertNotNull($listing);
        $this->assertSame(AmazonListing::STATUS_INCOMPLETE, $listing->status);
        $this->assertNull($listing->asin);
    }

    public function test_create_listing_uses_manual_asin_override(): void
    {
        $product = $this->makeProduct();

        Http::fake([
            '*sellingpartnerapi*/listings/2021-08-01/items/A1SELLER1/*' => Http::response([
                'status' => 'ACCEPTED',
            ], 200),
        ]);

        (new CreateAmazonListingJob(
            productId: (string) $product->id,
            credentialId: $this->credential->id,
            manualAsin: 'B0MANUAL00',
        ))->handle();

        // Catalog search must NOT be hit when a manual ASIN is supplied.
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/catalog/2022-04-01/items');
        });

        $listing = AmazonListing::where('product_id', $product->id)->first();
        $this->assertSame('B0MANUAL00', $listing->asin);
        $this->assertSame(AmazonListing::MATCH_SOURCE_MANUAL, $listing->asin_match_source);
    }

    public function test_adapter_update_quantity_skips_fba(): void
    {
        $product = $this->makeProduct();

        $listing = AmazonListing::create([
            'product_id' => $product->id,
            'integration_credential_id' => $this->credential->id,
            'team_id' => $this->team->id,
            'amazon_sku' => 'TEST-FBA',
            'asin' => 'B000FBA001',
            'fulfillment_channel' => AmazonListing::FULFILLMENT_FBA,
            'status' => AmazonListing::STATUS_ACTIVE,
        ]);

        Http::fake();

        $adapter = new \App\Services\Amazon\AmazonMarketplaceAdapter($this->credential->fresh());
        $result = $adapter->updateQuantity($product, $listing);

        $this->assertTrue($result);
        Http::assertNothingSent();
    }
}
