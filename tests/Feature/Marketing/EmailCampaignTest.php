<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\EmailCampaign;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class EmailCampaignTest extends TestCase
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
            'sku_prefix' => 'MKT',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-mkt',
            'is_system' => true,
            'permissions' => [
                'inventory.view', 'marketing.view', 'marketing.manage',
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

    public function test_can_view_campaigns_index(): void
    {
        EmailCampaign::create([
            'organization_id' => $this->organization->id,
            'name' => 'Post-Purchase Follow-up',
            'type' => EmailCampaign::TYPE_POST_PURCHASE_FOLLOWUP,
            'trigger_event' => EmailCampaign::TRIGGER_ORDER_DELIVERED,
            'trigger_delay_hours' => 24,
            'subject_template' => 'Thanks for your purchase!',
            'body_template' => 'We hope you enjoy your item.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('marketing.campaigns.index'));

        $response->assertStatus(200);
    }

    public function test_can_create_campaign(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('marketing.campaigns.store'), [
                'name' => 'Review Request Campaign',
                'type' => EmailCampaign::TYPE_REVIEW_REQUEST,
                'trigger_event' => EmailCampaign::TRIGGER_DAYS_AFTER_DELIVERY,
                'trigger_delay_hours' => 72,
                'subject_template' => 'How was your purchase?',
                'body_template' => 'We would love to hear your feedback.',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('marketing.campaigns.index'));

        $this->assertDatabaseHas('email_campaigns', [
            'organization_id' => $this->organization->id,
            'name' => 'Review Request Campaign',
            'type' => EmailCampaign::TYPE_REVIEW_REQUEST,
        ]);
    }

    public function test_can_update_campaign(): void
    {
        $campaign = EmailCampaign::create([
            'organization_id' => $this->organization->id,
            'name' => 'Original Campaign',
            'type' => EmailCampaign::TYPE_POST_PURCHASE_FOLLOWUP,
            'trigger_event' => EmailCampaign::TRIGGER_ORDER_DELIVERED,
            'trigger_delay_hours' => 24,
            'subject_template' => 'Original subject',
            'body_template' => 'Original body',
            'is_active' => true,
        ]);

        $campaign->update([
            'name' => 'Updated Campaign',
            'type' => EmailCampaign::TYPE_WIN_BACK,
            'trigger_event' => EmailCampaign::TRIGGER_DAYS_AFTER_DELIVERY,
            'trigger_delay_hours' => 168,
            'subject_template' => 'We miss you!',
            'body_template' => 'Come back and shop with us.',
            'is_active' => false,
        ]);

        $campaign->refresh();
        $this->assertEquals('Updated Campaign', $campaign->name);
        $this->assertEquals(EmailCampaign::TYPE_WIN_BACK, $campaign->type);
        $this->assertFalse($campaign->is_active);
    }

    public function test_campaign_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();

        EmailCampaign::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Org Campaign',
            'type' => EmailCampaign::TYPE_RESTOCK_ALERT,
            'trigger_event' => EmailCampaign::TRIGGER_ORDER_SHIPPED,
            'trigger_delay_hours' => 0,
            'subject_template' => 'Restock alert',
            'body_template' => 'Item restocked.',
            'is_active' => true,
        ]);

        $myCampaign = EmailCampaign::create([
            'organization_id' => $this->organization->id,
            'name' => 'My Org Campaign',
            'type' => EmailCampaign::TYPE_POST_PURCHASE_FOLLOWUP,
            'trigger_event' => EmailCampaign::TRIGGER_ORDER_DELIVERED,
            'trigger_delay_hours' => 24,
            'subject_template' => 'Thanks!',
            'body_template' => 'Thank you for your purchase.',
            'is_active' => true,
        ]);

        $myCampaigns = EmailCampaign::where('organization_id', $this->organization->id)->get();

        $this->assertCount(1, $myCampaigns);
        $this->assertEquals('My Org Campaign', $myCampaigns->first()->name);
    }
}
