<?php

namespace Tests\Feature\Ebay;

use App\Models\Ebay\EbayPromotedCampaign;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class PromotedListingTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create([
            'sku_prefix' => 'PRM',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-prm',
            'is_system' => true,
            'permissions' => [
                'inventory.view', 'inventory.create', 'inventory.update',
                'products.view', 'products.create', 'products.edit',
            ],
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($adminRole->id, [
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

    public function test_can_view_promoted_campaigns(): void
    {
        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => 'ebay',
            'environment' => 'production',
            'name' => 'eBay Test Account',
            'is_active' => true,
        ]);

        EbayPromotedCampaign::create([
            'organization_id' => $this->organization->id,
            'integration_credential_id' => $credential->id,
            'name' => 'Test Campaign',
            'status' => EbayPromotedCampaign::STATUS_ACTIVE,
            'campaign_type' => EbayPromotedCampaign::TYPE_STANDARD,
            'start_date' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('marketplaces.ebay.promoted.campaigns'));

        $response->assertStatus(200);
    }

    public function test_can_create_promoted_campaign(): void
    {
        $credential = IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => 'ebay',
            'environment' => 'production',
            'name' => 'eBay Test Account',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('marketplaces.ebay.promoted.store'), [
                'name' => 'New Promoted Campaign',
                'campaign_type' => 'standard',
                'integration_credential_id' => $credential->id,
                'budget_daily' => 10.00,
                'budget_total' => 300.00,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
            ]);

        $response->assertRedirect(route('marketplaces.ebay.promoted.campaigns'));
    }

    public function test_promoted_campaign_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();

        $otherCredential = IntegrationCredential::create([
            'organization_id' => $otherOrg->id,
            'team_id' => null,
            'platform' => 'ebay',
            'environment' => 'production',
            'name' => 'Other eBay Account',
            'is_active' => true,
        ]);

        $otherCampaign = EbayPromotedCampaign::create([
            'organization_id' => $otherOrg->id,
            'integration_credential_id' => $otherCredential->id,
            'name' => 'Other Org Campaign',
            'status' => EbayPromotedCampaign::STATUS_ACTIVE,
            'campaign_type' => EbayPromotedCampaign::TYPE_STANDARD,
            'start_date' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('marketplaces.ebay.promoted.show', $otherCampaign));

        $response->assertStatus(403);
    }
}
