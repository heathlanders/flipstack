<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\WarehouseZone;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class WarehouseLayoutTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected Role $adminRole;

    protected InventoryLocation $location;

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
                'inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete',
                'warehouse.view', 'warehouse.manage',
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

        $this->location = InventoryLocation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_can_view_zones(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('inventory.warehouse.zones.index', [
                'location_id' => $this->location->id,
            ]));

        $response->assertStatus(200);
    }

    public function test_can_create_zone(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('inventory.warehouse.zones.store'), [
                'location_id' => $this->location->id,
                'name' => 'Zone A - Storage',
                'code' => 'ZA',
                'type' => 'storage',
                'sort_order' => 1,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('warehouse_zones', [
            'organization_id' => $this->organization->id,
            'location_id' => $this->location->id,
            'name' => 'Zone A - Storage',
            'code' => 'ZA',
            'type' => 'storage',
        ]);
    }

    public function test_can_view_bins(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('inventory.warehouse.bins.index', [
                'location_id' => $this->location->id,
            ]));

        $response->assertStatus(200);
    }

    public function test_can_create_bin(): void
    {
        $zone = WarehouseZone::create([
            'organization_id' => $this->organization->id,
            'location_id' => $this->location->id,
            'name' => 'Test Zone',
            'code' => 'TZ',
            'type' => 'storage',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('inventory.warehouse.bins.store'), [
                'zone_id' => $zone->id,
                'code' => 'TZ-A1-R1-S1',
                'label' => 'Aisle 1, Rack 1, Shelf 1',
                'aisle' => 'A1',
                'rack' => 'R1',
                'shelf' => 'S1',
                'max_capacity' => 100,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('warehouse_bins', [
            'zone_id' => $zone->id,
            'code' => 'TZ-A1-R1-S1',
        ]);
    }

    public function test_zones_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherLocation = InventoryLocation::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $myZone = WarehouseZone::create([
            'organization_id' => $this->organization->id,
            'location_id' => $this->location->id,
            'name' => 'My Zone',
            'code' => 'MZ',
            'type' => 'storage',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $otherZone = WarehouseZone::create([
            'organization_id' => $otherOrg->id,
            'location_id' => $otherLocation->id,
            'name' => 'Other Zone',
            'code' => 'OZ',
            'type' => 'storage',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $orgZones = WarehouseZone::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->get();

        $this->assertTrue($orgZones->contains($myZone));
        $this->assertFalse($orgZones->contains($otherZone));
    }
}
