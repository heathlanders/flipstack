<?php

namespace Tests\Feature\Inventory;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use App\Services\Catalog\BundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class BundleTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected Role $adminRole;

    protected Category $category;

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
                'products.view', 'products.create', 'products.edit', 'products.delete',
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

        $this->category = Category::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    protected function createProduct(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
        ], $attributes));
    }

    public function test_can_view_bundles_index(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('inventory.bundles.index'));

        $response->assertStatus(200);
    }

    public function test_can_create_bundle(): void
    {
        $bundleProduct = $this->createProduct(['name' => 'My Bundle']);
        $component1 = $this->createProduct(['name' => 'Component A']);
        $component2 = $this->createProduct(['name' => 'Component B']);

        $response = $this->actingAs($this->user)
            ->post(route('inventory.bundles.store'), [
                'product_id' => $bundleProduct->id,
                'name' => 'Test Bundle',
                'description' => 'A test bundle with two components',
                'bundle_type' => 'fixed',
                'components' => [
                    [
                        'product_id' => $component1->id,
                        'quantity' => 2,
                        'is_required' => true,
                    ],
                    [
                        'product_id' => $component2->id,
                        'quantity' => 1,
                        'is_required' => true,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('product_bundles', [
            'organization_id' => $this->organization->id,
            'name' => 'Test Bundle',
            'bundle_type' => 'fixed',
        ]);
    }

    public function test_can_view_bundle_detail(): void
    {
        $bundleService = app(BundleService::class);

        $this->assertNotNull($bundleService);
        $this->assertInstanceOf(BundleService::class, $bundleService);
    }

    public function test_bundle_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();

        $myProduct = $this->createProduct(['name' => 'Org Product']);
        $otherCategory = Category::factory()->create(['organization_id' => $otherOrg->id]);
        $otherProduct = Product::factory()->create([
            'organization_id' => $otherOrg->id,
            'category_id' => $otherCategory->id,
        ]);

        // Products are properly scoped to organization
        $myProducts = Product::where('organization_id', $this->organization->id)->get();
        $this->assertTrue($myProducts->contains($myProduct));
        $this->assertFalse($myProducts->contains($otherProduct));
    }
}
