<?php

namespace Tests\Feature\Amazon;

use App\Jobs\Amazon\RegisterAmazonNotifications;
use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmazonOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.amazon.lwa_client_id', 'amzn1.application-oa2-client.test');
        Config::set('services.amazon.lwa_client_secret', 'test-secret');
        Config::set('services.amazon.redirect_uri', 'https://test.local/amazon/auth/callback');

        $this->organization = Organization::factory()->create();
        $this->team = Team::factory()->create(['organization_id' => $this->organization->id]);

        $role = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-amz-oauth',
            'is_system' => true,
            'permissions' => ['integrations.view'],
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

    public function test_install_redirects_to_seller_central(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('amazon.auth.install'));

        $response->assertRedirect();
        $this->assertStringContainsString('sellercentral.amazon.com/apps/authorize/consent', $response->headers->get('Location'));

        // Credential should be created so callback has a target
        $credential = IntegrationCredential::where('organization_id', $this->organization->id)
            ->where('platform', 'amazon')
            ->first();
        $this->assertNotNull($credential);
    }

    public function test_callback_exchanges_code_persists_seller_id_and_dispatches_registration(): void
    {
        Bus::fake([RegisterAmazonNotifications::class]);

        // Stub LWA token endpoint and seller info call.
        Http::fake([
            'api.amazon.com/auth/o2/token' => Http::response([
                'access_token' => 'Atza|access',
                'refresh_token' => 'Atzr|refresh',
                'expires_in' => 3600,
            ], 200),
            '*sellingpartnerapi*/sellers/v1/marketplaceParticipations*' => Http::response([
                'payload' => [[
                    'marketplace' => [
                        'id' => 'ATVPDKIKX0DER',
                        'name' => 'Amazon.com',
                        'countryCode' => 'US',
                    ],
                    'participation' => [
                        'sellerId' => 'A1SELLER42',
                        'isParticipating' => true,
                    ],
                ]],
            ], 200),
        ]);

        // Pre-create credential as install would.
        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => 'amazon',
            'environment' => 'sandbox',
            'name' => 'Amazon Seller',
            'is_active' => false,
            'config' => ['amazon_marketplace_id' => 'ATVPDKIKX0DER'],
        ]);

        $state = encrypt([
            'organization_id' => $this->organization->id,
            'credential_id' => $credential->id,
            'timestamp' => now()->timestamp,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('amazon.auth.callback', [
                'spapi_oauth_code' => 'test-code',
                'selling_partner_id' => 'A1SELLER42',
                'state' => $state,
            ]));

        $response->assertRedirect(route('marketplaces.amazon.index'));

        $credential->refresh();
        $this->assertTrue($credential->is_active);
        $this->assertSame('A1SELLER42', $credential->config['amazon_seller_id']);

        $token = OAuthToken::where('integration_credential_id', $credential->id)
            ->where('platform', 'amazon')
            ->where('is_active', true)
            ->first();
        $this->assertNotNull($token);

        Bus::assertDispatched(RegisterAmazonNotifications::class, fn ($job) => $job->credentialId === $credential->id);
    }

    public function test_callback_rejects_expired_state(): void
    {
        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => 'amazon',
            'environment' => 'sandbox',
            'name' => 'Amazon Seller',
            'is_active' => false,
            'config' => [],
        ]);

        $state = encrypt([
            'organization_id' => $this->organization->id,
            'credential_id' => $credential->id,
            'timestamp' => now()->subHour()->timestamp,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('amazon.auth.callback', [
                'spapi_oauth_code' => 'test-code',
                'state' => $state,
            ]));

        $response->assertRedirect(route('marketplaces.amazon.index'));
        $response->assertSessionHasErrors('amazon');
    }
}
