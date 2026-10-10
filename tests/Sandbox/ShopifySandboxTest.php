<?php

namespace Tests\Sandbox;

use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\User\User;
use App\Services\Shopify\ShopifyInventoryService;
use App\Services\Shopify\ShopifyOAuthService;
use App\Services\Shopify\ShopifyProductService;
use App\Services\Shopify\ShopifyWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Live sandbox tests that hit a real Shopify development store.
 *
 * Skipped by default unless SHOPIFY_TEST_STORE_DOMAIN is set in env.
 * Run with: php artisan test --group=sandbox
 *
 * @group sandbox
 */
class ShopifySandboxTest extends TestCase
{
    use RefreshDatabase;

    protected static ?int $testProductId = null;

    protected static ?int $testWebhookId = null;

    protected IntegrationCredential $credential;

    protected function setUp(): void
    {
        parent::setUp();

        if (! env('SHOPIFY_TEST_STORE_DOMAIN')) {
            $this->markTestSkipped('SHOPIFY_TEST_STORE_DOMAIN not configured - skipping sandbox tests');
        }

        $org = Organization::factory()->create();
        $team = Team::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create(['organization_id' => $org->id]);

        $this->credential = IntegrationCredential::create([
            'organization_id' => $org->id,
            'team_id' => $team->id,
            'platform' => 'shopify',
            'environment' => 'production',
            'name' => 'Sandbox Test Store',
            'is_active' => true,
            'client_id' => env('SHOPIFY_API_KEY', 'sandbox-test-key'),
            'config' => [
                'shop_domain' => env('SHOPIFY_TEST_STORE_DOMAIN'),
                'primary_location_id' => env('SHOPIFY_TEST_LOCATION_ID'),
            ],
        ]);

        OAuthToken::create([
            'organization_id' => $org->id,
            'team_id' => $team->id,
            'integration_credential_id' => $this->credential->id,
            'platform' => 'shopify',
            'environment' => 'production',
            'access_token' => env('SHOPIFY_TEST_ACCESS_TOKEN'),
            'is_active' => true,
        ]);
    }

    public function test_can_verify_credentials(): void
    {
        $oauthService = app(ShopifyOAuthService::class);
        $result = $oauthService->verifyCredentials($this->credential);

        $this->assertTrue($result);
    }

    public function test_can_fetch_shop_info(): void
    {
        $oauthService = app(ShopifyOAuthService::class);
        $token = $this->credential->getActiveShopifyToken();
        $shopInfo = $oauthService->getShopInfo(
            $this->credential->config['shop_domain'],
            $token->decrypted_access_token
        );

        $this->assertArrayHasKey('name', $shopInfo);
        $this->assertArrayHasKey('domain', $shopInfo);
        $this->assertNotEmpty($shopInfo['name']);
    }

    public function test_can_fetch_locations(): void
    {
        $service = new ShopifyInventoryService($this->credential);
        $locations = $service->getLocations();

        $this->assertIsArray($locations);
        $this->assertNotEmpty($locations);
        $this->assertArrayHasKey('id', $locations[0]);
    }

    /**
     * @depends test_can_fetch_locations
     */
    public function test_can_create_product(): void
    {
        $org = $this->credential->organization;
        $user = User::where('organization_id', $org->id)->first();

        $product = \App\Models\Catalog\Product::create([
            'organization_id' => $org->id,
            'name' => '[SANDBOX-TEST] Test Product '.now()->timestamp,
            'description' => 'Automated sandbox test product - safe to delete',
            'sku' => 'SANDBOX-'.now()->timestamp,
            'selling_price' => 9.99,
            'stock_quantity' => 10,
            'status' => 'active',
            'condition' => 'NEW',
            'manage_stock' => true,
            'created_by' => $user->id,
        ]);

        $listing = \App\Models\Shopify\ShopifyListing::create([
            'product_id' => $product->id,
            'integration_credential_id' => $this->credential->id,
            'team_id' => $this->credential->team_id,
            'shopify_sku' => $product->sku,
            'status' => 'pending',
            'currency' => 'USD',
        ]);

        $service = new ShopifyProductService($this->credential);
        $result = $service->createProduct($product, $listing);

        $this->assertArrayHasKey('id', $result);
        $this->assertNotEmpty($result['id']);

        static::$testProductId = $result['id'];

        $listing->refresh();
        $this->assertEquals($result['id'], $listing->shopify_product_id);
    }

    /**
     * @depends test_can_create_product
     */
    public function test_can_get_product(): void
    {
        $this->assertNotNull(static::$testProductId, 'Test product was not created');

        $service = new ShopifyProductService($this->credential);
        $product = $service->getProduct(static::$testProductId);

        $this->assertArrayHasKey('title', $product);
        $this->assertStringContainsString('[SANDBOX-TEST]', $product['title']);
    }

    /**
     * @depends test_can_create_product
     */
    public function test_can_update_product(): void
    {
        $this->assertNotNull(static::$testProductId, 'Test product was not created');

        $listing = \App\Models\Shopify\ShopifyListing::where('shopify_product_id', static::$testProductId)->first();
        $product = $listing->product;

        $product->update(['name' => '[SANDBOX-TEST] Updated Product '.now()->timestamp]);
        $listing->update(['product_data_hash' => null]); // Force update

        $service = new ShopifyProductService($this->credential);
        $result = $service->updateProduct($product, $listing, true);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Updated Product', $result['title']);
    }

    /**
     * @depends test_can_create_product
     */
    public function test_can_set_inventory_level(): void
    {
        $locationId = $this->credential->config['primary_location_id'];

        if (! $locationId) {
            $service = new ShopifyInventoryService($this->credential);
            $locations = $service->getLocations();
            $locationId = $locations[0]['id'] ?? null;
        }

        $this->assertNotNull($locationId, 'No location ID available');
        $this->assertNotNull(static::$testProductId, 'Test product was not created');

        $listing = \App\Models\Shopify\ShopifyListing::where('shopify_product_id', static::$testProductId)->first();
        $this->assertNotNull($listing->shopify_inventory_item_id, 'No inventory item ID');

        $service = new ShopifyInventoryService($this->credential);
        $result = $service->setInventoryLevel($locationId, $listing->shopify_inventory_item_id, 10);

        $this->assertArrayHasKey('inventory_level', $result);
    }

    /**
     * @depends test_can_set_inventory_level
     */
    public function test_can_get_inventory_levels(): void
    {
        $locationId = $this->credential->config['primary_location_id'];
        $listing = \App\Models\Shopify\ShopifyListing::where('shopify_product_id', static::$testProductId)->first();

        $service = new ShopifyInventoryService($this->credential);
        $levels = $service->getInventoryLevels($locationId, [$listing->shopify_inventory_item_id]);

        $this->assertIsArray($levels);
        $this->assertNotEmpty($levels);
    }

    public function test_can_list_products(): void
    {
        $service = new ShopifyProductService($this->credential);
        $products = $service->listProducts(['limit' => 5]);

        $this->assertIsArray($products);
    }

    public function test_can_register_and_list_webhooks(): void
    {
        $service = new ShopifyWebhookService($this->credential);

        // Register a test webhook
        try {
            $result = $service->registerWebhooks();
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            // May already be registered
        }

        $webhooks = $service->getWebhooks();
        $this->assertIsArray($webhooks);

        if (! empty($webhooks)) {
            static::$testWebhookId = $webhooks[0]['id'];
        }
    }

    /**
     * @depends test_can_register_and_list_webhooks
     */
    public function test_can_delete_webhook(): void
    {
        if (! static::$testWebhookId) {
            $this->markTestSkipped('No test webhook to delete');
        }

        $service = new ShopifyWebhookService($this->credential);
        $service->deleteWebhook(static::$testWebhookId);

        // Verify deletion
        $webhooks = $service->getWebhooks();
        $ids = array_column($webhooks, 'id');
        $this->assertNotContains(static::$testWebhookId, $ids);

        static::$testWebhookId = null;
    }

    /**
     * @depends test_can_create_product
     */
    public function test_cleanup_test_product(): void
    {
        if (! static::$testProductId) {
            $this->markTestSkipped('No test product to clean up');
        }

        $listing = \App\Models\Shopify\ShopifyListing::where('shopify_product_id', static::$testProductId)->first();

        if ($listing) {
            $service = new ShopifyProductService($this->credential);
            $service->deleteProduct($listing);

            $listing->refresh();
            $this->assertEquals('deleted', $listing->status);
        }

        static::$testProductId = null;
    }

    public static function tearDownAfterClass(): void
    {
        // Cleanup is handled by test_cleanup_test_product via @depends
        parent::tearDownAfterClass();
    }
}
