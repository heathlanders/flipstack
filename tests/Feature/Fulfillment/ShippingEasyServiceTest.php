<?php

namespace Tests\Feature\Fulfillment;

use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\OrderItem;
use App\Models\Fulfillment\Shipment;
use App\Models\Fulfillment\ShippingEasySyncLog;
use App\Models\Fulfillment\ShippingRate;
use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\InventoryLocation;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Services\Fulfillment\ShippingEasyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShippingEasyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Team $team;

    protected IntegrationCredential $credential;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();
    }

    protected function setUpFixtures(): void
    {
        $this->organization = Organization::factory()->create([
            'sku_prefix' => 'SES',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => 'shippingeasy',
            'environment' => 'test',
            'name' => 'ShippingEasy Test',
            'client_id' => 'test_api_key',
            'client_secret' => 'test_api_secret',
            'is_active' => true,
            'config' => ['store_api_key' => 'test_store_key'],
        ]);

        $this->location = InventoryLocation::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'name' => 'Warehouse',
            'code' => 'WH1',
            'postal_code' => '90210',
            'is_active' => true,
        ]);
    }

    protected function createOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'marketplace' => 'ebay',
            'marketplace_order_id' => 'TEST-'.rand(100000, 999999),
            'status' => Order::STATUS_PENDING,
            'buyer_username' => 'testbuyer',
            'buyer_name' => 'John Doe',
            'shipping_address_name' => 'John Doe',
            'shipping_address_line1' => '123 Main St',
            'shipping_city' => 'Los Angeles',
            'shipping_state' => 'CA',
            'shipping_postal_code' => '90001',
            'shipping_country' => 'US',
            'subtotal' => 29.99,
            'total_amount' => 29.99,
            'currency' => 'USD',
        ], $overrides));
    }

    protected function createShipment(Order $order, array $overrides = []): Shipment
    {
        return Shipment::create(array_merge([
            'organization_id' => $this->organization->id,
            'order_id' => $order->id,
            'location_id' => $this->location->id,
            'shipment_number' => 'SHP-'.rand(10000, 99999),
            'status' => Shipment::STATUS_PENDING,
            'carrier' => 'USPS',
            'service' => 'Priority',
            'ship_from_name' => 'Warehouse',
            'ship_from_address_line1' => '456 Warehouse Blvd',
            'ship_from_city' => 'Beverly Hills',
            'ship_from_state' => 'CA',
            'ship_from_postal_code' => '90210',
            'ship_from_country' => 'US',
        ], $overrides));
    }

    public function test_is_available_returns_true_when_configured(): void
    {
        $service = new ShippingEasyService($this->organization);

        $this->assertTrue($service->isAvailable());
    }

    public function test_is_available_returns_false_when_no_credentials(): void
    {
        $otherOrg = Organization::factory()->create();

        $service = new ShippingEasyService($otherOrg);

        $this->assertFalse($service->isAvailable());
    }

    public function test_is_available_returns_false_when_inactive(): void
    {
        $this->credential->update(['is_active' => false]);

        $service = new ShippingEasyService($this->organization);

        $this->assertFalse($service->isAvailable());
    }

    public function test_constructor_does_not_throw_when_unavailable(): void
    {
        $otherOrg = Organization::factory()->create();

        // Should not throw
        $service = new ShippingEasyService($otherOrg);

        $this->assertFalse($service->isAvailable());
    }

    public function test_test_connection_succeeds(): void
    {
        Http::fake([
            'app.shippingeasy.com/api/carriers*' => Http::response(['carriers' => []], 200),
        ]);

        $service = new ShippingEasyService($this->organization);

        $this->assertTrue($service->testConnection());
    }

    public function test_test_connection_fails(): void
    {
        Http::fake([
            'app.shippingeasy.com/api/carriers*' => Http::response('Unauthorized', 401),
        ]);

        $service = new ShippingEasyService($this->organization);

        $this->assertFalse($service->testConnection());
    }

    public function test_get_rates_creates_shipping_rate_records(): void
    {
        Http::fake([
            'app.shippingeasy.com/api/rates*' => Http::response([
                'rates' => [
                    ['carrier' => 'USPS', 'service_name' => 'Priority Mail', 'service_code' => 'usps_priority', 'total_cost' => 7.50, 'estimated_days' => 3],
                    ['carrier' => 'UPS', 'service_name' => 'Ground', 'service_code' => 'ups_ground', 'total_cost' => 9.25, 'estimated_days' => 5],
                ],
            ], 200),
        ]);

        $order = $this->createOrder();

        $service = new ShippingEasyService($this->organization);
        $rates = $service->getRates($order, $this->location);

        $this->assertCount(2, $rates);
        $this->assertEquals('USPS', $rates->first()->carrier);
        $this->assertEquals(7.50, $rates->first()->rate);
        $this->assertEquals('shippingeasy', $rates->first()->metadata['provider']);
    }

    public function test_purchase_label_creates_order_and_returns_label(): void
    {
        Http::fake([
            'app.shippingeasy.com/api/stores/test_store_key/orders?*' => Http::response([
                'order' => ['id' => 'se_order_123'],
            ], 200),
            'app.shippingeasy.com/api/orders/se_order_123/labels*' => Http::response([
                'tracking_number' => '9400111899223100001234',
                'tracking_url' => 'https://tracking.example.com/9400111899223100001234',
                'label_url' => 'https://labels.example.com/label.pdf',
                'carrier' => 'USPS',
                'cost' => 7.50,
                'shipment_id' => 'se_ship_456',
            ], 200),
        ]);

        $order = $this->createOrder();
        OrderItem::create([
            'order_id' => $order->id,
            'title' => 'Test Product',
            'sku' => 'TEST-SKU',
            'quantity' => 1,
            'unit_price' => 29.99,
            'total_price' => 29.99,
            'pick_status' => 'pending',
        ]);

        ShippingRate::create([
            'organization_id' => $this->organization->id,
            'order_id' => $order->id,
            'easypost_rate_id' => 'usps_priority',
            'carrier' => 'USPS',
            'service' => 'Priority Mail',
            'rate' => 7.50,
            'currency' => 'USD',
            'expires_at' => now()->addHour(),
            'metadata' => ['provider' => 'shippingeasy', 'carrier_code' => 'USPS', 'service_code' => 'usps_priority'],
        ]);

        $service = new ShippingEasyService($this->organization);
        $result = $service->purchaseLabel($order, 'usps_priority');

        $this->assertEquals('9400111899223100001234', $result['tracking_number']);
        $this->assertEquals('https://labels.example.com/label.pdf', $result['label_url']);
        $this->assertEquals('se_ship_456', $result['shippingeasy_shipment_id']);
        $this->assertEquals('se_order_123', $result['shippingeasy_order_id']);
    }

    public function test_purchase_label_sends_line_items(): void
    {
        Http::fake([
            'app.shippingeasy.com/api/stores/test_store_key/orders?*' => Http::response([
                'order' => ['id' => 'se_order_789'],
            ], 200),
            'app.shippingeasy.com/api/orders/se_order_789/labels*' => Http::response([
                'tracking_number' => '1234',
                'label_url' => 'https://labels.example.com/label.pdf',
            ], 200),
        ]);

        $order = $this->createOrder();
        OrderItem::create([
            'order_id' => $order->id,
            'title' => 'Widget',
            'sku' => 'WDG-001',
            'quantity' => 2,
            'unit_price' => 15.00,
            'total_price' => 30.00,
            'weight_oz' => 8,
            'pick_status' => 'pending',
        ]);

        ShippingRate::create([
            'organization_id' => $this->organization->id,
            'order_id' => $order->id,
            'easypost_rate_id' => 'usps_priority',
            'carrier' => 'USPS',
            'service' => 'Priority Mail',
            'rate' => 7.50,
            'currency' => 'USD',
            'expires_at' => now()->addHour(),
            'metadata' => ['provider' => 'shippingeasy', 'carrier_code' => 'USPS', 'service_code' => 'usps_priority'],
        ]);

        $service = new ShippingEasyService($this->organization);
        $service->purchaseLabel($order, 'usps_priority');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/stores/test_store_key/orders')) {
                return false;
            }

            $body = $request->data();
            $lineItems = $body['order']['recipients'][0]['line_items_attributes'] ?? [];

            return count($lineItems) === 1
                && $lineItems[0]['product_name'] === 'Widget'
                && $lineItems[0]['sku'] === 'WDG-001'
                && $lineItems[0]['quantity'] === 2;
        });
    }

    public function test_purchase_label_throws_without_store_api_key(): void
    {
        $this->credential->update(['config' => []]);

        $order = $this->createOrder();
        ShippingRate::create([
            'organization_id' => $this->organization->id,
            'order_id' => $order->id,
            'easypost_rate_id' => 'usps_priority',
            'carrier' => 'USPS',
            'service' => 'Priority Mail',
            'rate' => 7.50,
            'currency' => 'USD',
            'expires_at' => now()->addHour(),
            'metadata' => ['provider' => 'shippingeasy'],
        ]);

        $service = new ShippingEasyService($this->organization);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Store API Key not configured');
        $service->purchaseLabel($order, 'usps_priority');
    }

    public function test_void_label_succeeds(): void
    {
        Http::fake([
            'app.shippingeasy.com/api/shipments/se_ship_123/void*' => Http::response(['success' => true], 200),
        ]);

        $order = $this->createOrder();
        $shipment = $this->createShipment($order, [
            'shippingeasy_shipment_id' => 'se_ship_123',
        ]);

        $service = new ShippingEasyService($this->organization);
        $this->assertTrue($service->voidLabel($shipment));
    }

    public function test_void_label_throws_without_shipment_id(): void
    {
        $order = $this->createOrder();
        $shipment = $this->createShipment($order, ['shippingeasy_shipment_id' => null]);

        $service = new ShippingEasyService($this->organization);

        $this->expectException(\Exception::class);
        $service->voidLabel($shipment);
    }

    public function test_get_orders_fetches_from_api(): void
    {
        Http::fake([
            'app.shippingeasy.com/api/orders*' => Http::response([
                'orders' => [
                    ['id' => 1, 'external_order_identifier' => 'ORD-001'],
                ],
            ], 200),
        ]);

        $service = new ShippingEasyService($this->organization);
        $result = $service->getOrders(['status' => 'shipped']);

        $this->assertCount(1, $result['orders']);
    }

    public function test_cancel_order_returns_true(): void
    {
        Http::fake([
            'app.shippingeasy.com/api/stores/test_store_key/orders/ORD-123/cancellations*' => Http::response([
                'order' => ['id' => '456', 'external_order_identifier' => 'ORD-123'],
            ], 200),
        ]);

        $service = new ShippingEasyService($this->organization);
        $this->assertTrue($service->cancelOrder('ORD-123'));
    }

    public function test_sync_log_created_on_success(): void
    {
        Http::fake([
            'app.shippingeasy.com/api/carriers*' => Http::response(['carriers' => []], 200),
        ]);

        $service = new ShippingEasyService($this->organization);
        $service->getCarriers();

        $this->assertDatabaseHas('shipping_easy_sync_logs', [
            'organization_id' => $this->organization->id,
            'operation' => ShippingEasySyncLog::OP_GET_CARRIERS,
            'status' => ShippingEasySyncLog::STATUS_SUCCESS,
        ]);
    }

    public function test_sync_log_records_error(): void
    {
        Http::fake([
            'app.shippingeasy.com/api/orders*' => Http::response('Server Error', 500),
        ]);

        $service = new ShippingEasyService($this->organization);

        try {
            $service->getOrders();
        } catch (\Exception $e) {
            // Expected
        }

        $this->assertDatabaseHas('shipping_easy_sync_logs', [
            'organization_id' => $this->organization->id,
            'operation' => ShippingEasySyncLog::OP_GET_ORDERS,
            'status' => ShippingEasySyncLog::STATUS_ERROR,
        ]);
    }

    public function test_hmac_signature_verification(): void
    {
        $secret = 'my_secret';
        $body = '{"order":{"id":1}}';
        $path = '/api/stores/abc/orders';
        $params = [
            'api_key' => 'my_key',
            'api_timestamp' => '1700000000',
        ];

        // Official SE algorithm: ksort params, http_build_query, join with &
        ksort($params);
        $plaintext = 'POST&'.$path.'&'.http_build_query($params).'&'.$body;
        $validSig = hash_hmac('sha256', $plaintext, $secret);

        $this->assertTrue(
            ShippingEasyService::verifyWebhookSignature($secret, 'POST', $path, $params, $body, $validSig)
        );

        $this->assertFalse(
            ShippingEasyService::verifyWebhookSignature($secret, 'POST', $path, $params, $body, 'invalid')
        );
    }

    public function test_retries_on_429_rate_limit(): void
    {
        $callCount = 0;
        Http::fake(function ($request) use (&$callCount) {
            $callCount++;
            if ($callCount <= 2) {
                return Http::response('Rate limited', 429);
            }

            return Http::response(['carriers' => [['name' => 'USPS']]], 200);
        });

        $service = new ShippingEasyService($this->organization);
        $result = $service->getCarriers();

        $this->assertArrayHasKey('carriers', $result);
        $this->assertEquals(3, $callCount);
    }

    public function test_throws_after_max_retries_on_429(): void
    {
        Http::fake(function () {
            return Http::response('Rate limited', 429);
        });

        $service = new ShippingEasyService($this->organization);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('rate limit exceeded');
        $service->getCarriers();
    }

    public function test_ensure_available_throws_when_not_configured(): void
    {
        $otherOrg = Organization::factory()->create();
        $service = new ShippingEasyService($otherOrg);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not configured');
        $service->testConnection();
    }
}
