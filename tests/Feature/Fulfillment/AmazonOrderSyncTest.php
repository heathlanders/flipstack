<?php

namespace Tests\Feature\Fulfillment;

use App\Jobs\Fulfillment\SyncAmazonOrders;
use App\Jobs\Inventory\PushInventoryToMarketplaces;
use App\Models\Amazon\AmazonListing;
use App\Models\Catalog\Product;
use App\Models\Fulfillment\Order;
use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use App\Services\Fulfillment\AmazonOrderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AmazonOrderSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected IntegrationCredential $credential;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->setUpAmazonFixtures();
    }

    protected function setUpAmazonFixtures(): void
    {
        $this->organization = Organization::factory()->create([
            'sku_prefix' => 'AMZ',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $role = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-amz',
            'is_system' => true,
            'permissions' => ['inventory.view', 'products.view'],
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($role->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        $this->user->current_team_id = $this->team->id;
        $this->user->save();

        $this->team->users()->attach($this->user->id, ['role' => 'admin']);

        OnboardingProgress::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'current_step' => 'completed',
            'steps_completed' => ['welcome', 'profile', 'organization', 'team'],
        ]);

        $this->credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => 'amazon',
            'environment' => 'production',
            'name' => 'Amazon Test',
            'is_active' => true,
            'auto_sync_enabled' => true,
            'client_id' => 'test-amazon-client-id',
            'client_secret' => 'test-amazon-client-secret',
            'config' => [
                'amazon_marketplace_id' => 'ATVPDKIKX0DER',
            ],
        ]);

        OAuthToken::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'integration_credential_id' => $this->credential->id,
            'platform' => 'amazon',
            'environment' => 'production',
            'access_token' => 'test-amazon-access-token',
            'refresh_token' => 'test-amazon-refresh-token',
            'expires_at' => now()->addHours(1),
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

    protected function amazonOrderPayload(array $overrides = []): array
    {
        return array_merge([
            'AmazonOrderId' => '111-1234567-1234567',
            'OrderStatus' => 'Unshipped',
            'PurchaseDate' => now()->toIso8601String(),
            'LatestShipDate' => now()->addDays(3)->toIso8601String(),
            'OrderTotal' => [
                'Amount' => '49.99',
                'CurrencyCode' => 'USD',
            ],
            'ShippingAddress' => [
                'Name' => 'Jane Smith',
                'AddressLine1' => '456 Oak Ave',
                'AddressLine2' => null,
                'City' => 'Portland',
                'StateOrRegion' => 'OR',
                'PostalCode' => '97201',
                'CountryCode' => 'US',
                'Phone' => '555-0200',
            ],
            'BuyerInfo' => [
                'BuyerEmail' => 'buyer@example.com',
            ],
            'OrderItems' => [
                [
                    'OrderItemId' => 'item-001',
                    'SellerSKU' => 'AMZ-TEST-001',
                    'ASIN' => 'B000000001',
                    'Title' => 'Test Amazon Product',
                    'QuantityOrdered' => 1,
                    'ItemPrice' => ['Amount' => '49.99', 'CurrencyCode' => 'USD'],
                    'ItemTax' => ['Amount' => '3.50', 'CurrencyCode' => 'USD'],
                    'ShippingPrice' => ['Amount' => '5.99', 'CurrencyCode' => 'USD'],
                    'ConditionId' => 'New',
                ],
            ],
        ], $overrides);
    }

    public function test_import_order_creates_order_record(): void
    {
        $payload = $this->amazonOrderPayload();

        $service = new AmazonOrderSyncService($this->credential);
        $result = $service->importOrder($payload);

        $this->assertTrue($result['created']);
        $this->assertEquals('amazon', $result['order']->marketplace);
        $this->assertEquals('111-1234567-1234567', $result['order']->marketplace_order_id);
        $this->assertEquals(Order::STATUS_PENDING, $result['order']->status);
    }

    public function test_import_order_creates_order_items(): void
    {
        $payload = $this->amazonOrderPayload();

        $service = new AmazonOrderSyncService($this->credential);
        $result = $service->importOrder($payload);

        $order = $result['order'];
        $this->assertEquals(1, $order->items()->count());

        $item = $order->items()->first();
        $this->assertEquals('Test Amazon Product', $item->title);
        $this->assertEquals('AMZ-TEST-001', $item->sku);
        $this->assertEquals(1, $item->quantity);
    }

    public function test_import_order_extracts_shipping_address(): void
    {
        $payload = $this->amazonOrderPayload();

        $service = new AmazonOrderSyncService($this->credential);
        $result = $service->importOrder($payload);

        $order = $result['order'];
        $this->assertEquals('Jane Smith', $order->shipping_address_name);
        $this->assertEquals('456 Oak Ave', $order->shipping_address_line1);
        $this->assertEquals('Portland', $order->shipping_city);
        $this->assertEquals('OR', $order->shipping_state);
        $this->assertEquals('97201', $order->shipping_postal_code);
    }

    public function test_import_order_matches_product_by_asin(): void
    {
        $product = Product::create([
            'organization_id' => $this->organization->id,
            'name' => 'Amazon Product',
            'sku' => 'AMZ-TEST-001',
            'selling_price' => 49.99,
            'status' => 'active',
            'condition' => 'NEW',
            'available_quantity' => 10,
            'stock_quantity' => 10,
            'manage_stock' => true,
            'created_by' => $this->user->id,
            'gallery_images' => [],
        ]);

        AmazonListing::create([
            'product_id' => $product->id,
            'integration_credential_id' => $this->credential->id,
            'team_id' => $this->team->id,
            'asin' => 'B000000001',
            'amazon_sku' => 'AMZ-TEST-001',
            'status' => 'active',
            'listed_price' => 49.99,
            'currency' => 'USD',
        ]);

        $payload = $this->amazonOrderPayload();

        $service = new AmazonOrderSyncService($this->credential);
        $result = $service->importOrder($payload);

        $item = $result['order']->items()->first();
        $this->assertEquals($product->id, $item->product_id);
    }

    public function test_import_order_reserves_inventory(): void
    {
        $product = Product::create([
            'organization_id' => $this->organization->id,
            'name' => 'Amazon Product',
            'sku' => 'AMZ-TEST-001',
            'selling_price' => 49.99,
            'status' => 'active',
            'condition' => 'NEW',
            'available_quantity' => 10,
            'stock_quantity' => 10,
            'manage_stock' => true,
            'created_by' => $this->user->id,
            'gallery_images' => [],
        ]);

        AmazonListing::create([
            'product_id' => $product->id,
            'integration_credential_id' => $this->credential->id,
            'team_id' => $this->team->id,
            'asin' => 'B000000001',
            'amazon_sku' => 'AMZ-TEST-001',
            'status' => 'active',
            'listed_price' => 49.99,
            'currency' => 'USD',
        ]);

        $payload = $this->amazonOrderPayload();

        $service = new AmazonOrderSyncService($this->credential);
        $service->importOrder($payload);

        $product->refresh();
        $this->assertEquals(9, $product->available_quantity);

        Queue::assertPushed(PushInventoryToMarketplaces::class);
    }

    public function test_import_order_upserts_existing(): void
    {
        $payload = $this->amazonOrderPayload();

        $service = new AmazonOrderSyncService($this->credential);
        $result1 = $service->importOrder($payload);
        $this->assertTrue($result1['created']);

        $result2 = $service->importOrder($payload);
        $this->assertFalse($result2['created']);
        $this->assertEquals($result1['order']->id, $result2['order']->id);
    }

    public function test_maps_amazon_cancelled_status(): void
    {
        $payload = $this->amazonOrderPayload(['OrderStatus' => 'Canceled']);

        $service = new AmazonOrderSyncService($this->credential);
        $result = $service->importOrder($payload);

        $this->assertEquals(Order::STATUS_CANCELLED, $result['order']->status);
    }

    public function test_maps_amazon_shipped_status(): void
    {
        $payload = $this->amazonOrderPayload(['OrderStatus' => 'Shipped']);

        $service = new AmazonOrderSyncService($this->credential);
        $result = $service->importOrder($payload);

        $this->assertEquals(Order::STATUS_SHIPPED, $result['order']->status);
    }

    public function test_sync_amazon_orders_job_dispatches(): void
    {
        Queue::fake();

        SyncAmazonOrders::dispatch();

        Queue::assertPushed(SyncAmazonOrders::class);
    }
}
