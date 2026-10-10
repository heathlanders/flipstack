<?php

namespace Tests\Feature\Fulfillment;

use App\Jobs\Fulfillment\ProcessShippingEasyWebhook;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\InventoryLocation;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Services\Fulfillment\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ShippingEasyWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Team $team;

    protected IntegrationCredential $credential;

    protected InventoryLocation $location;

    protected string $apiKey = 'test_api_key';

    protected string $apiSecret = 'test_api_secret';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->organization = Organization::factory()->create([
            'sku_prefix' => 'SEW',
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
            'client_id' => $this->apiKey,
            'client_secret' => $this->apiSecret,
            'is_active' => true,
            'config' => ['store_api_key' => 'test_store'],
        ]);

        $this->location = InventoryLocation::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'name' => 'Warehouse',
            'code' => 'WHW',
            'postal_code' => '90210',
            'is_active' => true,
        ]);
    }

    /**
     * Sign a webhook request using the official SE algorithm.
     * SE sends auth as query params with ksort'd http_build_query in the signature.
     */
    protected function signRequest(array $payload): array
    {
        $body = json_encode($payload);
        $timestamp = (string) time();
        $path = '/webhooks/shippingeasy';

        // Official SE algorithm: ksort params, http_build_query, join with &
        $params = [
            'api_key' => $this->apiKey,
            'api_timestamp' => $timestamp,
        ];
        ksort($params);

        $parts = ['POST', $path, http_build_query($params)];
        $normalizedBody = str_replace('\\/', '/', $body);
        if ($normalizedBody !== '' && $normalizedBody !== 'null') {
            $parts[] = $normalizedBody;
        }

        $signature = hash_hmac('sha256', implode('&', $parts), $this->apiSecret);

        return [
            'payload' => $payload,
            'queryParams' => [
                'api_key' => $this->apiKey,
                'api_timestamp' => $timestamp,
                'api_signature' => $signature,
            ],
        ];
    }

    /**
     * Build a payload matching the actual SE callback format.
     */
    protected function buildSePayload(string $workflowState, array $shipmentOverrides = []): array
    {
        return [
            'shipment' => array_merge([
                'id' => '12345',
                'tracking_number' => '9400111899223100001234',
                'carrier_key' => 'USPS',
                'carrier_service_key' => 'USPS_PRIORITY',
                'shipment_cost' => 750,
                'ship_date' => '2026-03-29',
                'workflow_state' => $workflowState,
                'weight_in_ounces' => 16,
                'orders' => [
                    ['id' => '100', 'external_order_identifier' => 'ORD-001'],
                ],
            ], $shipmentOverrides),
        ];
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

    protected function createOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'marketplace' => 'ebay',
            'marketplace_order_id' => 'TEST-'.rand(100000, 999999),
            'status' => Order::STATUS_SHIPPED,
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

    public function test_accepts_valid_signature_with_workflow_state(): void
    {
        $payload = $this->buildSePayload('label_ready');
        $req = $this->signRequest($payload);

        $this->postJson('/webhooks/shippingeasy?'.http_build_query($req['queryParams']), $req['payload'])
            ->assertOk()
            ->assertJson(['status' => 'accepted']);
    }

    public function test_rejects_missing_auth(): void
    {
        $this->postJson('/webhooks/shippingeasy', $this->buildSePayload('label_ready'))
            ->assertStatus(401)
            ->assertJson(['error' => 'Missing authentication parameters']);
    }

    public function test_rejects_unknown_api_key(): void
    {
        $queryParams = http_build_query([
            'api_key' => 'unknown',
            'api_timestamp' => (string) time(),
            'api_signature' => 'fake',
        ]);

        $this->postJson('/webhooks/shippingeasy?'.$queryParams, $this->buildSePayload('label_ready'))
            ->assertStatus(401)
            ->assertJson(['error' => 'Unknown API key']);
    }

    public function test_rejects_invalid_signature(): void
    {
        $queryParams = http_build_query([
            'api_key' => $this->apiKey,
            'api_timestamp' => (string) time(),
            'api_signature' => 'bad_signature',
        ]);

        $this->postJson('/webhooks/shippingeasy?'.$queryParams, $this->buildSePayload('label_ready'))
            ->assertStatus(401)
            ->assertJson(['error' => 'Invalid signature']);
    }

    public function test_dispatches_job_for_label_ready(): void
    {
        $payload = $this->buildSePayload('label_ready');
        $req = $this->signRequest($payload);

        $this->postJson('/webhooks/shippingeasy?'.http_build_query($req['queryParams']), $req['payload'])
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        Queue::assertPushed(ProcessShippingEasyWebhook::class);
    }

    public function test_dispatches_job_for_label_printed(): void
    {
        $payload = $this->buildSePayload('label_printed');
        $req = $this->signRequest($payload);

        $this->postJson('/webhooks/shippingeasy?'.http_build_query($req['queryParams']), $req['payload'])
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        Queue::assertPushed(ProcessShippingEasyWebhook::class);
    }

    public function test_dispatches_job_for_cancelled(): void
    {
        $payload = $this->buildSePayload('cancelled');
        $req = $this->signRequest($payload);

        $this->postJson('/webhooks/shippingeasy?'.http_build_query($req['queryParams']), $req['payload'])
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        Queue::assertPushed(ProcessShippingEasyWebhook::class);
    }

    public function test_dispatches_job_for_drop_shipped(): void
    {
        $payload = $this->buildSePayload('drop_shipped');
        $req = $this->signRequest($payload);

        $this->postJson('/webhooks/shippingeasy?'.http_build_query($req['queryParams']), $req['payload'])
            ->assertOk()
            ->assertJson(['status' => 'accepted']);

        Queue::assertPushed(ProcessShippingEasyWebhook::class);
    }

    public function test_ignores_label_pending(): void
    {
        $payload = $this->buildSePayload('label_pending');
        $req = $this->signRequest($payload);

        $this->postJson('/webhooks/shippingeasy?'.http_build_query($req['queryParams']), $req['payload'])
            ->assertOk()
            ->assertJson(['status' => 'ignored']);

        Queue::assertNotPushed(ProcessShippingEasyWebhook::class);
    }

    public function test_ignores_unknown_workflow_state(): void
    {
        $payload = $this->buildSePayload('some_future_state');
        $req = $this->signRequest($payload);

        $this->postJson('/webhooks/shippingeasy?'.http_build_query($req['queryParams']), $req['payload'])
            ->assertOk()
            ->assertJson(['status' => 'ignored']);

        Queue::assertNotPushed(ProcessShippingEasyWebhook::class);
    }

    public function test_job_handles_label_purchased_with_se_format(): void
    {
        Queue::fake([]);

        $order = $this->createOrder(['status' => Order::STATUS_PACKED]);
        $shipment = $this->createShipment($order, [
            'status' => Shipment::STATUS_PENDING,
            'shippingeasy_shipment_id' => '12345',
        ]);

        $job = new ProcessShippingEasyWebhook('label.purchased', [
            'shipment' => [
                'id' => '12345',
                'tracking_number' => '9400111899223100005678',
                'tracking_url' => 'https://tracking.example.com/5678',
                'label_url' => 'https://labels.example.com/label.pdf',
                'shipment_cost' => 750,
                'carrier_key' => 'USPS',
                'carrier_service_key' => 'USPS_PRIORITY',
                'workflow_state' => 'label_ready',
                'orders' => [
                    ['id' => '100', 'external_order_identifier' => 'ORD-001'],
                ],
            ],
        ], $this->organization->id);

        $job->handle(app(TrackingService::class));

        $shipment->refresh();
        $this->assertEquals('9400111899223100005678', $shipment->tracking_number);
        $this->assertEquals('https://labels.example.com/label.pdf', $shipment->label_url);
        $this->assertEquals(Shipment::STATUS_LABEL_PURCHASED, $shipment->status);
        $this->assertEquals(7.50, $shipment->label_cost);
        $this->assertEquals('USPS', $shipment->carrier);
        $this->assertEquals('USPS_PRIORITY', $shipment->service);
    }

    public function test_job_finds_shipment_by_nested_order_id(): void
    {
        Queue::fake([]);

        $order = $this->createOrder(['status' => Order::STATUS_PACKED]);
        $shipment = $this->createShipment($order, [
            'status' => Shipment::STATUS_PENDING,
            'shippingeasy_order_id' => '100',
        ]);

        $job = new ProcessShippingEasyWebhook('label.purchased', [
            'shipment' => [
                'id' => 'new_shipment_id',
                'tracking_number' => '1234567890',
                'shipment_cost' => 500,
                'workflow_state' => 'label_ready',
                'orders' => [
                    ['id' => '100', 'external_order_identifier' => 'ORD-001'],
                ],
            ],
        ], $this->organization->id);

        $job->handle(app(TrackingService::class));

        $shipment->refresh();
        $this->assertEquals('1234567890', $shipment->tracking_number);
    }

    public function test_job_handles_cancelled_webhook(): void
    {
        Queue::fake([]);

        $order = $this->createOrder();
        $shipment = $this->createShipment($order, [
            'status' => Shipment::STATUS_LABEL_PURCHASED,
            'shippingeasy_shipment_id' => 'se_cancel_123',
        ]);

        $job = new ProcessShippingEasyWebhook('shipment.cancelled', [
            'shipment' => [
                'id' => 'se_cancel_123',
                'workflow_state' => 'cancelled',
                'orders' => [
                    ['id' => '200', 'external_order_identifier' => 'ORD-002'],
                ],
            ],
        ], $this->organization->id);

        $job->handle(app(TrackingService::class));

        $shipment->refresh();
        $this->assertEquals(Shipment::STATUS_CANCELLED, $shipment->status);
    }

    public function test_job_handles_tracking_update(): void
    {
        Queue::fake([]);

        $order = $this->createOrder(['status' => Order::STATUS_SHIPPED]);
        $shipment = $this->createShipment($order, [
            'status' => Shipment::STATUS_SHIPPED,
            'shippingeasy_shipment_id' => 'se_track_123',
            'tracking_number' => '9400111899223100001234',
        ]);

        $job = new ProcessShippingEasyWebhook('shipment.shipped', [
            'shipment' => [
                'id' => 'se_track_123',
                'tracking_number' => '9400111899223100001234',
                'workflow_state' => 'drop_shipped',
                'carrier_key' => 'USPS',
                'orders' => [
                    ['id' => '300', 'external_order_identifier' => 'ORD-003'],
                ],
            ],
        ], $this->organization->id);

        $job->handle(app(TrackingService::class));

        $shipment->refresh();
        // TrackingService maps "drop_shipped"/"shipment.shipped" to in_transit
        $this->assertEquals(Shipment::STATUS_IN_TRANSIT, $shipment->status);
    }

    public function test_job_handles_label_failed(): void
    {
        Queue::fake([]);

        $order = $this->createOrder();
        $shipment = $this->createShipment($order, [
            'status' => Shipment::STATUS_PENDING,
            'shippingeasy_order_id' => 'se_fail',
        ]);

        $job = new ProcessShippingEasyWebhook('label.failed', [
            'shipment' => [
                'order_id' => 'se_fail',
                'error_message' => 'Invalid address',
            ],
        ], $this->organization->id);

        $job->handle(app(TrackingService::class));

        $shipment->refresh();
        $this->assertEquals(Shipment::STATUS_FAILED, $shipment->status);
    }

    public function test_job_handles_unknown_shipment_gracefully(): void
    {
        Queue::fake([]);

        $job = new ProcessShippingEasyWebhook('shipment.shipped', [
            'shipment' => [
                'id' => 'nonexistent',
                'tracking_number' => '0000',
                'workflow_state' => 'drop_shipped',
                'orders' => [],
            ],
        ], $this->organization->id);

        // Should not throw
        $job->handle(app(TrackingService::class));
        $this->assertTrue(true);
    }
}
