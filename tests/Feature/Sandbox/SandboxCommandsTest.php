<?php

namespace Tests\Feature\Sandbox;

use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Models\Organization\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SandboxCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        config([
            'services.ebay.use_sandbox' => true,
            'services.shopify.use_sandbox' => true,
        ]);
    }

    public function test_status_renders_without_credentials(): void
    {
        $this->artisan('sandbox:status')
            ->expectsOutputToContain('Sandbox / Production Status')
            ->expectsOutputToContain('eBay')
            ->expectsOutputToContain('Shopify')
            ->assertSuccessful();
    }

    public function test_cutover_refuses_on_production_env(): void
    {
        config(['app.env' => 'production']);

        $this->artisan('sandbox:cutover', ['--confirm' => true])
            ->expectsOutputToContain('Refusing to run on production')
            ->assertFailed();
    }

    public function test_cutover_dry_run_reports_stale_rows_without_deleting(): void
    {
        $cred = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'platform' => 'ebay',
            'name' => 'stale-prod',
            'environment' => 'production',
            'is_active' => true,
        ]);

        OAuthToken::create([
            'organization_id' => $this->organization->id,
            'integration_credential_id' => $cred->id,
            'platform' => 'ebay',
            'environment' => 'production',
            'access_token' => 'fake',
            'is_active' => true,
        ]);

        $this->artisan('sandbox:cutover', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run only')
            ->assertSuccessful();

        $this->assertDatabaseHas('integration_credentials', ['id' => $cred->id]);
    }

    public function test_cutover_with_confirm_deletes_stale_rows(): void
    {
        $cred = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'platform' => 'shopify',
            'name' => 'stale-prod-shopify',
            'environment' => 'production',
            'is_active' => true,
        ]);

        OAuthToken::create([
            'organization_id' => $this->organization->id,
            'integration_credential_id' => $cred->id,
            'platform' => 'shopify',
            'environment' => 'production',
            'access_token' => 'fake',
            'is_active' => true,
        ]);

        $this->artisan('sandbox:cutover', ['--confirm' => true])
            ->expectsOutputToContain('Deleted')
            ->assertSuccessful();

        $this->assertDatabaseMissing('integration_credentials', ['id' => $cred->id]);
        $this->assertDatabaseMissing('oauth_tokens', ['integration_credential_id' => $cred->id]);
    }

    public function test_cutover_keeps_rows_matching_current_env(): void
    {
        $cred = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'platform' => 'ebay',
            'name' => 'matching-sandbox',
            'environment' => 'sandbox',
            'is_active' => true,
        ]);

        $this->artisan('sandbox:cutover', ['--confirm' => true])
            ->expectsOutputToContain('Nothing to clean')
            ->assertSuccessful();

        $this->assertDatabaseHas('integration_credentials', ['id' => $cred->id]);
    }
}
