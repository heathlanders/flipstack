<?php

namespace Tests\Feature\Fulfillment;

use App\Jobs\Fulfillment\SyncRefundToMarketplace;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\ReturnRequest;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use App\Services\Fulfillment\FulfillmentAuditService;
use App\Services\Fulfillment\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceReturnSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected ReturnService $returnService;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->setUpFixtures();
        $this->returnService = new ReturnService(new FulfillmentAuditService);
    }

    protected function setUpFixtures(): void
    {
        $this->organization = Organization::factory()->create([
            'sku_prefix' => 'RET',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $role = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-ret',
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

    protected function createOrderForMarketplace(string $marketplace): Order
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

        return Order::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'credential_id' => $credential->id,
            'marketplace' => $marketplace,
            'marketplace_order_id' => 'RET-'.strtoupper($marketplace).'-001',
            'status' => Order::STATUS_DELIVERED,
            'buyer_username' => 'buyer@example.com',
            'buyer_name' => 'Test Buyer',
            'shipping_address_name' => 'Test Buyer',
            'shipping_address_line1' => '789 Return Rd',
            'shipping_city' => 'Returnville',
            'shipping_state' => 'TX',
            'shipping_postal_code' => '75001',
            'shipping_country' => 'US',
            'total_amount' => 39.99,
            'currency' => 'USD',
        ]);
    }

    protected function createInspectedReturn(Order $order): ReturnRequest
    {
        $return = ReturnRequest::create([
            'organization_id' => $order->organization_id,
            'order_id' => $order->id,
            'rma_number' => ReturnRequest::generateRmaNumber(),
            'status' => ReturnRequest::STATUS_INSPECTED,
            'reason' => 'DEFECTIVE',
            'source' => ReturnRequest::SOURCE_BUYER,
            'quantity' => 1,
            'refund_amount' => $order->total_amount,
            'restockable' => true,
        ]);

        $order->update(['status' => Order::STATUS_RETURN_INITIATED]);

        return $return;
    }

    public function test_process_refund_dispatches_marketplace_sync_for_ebay(): void
    {
        $order = $this->createOrderForMarketplace('ebay');
        $return = $this->createInspectedReturn($order);

        $this->returnService->processRefund($return, $this->user);

        Queue::assertPushed(SyncRefundToMarketplace::class, function ($job) {
            return true;
        });
    }

    public function test_process_refund_dispatches_marketplace_sync_for_shopify(): void
    {
        $order = $this->createOrderForMarketplace('shopify');
        $return = $this->createInspectedReturn($order);

        $this->returnService->processRefund($return, $this->user);

        Queue::assertPushed(SyncRefundToMarketplace::class);
    }

    public function test_process_refund_dispatches_marketplace_sync_for_amazon(): void
    {
        $order = $this->createOrderForMarketplace('amazon');
        $return = $this->createInspectedReturn($order);

        $this->returnService->processRefund($return, $this->user);

        Queue::assertPushed(SyncRefundToMarketplace::class);
    }

    public function test_process_refund_does_not_dispatch_for_no_credential(): void
    {
        $order = Order::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'credential_id' => null,
            'marketplace' => 'manual',
            'marketplace_order_id' => 'MANUAL-001',
            'status' => Order::STATUS_DELIVERED,
            'buyer_username' => 'buyer@example.com',
            'buyer_name' => 'Test Buyer',
            'shipping_address_name' => 'Test Buyer',
            'shipping_address_line1' => '789 Return Rd',
            'shipping_city' => 'Returnville',
            'shipping_state' => 'TX',
            'shipping_postal_code' => '75001',
            'shipping_country' => 'US',
            'total_amount' => 39.99,
            'currency' => 'USD',
        ]);

        $return = $this->createInspectedReturn($order);

        $this->returnService->processRefund($return, $this->user);

        Queue::assertNotPushed(SyncRefundToMarketplace::class);
    }

    public function test_process_refund_updates_return_status(): void
    {
        $order = $this->createOrderForMarketplace('shopify');
        $return = $this->createInspectedReturn($order);

        $this->returnService->processRefund($return, $this->user);

        $return->refresh();
        $this->assertEquals(ReturnRequest::STATUS_REFUNDED, $return->status);
        $this->assertNotNull($return->refunded_at);

        $order->refresh();
        $this->assertEquals(Order::STATUS_RETURNED, $order->status);
    }
}
