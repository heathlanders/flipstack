<?php

namespace Tests\Feature\Integration;

use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use App\Services\Shopify\ShopifyConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyConnectTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected Team $team;

    protected User $ownerUser;

    protected User $nonOwnerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

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
        $this->ownerUser->current_team_id = $this->team->id;
        $this->ownerUser->save();
        $this->team->users()->attach($this->ownerUser->id, ['role' => 'admin']);

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

    protected function mockShopifyRateLimiter(): void
    {
        $this->app->singleton(\App\Services\Shopify\ShopifyRateLimiter::class, function () {
            $mock = \Mockery::mock(\App\Services\Shopify\ShopifyRateLimiter::class);
            $mock->shouldReceive('waitIfNeeded')->andReturnNull();
            $mock->shouldReceive('recordResponse')->andReturnNull();
            $mock->shouldReceive('shouldThrottle')->andReturn(false);

            return $mock;
        });
    }

    protected function mockShopifyConfigured(): void
    {
        $this->mockShopifyRateLimiter();
        $this->partialMock(ShopifyConfigService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('getApiKey')->andReturn('test-api-key');
            $mock->shouldReceive('getApiSecret')->andReturn('test-api-secret');
            $mock->shouldReceive('getApiVersion')->andReturn('2024-10');
            $mock->shouldReceive('getScopesString')->andReturn('read_products,write_products');
            $mock->shouldReceive('getRedirectUri')->andReturn('https://localhost:8090/shopify/auth/callback');
        });
    }

    public function test_install_redirects_to_shopify_oauth_url(): void
    {
        $this->mockShopifyConfigured();

        // Pre-create credential so firstOrCreate finds it (client_id is required)
        IntegrationCredential::create([
            'organization_id' => $this->ownerUser->organization_id,
            'platform' => 'shopify',
            'environment' => 'production',
            'name' => 'Shopify Store',
            'is_active' => true,
            'team_id' => $this->team->id,
            'client_id' => 'test-api-key',
            'config' => ['shop_domain' => 'my-store.myshopify.com'],
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->get(route('shopify.auth.install', ['shop_domain' => 'my-store.myshopify.com']));

        $response->assertRedirect();
        $this->assertStringContainsString('my-store.myshopify.com/admin/oauth/authorize', $response->headers->get('Location'));
    }

    public function test_install_validates_shop_domain(): void
    {
        $this->mockShopifyConfigured();

        $response = $this->actingAs($this->ownerUser)
            ->get(route('shopify.auth.install', ['shop_domain' => 'invalid-domain']));

        $response->assertSessionHasErrors('shop_domain');
    }

    public function test_install_reuses_credential_for_same_shop_domain(): void
    {
        $this->mockShopifyConfigured();

        // Pre-create credential
        $credential = IntegrationCredential::create([
            'organization_id' => $this->ownerUser->organization_id,
            'platform' => 'shopify',
            'environment' => 'production',
            'name' => 'Shopify Store',
            'is_active' => true,
            'client_id' => 'test-api-key',
            'config' => ['shop_domain' => 'old-store.myshopify.com'],
        ]);

        // Same domain reuses existing credential
        $this->actingAs($this->ownerUser)
            ->get(route('shopify.auth.install', ['shop_domain' => 'old-store.myshopify.com']));

        $this->assertDatabaseCount('integration_credentials', 1);
        $credential->refresh();
        $this->assertEquals('old-store.myshopify.com', $credential->config['shop_domain']);
    }

    public function test_install_creates_new_credential_for_different_shop_domain(): void
    {
        $this->mockShopifyConfigured();

        // Pre-create credential for one store
        IntegrationCredential::create([
            'organization_id' => $this->ownerUser->organization_id,
            'platform' => 'shopify',
            'environment' => 'production',
            'name' => 'Shopify Store',
            'is_active' => true,
            'client_id' => 'test-api-key',
            'config' => ['shop_domain' => 'old-store.myshopify.com'],
        ]);

        // Different domain creates a new credential
        $this->actingAs($this->ownerUser)
            ->get(route('shopify.auth.install', ['shop_domain' => 'new-store.myshopify.com']));

        $this->assertDatabaseCount('integration_credentials', 2);
        $this->assertDatabaseHas('integration_credentials', [
            'organization_id' => $this->ownerUser->organization_id,
            'platform' => 'shopify',
            'name' => 'new-store.myshopify.com',
        ]);
    }

    public function test_callback_exchanges_code_for_token(): void
    {
        $this->mockShopifyConfigured();

        // Create credential first
        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'platform' => 'shopify',
            'environment' => 'production',
            'name' => 'Shopify Store',
            'is_active' => true,
            'team_id' => $this->team->id,
            'client_id' => 'test-api-key',
            'config' => ['shop_domain' => 'my-store.myshopify.com'],
        ]);

        $state = encrypt([
            'organization_id' => $this->organization->id,
            'credential_id' => $credential->id,
            'shop_domain' => 'my-store.myshopify.com',
            'timestamp' => now()->timestamp,
        ]);

        Http::fake([
            'my-store.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_test_token',
                'scope' => 'read_products,write_products',
            ]),
            'my-store.myshopify.com/admin/api/*/shop.json' => Http::response([
                'shop' => [
                    'id' => 123,
                    'name' => 'My Test Store',
                    'domain' => 'my-store.myshopify.com',
                ],
            ]),
            'my-store.myshopify.com/admin/api/*' => Http::response([
                'locations' => [['id' => 99999, 'name' => 'Warehouse']],
                'webhook' => ['id' => 1, 'topic' => 'products/update'],
            ]),
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->get(route('shopify.auth.callback', [
                'code' => 'test-auth-code',
                'state' => $state,
                'shop' => 'my-store.myshopify.com',
            ]));

        $response->assertRedirect(route('marketplaces.shopify.index'));

        $this->assertDatabaseHas('oauth_tokens', [
            'integration_credential_id' => $credential->id,
            'platform' => 'shopify',
            'is_active' => true,
        ]);
    }

    public function test_callback_rejects_expired_state(): void
    {
        $this->mockShopifyConfigured();

        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'platform' => 'shopify',
            'environment' => 'production',
            'name' => 'Shopify Store',
            'is_active' => true,
            'team_id' => $this->team->id,
            'client_id' => 'test-api-key',
            'config' => ['shop_domain' => 'my-store.myshopify.com'],
        ]);

        $state = encrypt([
            'organization_id' => $this->organization->id,
            'credential_id' => $credential->id,
            'shop_domain' => 'my-store.myshopify.com',
            'timestamp' => now()->subMinutes(15)->timestamp, // Expired (>10min)
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->get(route('shopify.auth.callback', [
                'code' => 'test-code',
                'state' => $state,
            ]));

        $response->assertRedirect(route('marketplaces.shopify.index'));
        $response->assertSessionHasErrors('shopify');
    }

    public function test_callback_stores_shop_info_in_credential_config(): void
    {
        $this->mockShopifyConfigured();

        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'platform' => 'shopify',
            'environment' => 'production',
            'name' => 'Shopify Store',
            'is_active' => true,
            'team_id' => $this->team->id,
            'client_id' => 'test-api-key',
            'config' => ['shop_domain' => 'my-store.myshopify.com'],
        ]);

        $state = encrypt([
            'organization_id' => $this->organization->id,
            'credential_id' => $credential->id,
            'shop_domain' => 'my-store.myshopify.com',
            'timestamp' => now()->timestamp,
        ]);

        Http::fake([
            'my-store.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_test',
                'scope' => 'read_products',
            ]),
            'my-store.myshopify.com/admin/api/*/shop.json' => Http::response([
                'shop' => [
                    'id' => 456,
                    'name' => 'Cool Shop',
                    'domain' => 'my-store.myshopify.com',
                ],
            ]),
            'my-store.myshopify.com/admin/api/*' => Http::response([
                'locations' => [['id' => 11111]],
                'webhook' => ['id' => 1],
            ]),
        ]);

        $this->actingAs($this->ownerUser)
            ->get(route('shopify.auth.callback', [
                'code' => 'test-code',
                'state' => $state,
            ]));

        $credential->refresh();
        $this->assertEquals('Cool Shop', $credential->config['shop_name']);
        $this->assertEquals(456, $credential->config['shop_id']);
    }

    public function test_callback_rejects_wrong_organization(): void
    {
        $this->mockShopifyConfigured();

        $otherOrg = Organization::factory()->create();
        $credential = IntegrationCredential::create([
            'organization_id' => $otherOrg->id,
            'platform' => 'shopify',
            'environment' => 'production',
            'name' => 'Shopify Store',
            'is_active' => true,
            'team_id' => $this->team->id,
            'client_id' => 'test-api-key',
            'config' => ['shop_domain' => 'other-store.myshopify.com'],
        ]);

        $state = encrypt([
            'organization_id' => $otherOrg->id,
            'credential_id' => $credential->id,
            'shop_domain' => 'other-store.myshopify.com',
            'timestamp' => now()->timestamp,
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->get(route('shopify.auth.callback', [
                'code' => 'test-code',
                'state' => $state,
            ]));

        // Controller catches the abort(403) in its catch block and redirects with error
        $response->assertRedirect(route('marketplaces.shopify.index'));
        $response->assertSessionHasErrors('shopify');
    }

    public function test_disconnect_deactivates_credential_and_tokens(): void
    {
        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'platform' => 'shopify',
            'environment' => 'production',
            'name' => 'Shopify Store',
            'is_active' => true,
            'team_id' => $this->team->id,
            'client_id' => 'test-api-key',
            'config' => ['shop_domain' => 'my-store.myshopify.com'],
        ]);

        $token = OAuthToken::create([
            'organization_id' => $this->organization->id,
            'integration_credential_id' => $credential->id,
            'platform' => 'shopify',
            'environment' => 'production',
            'access_token' => 'shpat_test',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->post(route('shopify.auth.disconnect'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $credential->refresh();
        $token->refresh();
        $this->assertFalse($credential->is_active);
        $this->assertFalse($token->is_active);
    }

    public function test_disconnect_returns_error_when_no_connection(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->post(route('shopify.auth.disconnect'));

        $response->assertRedirect();
        $response->assertSessionHasErrors('shopify');
    }
}
