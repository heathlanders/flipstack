<?php

namespace Tests\Feature\Tracking;

use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Models\Inventory\InventoryLocation;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Services\Tracking\Adapters\EbayTrackingAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EbayTrackingAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create([
            'sku_prefix' => 'EBT',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        Cache::flush();
    }

    public function test_supports_returns_false_when_order_not_ebay(): void
    {
        [, $shipment] = $this->createOrderWithShipment(marketplace: 'shopify', withCredential: true);

        $adapter = app(EbayTrackingAdapter::class);

        $this->assertFalse($adapter->supports($shipment));
    }

    public function test_supports_returns_false_when_no_active_ebay_credential(): void
    {
        [, $shipment] = $this->createOrderWithShipment(marketplace: 'ebay', withCredential: false);

        $adapter = app(EbayTrackingAdapter::class);

        $this->assertFalse($adapter->supports($shipment));
    }

    public function test_supports_returns_true_for_ebay_order_with_credential(): void
    {
        [, $shipment] = $this->createOrderWithShipment(marketplace: 'ebay', withCredential: true);

        $adapter = app(EbayTrackingAdapter::class);

        $this->assertTrue($adapter->supports($shipment));
    }

    public function test_fetch_returns_delivered_when_actual_delivery_time_present(): void
    {
        [$order, $shipment, $credential] = $this->createOrderWithShipment(marketplace: 'ebay', withCredential: true);
        $this->seedActiveOAuthToken($credential);

        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response($this->buildDeliveredXml(
                $shipment->tracking_number,
                shippedAt: '2026-05-01T15:00:00.000Z',
                deliveredAt: '2026-05-07T18:30:00.000Z'
            ), 200),
        ]);

        $adapter = app(EbayTrackingAdapter::class);
        $result = $adapter->fetch($shipment);

        $this->assertSame('delivered', $result->latestStatus);
        $this->assertNotNull($result->deliveredAt);
        $this->assertSame('2026-05-07', $result->deliveredAt->format('Y-m-d'));
        $this->assertCount(1, $result->events);
        $this->assertSame('delivered', $result->events[0]['status']);
    }

    public function test_fetch_returns_in_transit_when_only_shipped_time_present(): void
    {
        [$order, $shipment, $credential] = $this->createOrderWithShipment(marketplace: 'ebay', withCredential: true);
        $this->seedActiveOAuthToken($credential);

        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response($this->buildShippedOnlyXml(
                $shipment->tracking_number,
                shippedAt: '2026-05-01T15:00:00.000Z'
            ), 200),
        ]);

        $adapter = app(EbayTrackingAdapter::class);
        $result = $adapter->fetch($shipment);

        $this->assertSame('in_transit', $result->latestStatus);
        $this->assertNull($result->deliveredAt);
    }

    public function test_fetch_returns_empty_on_http_error(): void
    {
        [$order, $shipment, $credential] = $this->createOrderWithShipment(marketplace: 'ebay', withCredential: true);
        $this->seedActiveOAuthToken($credential);

        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response('Internal Server Error', 500),
        ]);

        $adapter = app(EbayTrackingAdapter::class);
        $result = $adapter->fetch($shipment);

        $this->assertTrue($result->isEmpty());
        $this->assertNull($result->deliveredAt);
    }

    public function test_empty_trading_api_response_is_not_cached(): void
    {
        [$order, $shipment, $credential] = $this->createOrderWithShipment(marketplace: 'ebay', withCredential: true);
        $this->seedActiveOAuthToken($credential);

        $emptyXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<GetOrdersResponse xmlns="urn:ebay:apis:eBLBaseComponents">
  <Ack>Success</Ack>
  <OrderArray />
</GetOrdersResponse>
XML;

        $deliveredXml = $this->buildDeliveredXml(
            $shipment->tracking_number,
            shippedAt: '2026-05-01T15:00:00.000Z',
            deliveredAt: '2026-05-07T18:30:00.000Z'
        );

        $callCount = 0;
        Http::fake([
            'api.ebay.com/ws/api.dll' => function () use (&$callCount, $emptyXml, $deliveredXml) {
                $callCount++;

                return $callCount === 1
                    ? Http::response($emptyXml, 200)
                    : Http::response($deliveredXml, 200);
            },
        ]);

        $adapter = app(EbayTrackingAdapter::class);

        $first = $adapter->fetch($shipment);
        $this->assertTrue($first->isEmpty(), 'first call (empty XML) should yield empty result');

        // Second fetch must re-hit eBay because we did NOT cache the empty body.
        $second = $adapter->fetch($shipment);
        $this->assertSame('delivered', $second->latestStatus);
        $this->assertNotNull($second->deliveredAt);
        $this->assertSame(2, $callCount, 'Trading API should be called twice — empty response was not cached');
    }

    public function test_presumed_delivery_when_fulfilled_and_max_date_elapsed(): void
    {
        [$order, $shipment, $credential] = $this->createOrderWithShipment(marketplace: 'ebay', withCredential: true);
        $this->seedActiveOAuthToken($credential);

        // Order was imported with FULFILLED status and a max delivery date
        // already in the past — eBay's signal that delivery is expected to
        // have happened.
        $maxDate = '2026-05-06T07:00:00.000Z';
        $order->update([
            'marketplace_status' => 'FULFILLED',
            'marketplace_data' => [
                'fulfillmentStartInstructions' => [
                    ['maxEstimatedDeliveryDate' => $maxDate],
                ],
            ],
        ]);

        // Trading API has no ActualDeliveryTime (self-shipped USPS label).
        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response($this->buildEmptyXml(), 200),
        ]);
        $this->presumedDeliveryHttpFakeForFollowingCalls();

        $adapter = app(EbayTrackingAdapter::class);
        $result = $adapter->fetch($shipment->fresh('order'));

        $this->assertSame('delivered', $result->latestStatus);
        $this->assertNotNull($result->deliveredAt);
        $this->assertSame('2026-05-06', $result->deliveredAt->format('Y-m-d'));
        $this->assertCount(1, $result->events);
        $this->assertStringContainsString('presumed', $result->events[0]['description']);
    }

    public function test_no_presumed_delivery_when_max_date_in_future(): void
    {
        [$order, $shipment, $credential] = $this->createOrderWithShipment(marketplace: 'ebay', withCredential: true);
        $this->seedActiveOAuthToken($credential);

        $order->update([
            'marketplace_status' => 'FULFILLED',
            'marketplace_data' => [
                'fulfillmentStartInstructions' => [
                    ['maxEstimatedDeliveryDate' => now()->addDays(5)->toIso8601String()],
                ],
            ],
        ]);

        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response($this->buildEmptyXml(), 200),
        ]);

        $adapter = app(EbayTrackingAdapter::class);
        $result = $adapter->fetch($shipment->fresh('order'));

        $this->assertTrue($result->isEmpty(), 'Should not presume delivery while max delivery date is still in the future');
    }

    public function test_no_presumed_delivery_when_not_fulfilled(): void
    {
        [$order, $shipment, $credential] = $this->createOrderWithShipment(marketplace: 'ebay', withCredential: true);
        $this->seedActiveOAuthToken($credential);

        $order->update([
            'marketplace_status' => 'IN_PROGRESS',
            'marketplace_data' => [
                'fulfillmentStartInstructions' => [
                    ['maxEstimatedDeliveryDate' => now()->subDays(5)->toIso8601String()],
                ],
            ],
        ]);

        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response($this->buildEmptyXml(), 200),
        ]);

        $adapter = app(EbayTrackingAdapter::class);
        $result = $adapter->fetch($shipment->fresh('order'));

        $this->assertTrue($result->isEmpty(), 'Should not presume delivery on non-FULFILLED orders');
    }

    public function test_fetch_matches_correct_package_when_multiple_present(): void
    {
        [$order, $shipment, $credential] = $this->createOrderWithShipment(marketplace: 'ebay', withCredential: true);
        $this->seedActiveOAuthToken($credential);

        $otherTracking = '1Z999AA9999999999';

        Http::fake([
            'api.ebay.com/ws/api.dll' => Http::response($this->buildMultiPackageXml(
                primaryTracking: $shipment->tracking_number,
                primaryDeliveredAt: '2026-05-07T18:30:00.000Z',
                otherTracking: $otherTracking,
                otherDeliveredAt: '2026-04-01T12:00:00.000Z'
            ), 200),
        ]);

        $adapter = app(EbayTrackingAdapter::class);
        $result = $adapter->fetch($shipment);

        $this->assertNotNull($result->deliveredAt);
        $this->assertSame('2026-05-07', $result->deliveredAt->format('Y-m-d'));
    }

    protected function presumedDeliveryHttpFakeForFollowingCalls(): void
    {
        // No-op helper retained for future per-test extension.
    }

    protected function buildEmptyXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<GetOrdersResponse xmlns="urn:ebay:apis:eBLBaseComponents">
  <Ack>Success</Ack>
  <OrderArray />
</GetOrdersResponse>
XML;
    }

    protected function createOrderWithShipment(string $marketplace, bool $withCredential): array
    {
        $credential = null;
        if ($withCredential) {
            $credential = IntegrationCredential::create([
                'organization_id' => $this->organization->id,
                'team_id' => $this->team->id,
                'platform' => $marketplace,
                'environment' => 'production',
                'name' => ucfirst($marketplace).' Test',
                'is_active' => true,
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
                'config' => [],
            ]);
        }

        $location = InventoryLocation::firstOrCreate([
            'organization_id' => $this->organization->id,
            'code' => 'EBT-LOC',
        ], [
            'name' => 'Test Location',
            'is_active' => true,
            'is_ship_from' => true,
        ]);

        $order = Order::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'credential_id' => $credential?->id,
            'marketplace' => $marketplace,
            'marketplace_order_id' => 'TEST-EBAY-'.rand(100000, 999999),
            'status' => Order::STATUS_SHIPPED,
            'buyer_username' => 'test@example.com',
            'buyer_name' => 'Test Buyer',
            'shipping_address_name' => 'Test Buyer',
            'shipping_address_line1' => '123 Test St',
            'shipping_city' => 'Testville',
            'shipping_state' => 'CA',
            'shipping_postal_code' => '90210',
            'shipping_country' => 'US',
            'total_amount' => 29.99,
            'currency' => 'USD',
            'shipped_at' => now(),
        ]);

        $shipment = Shipment::create([
            'organization_id' => $this->organization->id,
            'order_id' => $order->id,
            'location_id' => $location->id,
            'shipment_number' => 'SHP-EBT-'.rand(100000, 999999),
            'status' => Shipment::STATUS_SHIPPED,
            'tracking_number' => '9400111899223'.rand(100000000, 999999999),
            'carrier' => 'USPS',
            'shipping_provider' => 'ebay',
            'tracking_uploaded_to_marketplace' => true,
            'shipped_at' => now()->subDays(3),
            'ship_from_name' => 'Test Warehouse',
            'ship_from_address_line1' => '100 Warehouse Blvd',
            'ship_from_city' => 'Testville',
            'ship_from_state' => 'CA',
            'ship_from_postal_code' => '90210',
        ]);

        return [$order, $shipment, $credential];
    }

    protected function seedActiveOAuthToken(IntegrationCredential $credential): void
    {
        OAuthToken::create([
            'organization_id' => $credential->organization_id,
            'team_id' => $credential->team_id,
            'integration_credential_id' => $credential->id,
            'platform' => 'ebay',
            'environment' => $credential->environment,
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);
    }

    protected function buildDeliveredXml(string $tracking, string $shippedAt, string $deliveredAt): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<GetOrdersResponse xmlns="urn:ebay:apis:eBLBaseComponents">
  <Ack>Success</Ack>
  <OrderArray>
    <Order>
      <TransactionArray>
        <Transaction>
          <ShippingDetails>
            <ShippingPackageInfo>
              <ShipmentTrackingNumber>{$tracking}</ShipmentTrackingNumber>
              <ShippedTime>{$shippedAt}</ShippedTime>
              <ActualDeliveryTime>{$deliveredAt}</ActualDeliveryTime>
            </ShippingPackageInfo>
          </ShippingDetails>
        </Transaction>
      </TransactionArray>
    </Order>
  </OrderArray>
</GetOrdersResponse>
XML;
    }

    protected function buildShippedOnlyXml(string $tracking, string $shippedAt): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<GetOrdersResponse xmlns="urn:ebay:apis:eBLBaseComponents">
  <Ack>Success</Ack>
  <OrderArray>
    <Order>
      <TransactionArray>
        <Transaction>
          <ShippingDetails>
            <ShippingPackageInfo>
              <ShipmentTrackingNumber>{$tracking}</ShipmentTrackingNumber>
              <ShippedTime>{$shippedAt}</ShippedTime>
            </ShippingPackageInfo>
          </ShippingDetails>
        </Transaction>
      </TransactionArray>
    </Order>
  </OrderArray>
</GetOrdersResponse>
XML;
    }

    protected function buildMultiPackageXml(string $primaryTracking, string $primaryDeliveredAt, string $otherTracking, string $otherDeliveredAt): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<GetOrdersResponse xmlns="urn:ebay:apis:eBLBaseComponents">
  <Ack>Success</Ack>
  <OrderArray>
    <Order>
      <TransactionArray>
        <Transaction>
          <ShippingDetails>
            <ShippingPackageInfo>
              <ShipmentTrackingNumber>{$otherTracking}</ShipmentTrackingNumber>
              <ActualDeliveryTime>{$otherDeliveredAt}</ActualDeliveryTime>
            </ShippingPackageInfo>
            <ShippingPackageInfo>
              <ShipmentTrackingNumber>{$primaryTracking}</ShipmentTrackingNumber>
              <ActualDeliveryTime>{$primaryDeliveredAt}</ActualDeliveryTime>
            </ShippingPackageInfo>
          </ShippingDetails>
        </Transaction>
      </TransactionArray>
    </Order>
  </OrderArray>
</GetOrdersResponse>
XML;
    }
}
