<?php

namespace Tests\Feature\Integration;

use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use App\Services\EbayConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class EbayConnectTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected User $ownerUser;

    protected User $nonOwnerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $ownerRole = Role::factory()->create([
            'name' => 'Organization Owner',
            'slug' => 'organization_owner',
            'is_system' => true,
            'permissions' => ['organization.manage'],
        ]);

        $buyerRole = Role::factory()->create([
            'name' => 'Buyer',
            'slug' => 'buyer',
            'is_system' => true,
            'permissions' => ['inventory.view'],
        ]);

        $this->ownerUser = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->ownerUser->roles()->attach($ownerRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        $this->nonOwnerUser = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->nonOwnerUser->roles()->attach($buyerRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        foreach ([$this->ownerUser, $this->nonOwnerUser] as $user) {
            OnboardingProgress::create([
                'user_id' => $user->id,
                'organization_id' => $this->organization->id,
                'steps_completed' => [],
                'current_step' => 'completed',
                'data' => [],
                'started_at' => now(),
                'completed_at' => now(),
            ]);
        }
    }

    public function test_connect_ebay_returns_redirect_url_when_configured(): void
    {
        $this->mockEbayConfigured();

        $response = $this->actingAs($this->ownerUser)
            ->postJson(route('integrations.ebay.connect'));

        $response->assertOk()
            ->assertJsonStructure(['redirect_url']);

        $this->assertStringContainsString('ebay.com', $response->json('redirect_url'));
    }

    public function test_connect_ebay_creates_credential_record(): void
    {
        $this->mockEbayConfigured();

        $this->actingAs($this->ownerUser)
            ->postJson(route('integrations.ebay.connect'));

        $this->assertDatabaseHas('integration_credentials', [
            'organization_id' => $this->organization->id,
            'platform' => 'ebay',
        ]);
    }

    public function test_connect_ebay_reuses_pending_credential_instead_of_creating_new(): void
    {
        $this->mockEbayConfigured();

        $this->actingAs($this->ownerUser)
            ->postJson(route('integrations.ebay.connect'));

        $this->actingAs($this->ownerUser)
            ->postJson(route('integrations.ebay.connect'));

        $this->actingAs($this->ownerUser)
            ->postJson(route('integrations.ebay.connect'));

        $this->assertSame(1, IntegrationCredential::where('organization_id', $this->organization->id)
            ->where('platform', 'ebay')
            ->count());
    }

    public function test_connect_ebay_creates_new_credential_when_existing_is_completed(): void
    {
        $this->mockEbayConfigured();

        IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'platform' => 'ebay',
            'environment' => 'sandbox',
            'name' => 'eBay Sandbox',
            'client_id' => 'app_level_credentials',
            'is_active' => true,
            'seller_username' => 'completed_seller',
        ]);

        $this->actingAs($this->ownerUser)
            ->postJson(route('integrations.ebay.connect'));

        $this->assertSame(2, IntegrationCredential::where('organization_id', $this->organization->id)
            ->where('platform', 'ebay')
            ->count());
    }

    public function test_connect_ebay_returns_422_when_not_configured(): void
    {
        $mock = $this->partialMock(EbayConfigService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        $response = $this->actingAs($this->ownerUser)
            ->postJson(route('integrations.ebay.connect'));

        $response->assertStatus(422)
            ->assertJson(['error' => 'eBay integration is not configured. Please contact the administrator.']);
    }

    public function test_connect_ebay_denied_for_non_owner(): void
    {
        $this->mockEbayConfigured();

        $response = $this->actingAs($this->nonOwnerUser)
            ->postJson(route('integrations.ebay.connect'));

        $response->assertForbidden();
    }

    public function test_connect_ebay_returns_422_when_user_has_no_organization(): void
    {
        $userWithoutOrg = User::factory()->create([
            'organization_id' => null,
        ]);
        OnboardingProgress::create([
            'user_id' => $userWithoutOrg->id,
            'organization_id' => $this->organization->id,
            'steps_completed' => [],
            'current_step' => 'completed',
            'data' => [],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($userWithoutOrg)
            ->postJson(route('integrations.ebay.connect'));

        $response->assertStatus(422)
            ->assertJson(['error' => 'You must belong to an organization to connect eBay.']);
    }

    protected function mockEbayConfigured(): void
    {
        $this->partialMock(EbayConfigService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('getEnvironment')->andReturn('sandbox');
            $mock->shouldReceive('getRuName')->andReturn('test-runame');
            $mock->shouldReceive('getClientId')->andReturn('test-client-id');
            $mock->shouldReceive('getClientSecret')->andReturn('test-secret');
        });
    }
}
