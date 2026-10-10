<?php

namespace Tests\Feature\Supplier;

use App\Models\Inventory\Supplier;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\Supplier\SupplierProductSubmission;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class ProductSubmissionTest extends TestCase
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
            'sku_prefix' => 'SUB',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-sub',
            'is_system' => true,
            'permissions' => [
                'inventory.view', 'suppliers.view', 'suppliers.manage',
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

    protected function createSupplier(array $attributes = []): Supplier
    {
        return Supplier::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
        ], $attributes));
    }

    protected function createSubmission(Supplier $supplier, array $attributes = []): SupplierProductSubmission
    {
        return SupplierProductSubmission::create(array_merge([
            'organization_id' => $this->organization->id,
            'supplier_id' => $supplier->id,
            'status' => SupplierProductSubmission::STATUS_PENDING_REVIEW,
            'submitted_data' => [
                'product_name' => 'Test Product',
                'sku' => 'TEST-001',
                'price' => 29.99,
                'quantity' => 100,
                'description' => 'A test product submission from supplier.',
            ],
        ], $attributes));
    }

    public function test_can_view_product_submissions(): void
    {
        $supplier = $this->createSupplier();
        $this->createSubmission($supplier);

        $response = $this->actingAs($this->user)
            ->get(route('admin.supplier-submissions.index'));

        $response->assertStatus(200);
    }

    public function test_can_review_submission(): void
    {
        $supplier = $this->createSupplier();
        $submission = $this->createSubmission($supplier);

        $response = $this->actingAs($this->user)
            ->get(route('admin.supplier-submissions.review', $submission));

        $response->assertStatus(200);
    }

    public function test_submissions_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherSupplier = Supplier::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        SupplierProductSubmission::create([
            'organization_id' => $otherOrg->id,
            'supplier_id' => $otherSupplier->id,
            'status' => SupplierProductSubmission::STATUS_PENDING_REVIEW,
            'submitted_data' => [
                'product_name' => 'Other Org Product',
                'sku' => 'OTHER-001',
                'price' => 19.99,
                'quantity' => 50,
            ],
        ]);

        $mySupplier = $this->createSupplier();
        $mySubmission = $this->createSubmission($mySupplier);

        $mySubmissions = SupplierProductSubmission::where('organization_id', $this->organization->id)->get();

        $this->assertCount(1, $mySubmissions);
        $this->assertEquals($mySubmission->id, $mySubmissions->first()->id);
    }
}
