<?php

namespace Tests\Feature\Fulfillment;

use App\Jobs\Fulfillment\UploadTrackingToAmazon;
use App\Jobs\Fulfillment\UploadTrackingToEbay;
use App\Jobs\Fulfillment\UploadTrackingToShopify;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\InventoryLocation;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use App\Services\Fulfillment\FulfillmentAuditService;
use App\Services\Fulfillment\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MultiMarketplaceTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->setUpFixtures();
    }

    protected function setUpFixtures(): void
    {
        $this->organization = Organization::factory()->create([
            'sku_prefix' => 'TRK',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $role = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-trk',
            'is_system' => true,
            'permissions' => ['inventory.view'],
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
    }

    protected function createOrderWithShipment(string $marketplace): array
    {
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

        $location = InventoryLocation::firstOrCreate([
            'organization_id' => $this->organization->id,
            'code' => 'TST',
        ], [
            'name' => 'Test Location',
            'code' => 'TST',
            'is_active' => true,
            'is_ship_from' => true,
        ]);

        $order = Order::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'credential_id' => $credential->id,
            'marketplace' => $marketplace,
            'marketplace_order_id' => 'TEST-'.strtoupper($marketplace).'-'.rand(100, 999),
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
            'shipment_number' => 'SHP-'.now()->format('Ymd').'-'.rand(1000, 9999),
            'status' => Shipment::STATUS_SHIPPED,
            'tracking_number' => '9400111899223'.rand(100000000, 999999999),
            'carrier' => 'USPS',
            'tracking_uploaded_to_marketplace' => false,
            'shipped_at' => now(),
            'ship_from_name' => 'Test Warehouse',
            'ship_from_address_line1' => '100 Warehouse Blvd',
            'ship_from_city' => 'Testville',
            'ship_from_state' => 'CA',
            'ship_from_postal_code' => '90210',
        ]);

        return [$order, $shipment, $credential];
    }

    public function test_dispatch_tracking_upload_for_ebay(): void
    {
        [$order, $shipment] = $this->createOrderWithShipment('ebay');

        $trackingService = new TrackingService(new FulfillmentAuditService);
        $trackingService->dispatchTrackingUpload($shipment);

        Queue::assertPushed(UploadTrackingToEbay::class);
        Queue::assertNotPushed(UploadTrackingToShopify::class);
        Queue::assertNotPushed(UploadTrackingToAmazon::class);
    }

    public function test_dispatch_tracking_upload_for_shopify(): void
    {
        [$order, $shipment] = $this->createOrderWithShipment('shopify');

        $trackingService = new TrackingService(new FulfillmentAuditService);
        $trackingService->dispatchTrackingUpload($shipment);

        Queue::assertPushed(UploadTrackingToShopify::class);
        Queue::assertNotPushed(UploadTrackingToEbay::class);
        Queue::assertNotPushed(UploadTrackingToAmazon::class);
    }

    public function test_dispatch_tracking_upload_for_amazon(): void
    {
        [$order, $shipment] = $this->createOrderWithShipment('amazon');

        $trackingService = new TrackingService(new FulfillmentAuditService);
        $trackingService->dispatchTrackingUpload($shipment);

        Queue::assertPushed(UploadTrackingToAmazon::class);
        Queue::assertNotPushed(UploadTrackingToEbay::class);
        Queue::assertNotPushed(UploadTrackingToShopify::class);
    }

    public function test_dispatch_skips_already_uploaded(): void
    {
        [$order, $shipment] = $this->createOrderWithShipment('ebay');
        $shipment->update(['tracking_uploaded_to_marketplace' => true]);

        $trackingService = new TrackingService(new FulfillmentAuditService);
        $trackingService->dispatchTrackingUpload($shipment);

        Queue::assertNotPushed(UploadTrackingToEbay::class);
    }

    public function test_retry_failed_tracking_uploads(): void
    {
        $this->createOrderWithShipment('ebay');
        $this->createOrderWithShipment('shopify');
        $this->createOrderWithShipment('amazon');

        $trackingService = new TrackingService(new FulfillmentAuditService);
        $count = $trackingService->retryFailedTrackingUploads();

        $this->assertEquals(3, $count);
        Queue::assertPushed(UploadTrackingToEbay::class, 1);
        Queue::assertPushed(UploadTrackingToShopify::class, 1);
        Queue::assertPushed(UploadTrackingToAmazon::class, 1);
    }

    public function test_dispatch_ignores_unknown_marketplace(): void
    {
        [$order, $shipment] = $this->createOrderWithShipment('ebay');
        $order->update(['marketplace' => 'unknown']);

        $trackingService = new TrackingService(new FulfillmentAuditService);
        $trackingService->dispatchTrackingUpload($shipment->fresh());

        Queue::assertNotPushed(UploadTrackingToEbay::class);
        Queue::assertNotPushed(UploadTrackingToShopify::class);
        Queue::assertNotPushed(UploadTrackingToAmazon::class);
    }
}
