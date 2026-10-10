<?php

namespace Tests\Feature\Integration;

use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use App\Services\EbayConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EbayImportPromptTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected User $ownerUser;

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

        $this->ownerUser = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->ownerUser->roles()->attach($ownerRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        OnboardingProgress::create([
            'user_id' => $this->ownerUser->id,
            'organization_id' => $this->organization->id,
            'steps_completed' => [],
            'current_step' => 'completed',
            'data' => [],
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function test_settings_page_includes_product_count_of_zero_when_no_products(): void
    {
        $this->mockEbayConfigured();

        $response = $this->actingAs($this->ownerUser)
            ->get(route('organization.settings'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Organization/Settings')
            ->has('productCount')
            ->where('productCount', 0)
        );
    }

    public function test_settings_page_includes_correct_product_count_when_products_exist(): void
    {
        $this->mockEbayConfigured();

        Product::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->get(route('organization.settings'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Organization/Settings')
            ->where('productCount', 5)
        );
    }

    public function test_settings_page_includes_show_ebay_import_prompt_flag(): void
    {
        $this->mockEbayConfigured();

        $response = $this->actingAs($this->ownerUser)
            ->get(route('organization.settings'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Organization/Settings')
            ->has('showEbayImportPrompt')
        );
    }

    public function test_oauth_callback_sets_import_prompt_flash_when_no_products(): void
    {
        $this->mockEbayConfigured();

        Http::fake([
            '*/identity/v1/oauth2/token' => Http::response([
                'access_token' => 'test_access_token',
                'refresh_token' => 'test_refresh_token',
                'expires_in' => 7200,
                'token_type' => 'User Access Token',
            ]),
            '*/commerce/identity/v1/user/*' => Http::response([
                'username' => 'test_seller',
                'registrationMarketplaceId' => 'EBAY_US',
            ]),
        ]);

        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->ownerUser->currentTeam?->id,
            'platform' => 'ebay',
            'environment' => 'sandbox',
            'name' => 'eBay Sandbox',
            'client_id' => 'app_level_credentials',
            'is_active' => true,
        ]);

        $state = encrypt([
            'organization_id' => $this->organization->id,
            'credential_id' => $credential->id,
            'timestamp' => now()->timestamp,
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->get(route('integrations.ebay.callback', ['environment' => 'sandbox']).'?code=test_auth_code&state='.$state);

        $response->assertRedirect(route('organization.settings'));
        $response->assertSessionHas('show_ebay_import_prompt', true);
        $response->assertSessionHas('success');
    }

    public function test_oauth_callback_does_not_set_import_prompt_when_products_exist(): void
    {
        $this->mockEbayConfigured();

        Product::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        Http::fake([
            '*/identity/v1/oauth2/token' => Http::response([
                'access_token' => 'test_access_token',
                'refresh_token' => 'test_refresh_token',
                'expires_in' => 7200,
                'token_type' => 'User Access Token',
            ]),
            '*/commerce/identity/v1/user/*' => Http::response([
                'username' => 'test_seller',
                'registrationMarketplaceId' => 'EBAY_US',
            ]),
        ]);

        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->ownerUser->currentTeam?->id,
            'platform' => 'ebay',
            'environment' => 'sandbox',
            'name' => 'eBay Sandbox',
            'client_id' => 'app_level_credentials',
            'is_active' => true,
        ]);

        $state = encrypt([
            'organization_id' => $this->organization->id,
            'credential_id' => $credential->id,
            'timestamp' => now()->timestamp,
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->get(route('integrations.ebay.callback', ['environment' => 'sandbox']).'?code=test_auth_code&state='.$state);

        $response->assertRedirect(route('organization.settings'));
        $response->assertSessionHas('show_ebay_import_prompt', false);
    }

    public function test_products_from_other_organizations_do_not_affect_prompt(): void
    {
        $otherOrg = Organization::factory()->create();
        Product::factory()->count(10)->create([
            'organization_id' => $otherOrg->id,
        ]);

        $this->mockEbayConfigured();

        $response = $this->actingAs($this->ownerUser)
            ->get(route('organization.settings'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('productCount', 0)
        );
    }

    protected function mockEbayConfigured(): void
    {
        $this->partialMock(EbayConfigService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('getEnvironment')->andReturn('sandbox');
            $mock->shouldReceive('isSandbox')->andReturn(true);
            $mock->shouldReceive('getRuName')->andReturn('test-runame');
            $mock->shouldReceive('getClientId')->andReturn('test-client-id');
            $mock->shouldReceive('getClientSecret')->andReturn('test-secret');
        });
    }
}
