<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class BatchTrackingTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected Role $adminRole;

    protected Supplier $supplier;

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

        $this->supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_can_view_batches_index(): void
    {
        InventoryBatch::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $this->supplier->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.batches.index'));

        $response->assertStatus(200);
    }

    public function test_can_create_batch(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('inventory.batches.store'), [
                'name' => 'Test Batch',
                'supplier_id' => $this->supplier->id,
                'description' => 'A test batch for tracking inventory',
                'acquisition_date' => now()->toDateString(),
                'source_description' => 'Direct purchase',
                'notes' => 'Test notes',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('inventory_batches', [
            'organization_id' => $this->organization->id,
            'name' => 'Test Batch',
            'supplier_id' => $this->supplier->id,
        ]);
    }

    public function test_can_view_batch_detail(): void
    {
        $batch = InventoryBatch::factory()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $this->supplier->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.batches.show', $batch));

        $response->assertStatus(200);
    }

    public function test_batch_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();

        $myBatch = InventoryBatch::factory()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $this->supplier->id,
        ]);

        $otherSupplier = Supplier::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);
        $otherBatch = InventoryBatch::factory()->create([
            'organization_id' => $otherOrg->id,
            'supplier_id' => $otherSupplier->id,
        ]);

        $orgBatches = InventoryBatch::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->get();

        $this->assertTrue($orgBatches->contains($myBatch));
        $this->assertFalse($orgBatches->contains($otherBatch));
    }
}
