<?php

namespace Tests\Feature\Api;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductBundle;
use App\Models\Catalog\ProductBundleItem;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileBundleTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);

        Sanctum::actingAs($this->user, ['*']);
    }

    private function product(int $available): Product
    {
        $category = Category::factory()->create(['organization_id' => $this->organization->id]);

        return Product::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $category->id,
            'created_by' => $this->user->id,
            'available_quantity' => $available,
        ]);
    }

    private function fixedBundle(): ProductBundle
    {
        $bundle = ProductBundle::factory()->fixed()->create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product(0)->id,
            'name' => 'Starter Kit',
        ]);

        // Component A: 10 available, qty 2 -> 5 bundles
        ProductBundleItem::factory()->create([
            'bundle_id' => $bundle->id,
            'component_product_id' => $this->product(10)->id,
            'quantity' => 2,
            'is_required' => true,
        ]);
        // Component B: 12 available, qty 3 -> 4 bundles (the constraint)
        ProductBundleItem::factory()->create([
            'bundle_id' => $bundle->id,
            'component_product_id' => $this->product(12)->id,
            'quantity' => 3,
            'is_required' => true,
        ]);

        return $bundle;
    }

    public function test_index_lists_bundles_with_computed_availability(): void
    {
        $bundle = $this->fixedBundle();

        $this->getJson(route('api.bundles.index'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $bundle->id)
            ->assertJsonPath('data.0.available_quantity', 4)
            ->assertJsonPath('stats.active', 1);
    }

    public function test_show_returns_components(): void
    {
        $bundle = $this->fixedBundle();

        $this->getJson(route('api.bundles.show', $bundle))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.available_quantity', 4)
            ->assertJsonCount(2, 'data.components');
    }

    public function test_cannot_view_other_orgs_bundle(): void
    {
        $otherOrg = Organization::factory()->create();
        $bundle = ProductBundle::factory()->fixed()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $this->getJson(route('api.bundles.show', $bundle))->assertStatus(404);
    }
}
