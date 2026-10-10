<?php

namespace Tests\Feature\Marketplaces;

use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Models\Organization\Organization;
use App\Models\System\BulkOperationJob;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EbayMarketplaceTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected User $adminUser;

    protected User $nonAdminUser;

    protected Role $adminRole;

    protected Role $buyerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'permissions' => ['inventory.view', 'products.view'],
        ]);

        $this->buyerRole = Role::factory()->create([
            'name' => 'Buyer',
            'slug' => 'buyer',
            'is_system' => true,
            'permissions' => ['inventory.view'],
        ]);

        $this->adminUser = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        $this->nonAdminUser = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->nonAdminUser->roles()->attach($this->buyerRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        // Mark onboarding as completed so the middleware doesn't redirect
        foreach ([$this->adminUser, $this->nonAdminUser] as $user) {
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

    protected function createCredential(array $attrs = []): IntegrationCredential
    {
        return IntegrationCredential::create(array_merge([
            'organization_id' => $this->organization->id,
            'platform' => 'ebay',
            'environment' => 'production',
            'name' => 'eBay Production',
            'is_active' => true,
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ], $attrs));
    }

    protected function createToken(IntegrationCredential $credential): OAuthToken
    {
        return OAuthToken::create([
            'organization_id' => $this->organization->id,
            'integration_credential_id' => $credential->id,
            'platform' => 'ebay',
            'environment' => 'production',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_at' => now()->addHours(2),
            'is_active' => true,
        ]);
    }

    public function test_org_owner_can_access_ebay_hub_page(): void
    {
        $ownerRole = Role::factory()->create([
            'name' => 'Organization Owner',
            'slug' => 'organization_owner',
            'is_system' => true,
            'permissions' => [],
        ]);

        $owner = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $owner->roles()->attach($ownerRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        $response = $this->actingAs($owner)->get(route('marketplaces.ebay.index'));

        $response->assertStatus(200);
    }

    public function test_admin_can_access_ebay_hub_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('marketplaces.ebay.index'));

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_access_ebay_hub_page(): void
    {
        $response = $this->withMiddleware()->actingAs($this->nonAdminUser)->get(route('marketplaces.ebay.index'));

        $response->assertStatus(403);
    }

    public function test_hub_page_shows_connection_status(): void
    {
        $credential = $this->createCredential();
        $this->createToken($credential);

        $response = $this->actingAs($this->adminUser)->get(route('marketplaces.ebay.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Marketplaces/Ebay/Index')
            ->where('integrationStatus.connected', true)
            ->where('integrationStatus.has_token', true)
        );
    }

    public function test_import_page_renders(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('marketplaces.ebay.import'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Marketplaces/Ebay/Import')
        );
    }

    public function test_start_import_requires_ebay_connection(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('marketplaces.ebay.import.start'));

        $response->assertRedirect();
        $response->assertSessionHasErrors('ebay');
    }

    public function test_start_import_dispatches_job(): void
    {
        Queue::fake();

        $credential = $this->createCredential();
        $this->createToken($credential);

        $response = $this->actingAs($this->adminUser)->post(route('marketplaces.ebay.import.start'), [
            'skip_existing_skus' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bulk_operation_jobs', [
            'organization_id' => $this->organization->id,
            'operation_type' => BulkOperationJob::TYPE_EBAY_IMPORT,
            'status' => BulkOperationJob::STATUS_PENDING,
        ]);

        Queue::assertPushed(\App\Jobs\Ebay\ImportEbayInventory::class);
    }

    public function test_start_import_prevents_duplicate_imports(): void
    {
        Queue::fake();

        $credential = $this->createCredential();
        $this->createToken($credential);

        BulkOperationJob::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminUser->id,
            'operation_type' => BulkOperationJob::TYPE_EBAY_IMPORT,
            'status' => BulkOperationJob::STATUS_PROCESSING,
            'total_items' => 100,
            'processed_items' => 50,
            'failed_items' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('marketplaces.ebay.import.start'));

        $response->assertRedirect();
        $response->assertSessionHasErrors('ebay');
    }

    public function test_import_status_returns_progress(): void
    {
        $bulkJob = BulkOperationJob::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminUser->id,
            'operation_type' => BulkOperationJob::TYPE_EBAY_IMPORT,
            'status' => BulkOperationJob::STATUS_PROCESSING,
            'total_items' => 100,
            'processed_items' => 45,
            'failed_items' => 2,
            'parameters' => ['skip_existing_skus' => true],
        ]);

        $response = $this->actingAs($this->adminUser)->getJson(route('marketplaces.ebay.import.status'));

        $response->assertOk();
        $response->assertJson([
            'status' => 'processing',
            'total_items' => 100,
            'processed_items' => 45,
            'failed_items' => 2,
        ]);
    }

    public function test_cancel_import_marks_job_cancelled(): void
    {
        $bulkJob = BulkOperationJob::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminUser->id,
            'operation_type' => BulkOperationJob::TYPE_EBAY_IMPORT,
            'status' => BulkOperationJob::STATUS_PROCESSING,
            'total_items' => 100,
            'processed_items' => 50,
            'failed_items' => 0,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('marketplaces.ebay.import.cancel'));

        $response->assertRedirect();
        $this->assertDatabaseHas('bulk_operation_jobs', [
            'id' => $bulkJob->id,
            'status' => BulkOperationJob::STATUS_CANCELLED,
        ]);
    }
}
