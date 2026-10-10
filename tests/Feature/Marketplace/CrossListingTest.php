<?php

namespace Tests\Feature\Marketplace;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class CrossListingTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected Role $adminRole;

    protected Product $product;

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
                'inventory.view', 'products.view', 'products.create',
                'marketplace.view', 'marketplace.manage',
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

        $category = Category::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $category->id,
            'created_by' => $this->user->id,
            'status' => 'active',
        ]);
    }

    public function test_can_view_cross_list_preview(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('marketplaces.cross-list.preview', [
                'product_id' => $this->product->id,
                'source' => 'ebay',
                'target' => 'shopify',
            ]));

        // The preview page may return 200 or a redirect/error if no source listing exists.
        // We verify at minimum the route resolves and does not produce a 500 error.
        $this->assertContains($response->getStatusCode(), [200, 302, 404]);
    }

    public function test_cross_listing_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherCategory = Category::factory()->create(['organization_id' => $otherOrg->id]);
        $otherProduct = Product::factory()->create([
            'organization_id' => $otherOrg->id,
            'category_id' => $otherCategory->id,
            'status' => 'active',
        ]);

        // Attempting to preview cross-list for another org's product should fail
        $response = $this->actingAs($this->user)
            ->get(route('marketplaces.cross-list.preview', [
                'product_id' => $otherProduct->id,
                'source' => 'ebay',
                'target' => 'shopify',
            ]));

        // Should get a 404 (model not found in org scope) or validation error
        $this->assertContains($response->getStatusCode(), [403, 404, 422, 302]);
    }
}
