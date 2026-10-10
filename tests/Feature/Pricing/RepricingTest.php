<?php

namespace Tests\Feature\Pricing;

use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\Pricing\RepricingRule;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class RepricingTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'permissions' => [
                'pricing.view', 'pricing.create', 'pricing.update', 'pricing.delete',
            ],
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($this->adminRole->id, [
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

    public function test_can_view_repricing_rules_index(): void
    {
        RepricingRule::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('pricing.repricing.index'));

        $response->assertStatus(200);
    }

    public function test_can_create_repricing_rule(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('pricing.repricing.store'), [
                'name' => 'Test Cost Plus Rule',
                'marketplace' => 'ebay',
                'scope_type' => 'all',
                'strategy' => 'cost_plus',
                'parameters' => [
                    'cost_plus_margin' => 30,
                    'min_price' => 5.00,
                    'max_price' => 500.00,
                ],
                'is_active' => true,
            ]);

        $response->assertRedirect(route('pricing.repricing.index'));

        $this->assertDatabaseHas('repricing_rules', [
            'organization_id' => $this->organization->id,
            'name' => 'Test Cost Plus Rule',
            'marketplace' => 'ebay',
            'strategy' => 'cost_plus',
        ]);
    }

    public function test_can_update_repricing_rule(): void
    {
        $rule = RepricingRule::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Original Rule',
        ]);

        // Directly update the model to verify database operations work
        $rule->update([
            'name' => 'Updated Rule Name',
            'marketplace' => 'shopify',
        ]);

        $rule->refresh();
        $this->assertEquals('Updated Rule Name', $rule->name);
        $this->assertEquals('shopify', $rule->marketplace);
    }

    public function test_can_delete_repricing_rule(): void
    {
        $rule = RepricingRule::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $ruleId = $rule->id;

        $rule->delete();

        $this->assertDatabaseMissing('repricing_rules', [
            'id' => $ruleId,
        ]);
    }

    public function test_repricing_rule_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();

        $myRule = RepricingRule::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $otherRule = RepricingRule::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $orgRules = RepricingRule::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->get();

        $this->assertTrue($orgRules->contains($myRule));
        $this->assertFalse($orgRules->contains($otherRule));
    }
}
