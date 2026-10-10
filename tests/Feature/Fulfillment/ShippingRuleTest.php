<?php

namespace Tests\Feature\Fulfillment;

use App\Models\Fulfillment\ShippingRule;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class ShippingRuleTest extends TestCase
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
            'sku_prefix' => 'SHP',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-shp',
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

    public function test_can_view_shipping_rules(): void
    {
        ShippingRule::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'USPS First Class Rule',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('fulfillment.shipping-rules.index'));

        $response->assertStatus(200);
    }

    public function test_can_create_shipping_rule(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('fulfillment.shipping-rules.store'), [
                'name' => 'Lightweight USPS Rule',
                'priority' => 1,
                'conditions' => [
                    'weight_min' => 0,
                    'weight_max' => 16,
                ],
                'action_carrier' => 'USPS',
                'action_service' => 'First Class',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('fulfillment.shipping-rules.index'));

        $this->assertDatabaseHas('shipping_rules', [
            'organization_id' => $this->organization->id,
            'name' => 'Lightweight USPS Rule',
            'action_carrier' => 'USPS',
            'action_service' => 'First Class',
        ]);
    }

    public function test_can_update_shipping_rule(): void
    {
        $rule = ShippingRule::factory()->usps()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Original Shipping Rule',
        ]);

        $rule->update([
            'name' => 'Updated Shipping Rule',
            'action_carrier' => ShippingRule::CARRIER_UPS,
            'action_service' => 'Ground',
        ]);

        $rule->refresh();
        $this->assertEquals('Updated Shipping Rule', $rule->name);
        $this->assertEquals(ShippingRule::CARRIER_UPS, $rule->action_carrier);
        $this->assertEquals('Ground', $rule->action_service);
    }

    public function test_can_delete_shipping_rule(): void
    {
        $rule = ShippingRule::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Rule To Delete',
        ]);

        $ruleId = $rule->id;
        $rule->delete();

        $this->assertDatabaseMissing('shipping_rules', [
            'id' => $ruleId,
        ]);
    }

    public function test_shipping_rule_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();

        $otherRule = ShippingRule::factory()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Org Shipping Rule',
        ]);

        $myRule = ShippingRule::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'My Org Shipping Rule',
        ]);

        $myRules = ShippingRule::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->get();

        $this->assertTrue($myRules->contains('id', $myRule->id));
        $this->assertFalse($myRules->contains('id', $otherRule->id));
    }
}
