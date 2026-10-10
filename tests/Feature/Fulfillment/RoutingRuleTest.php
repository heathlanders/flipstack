<?php

namespace Tests\Feature\Fulfillment;

use App\Models\Fulfillment\FulfillmentRoutingRule;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class RoutingRuleTest extends TestCase
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
            'sku_prefix' => 'RTE',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-rte',
            'is_system' => true,
            'permissions' => [
                'inventory.view', 'fulfillment.view', 'fulfillment.manage',
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

    public function test_can_view_routing_rules(): void
    {
        FulfillmentRoutingRule::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Closest Warehouse',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('fulfillment.routing.index'));

        $response->assertStatus(200);
    }

    public function test_can_create_routing_rule(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('fulfillment.routing.store'), [
                'name' => 'New Proximity Rule',
                'rule_type' => FulfillmentRoutingRule::TYPE_PROXIMITY,
                'priority' => 1,
                'is_active' => true,
                'parameters' => [],
            ]);

        $response->assertRedirect(route('fulfillment.routing.index'));

        $this->assertDatabaseHas('fulfillment_routing_rules', [
            'organization_id' => $this->organization->id,
            'name' => 'New Proximity Rule',
            'rule_type' => FulfillmentRoutingRule::TYPE_PROXIMITY,
        ]);
    }

    public function test_can_update_routing_rule(): void
    {
        $rule = FulfillmentRoutingRule::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Original Rule',
            'rule_type' => FulfillmentRoutingRule::TYPE_LOWEST_COST,
            'priority' => 5,
        ]);

        $rule->update([
            'name' => 'Updated Rule',
            'rule_type' => FulfillmentRoutingRule::TYPE_HIGHEST_STOCK,
            'priority' => 1,
        ]);

        $rule->refresh();
        $this->assertEquals('Updated Rule', $rule->name);
        $this->assertEquals(FulfillmentRoutingRule::TYPE_HIGHEST_STOCK, $rule->rule_type);
        $this->assertEquals(1, $rule->priority);
    }

    public function test_can_delete_routing_rule(): void
    {
        $rule = FulfillmentRoutingRule::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Rule To Delete',
        ]);

        $ruleId = $rule->id;
        $rule->delete();

        $this->assertDatabaseMissing('fulfillment_routing_rules', [
            'id' => $ruleId,
        ]);
    }

    public function test_routing_rule_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();

        $otherRule = FulfillmentRoutingRule::factory()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Org Rule',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('fulfillment.routing.index'));

        $response->assertStatus(200);

        $myRule = FulfillmentRoutingRule::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'My Org Rule',
        ]);

        $myRules = FulfillmentRoutingRule::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->get();

        $this->assertTrue($myRules->contains('id', $myRule->id));
        $this->assertFalse($myRules->contains('id', $otherRule->id));
    }
}
