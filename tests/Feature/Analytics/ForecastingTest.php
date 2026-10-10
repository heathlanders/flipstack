<?php

namespace Tests\Feature\Analytics;

use App\Models\Analytics\ReorderSuggestion;
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

class ForecastingTest extends TestCase
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
            'sku_prefix' => 'FRC',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-frc',
            'is_system' => true,
            'permissions' => [
                'inventory.view', 'analytics.view', 'analytics.manage',
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

    public function test_can_view_forecasting_dashboard(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('analytics.forecasting.dashboard'));

        $response->assertStatus(200);
    }

    public function test_can_view_reorder_suggestions(): void
    {
        $category = Category::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $category->id,
            'created_by' => $this->user->id,
        ]);

        ReorderSuggestion::create([
            'organization_id' => $this->organization->id,
            'product_id' => $product->id,
            'suggested_quantity' => 50,
            'priority' => 'high',
            'status' => 'pending',
            'reason' => 'Stock running low based on demand forecast',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('analytics.forecasting.suggestions'));

        $response->assertStatus(200);
    }

    public function test_forecasting_scoped_to_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherCategory = Category::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $otherProduct = Product::factory()->create([
            'organization_id' => $otherOrg->id,
            'category_id' => $otherCategory->id,
        ]);

        ReorderSuggestion::create([
            'organization_id' => $otherOrg->id,
            'product_id' => $otherProduct->id,
            'suggested_quantity' => 25,
            'priority' => 'medium',
            'status' => 'pending',
            'reason' => 'Other org suggestion',
        ]);

        $myCategory = Category::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $myProduct = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $myCategory->id,
            'created_by' => $this->user->id,
        ]);

        ReorderSuggestion::create([
            'organization_id' => $this->organization->id,
            'product_id' => $myProduct->id,
            'suggested_quantity' => 100,
            'priority' => 'high',
            'status' => 'pending',
            'reason' => 'My org suggestion',
        ]);

        $mySuggestions = ReorderSuggestion::where('organization_id', $this->organization->id)->get();

        $this->assertCount(1, $mySuggestions);
        $this->assertEquals($myProduct->id, $mySuggestions->first()->product_id);
    }
}
