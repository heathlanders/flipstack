<?php

namespace Tests\Feature\Inventory;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Organization\Organization;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected User $user;

    protected Role $adminRole;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'permissions' => [
                'inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete',
                'inventory.export', 'inventory.import', 'inventory.manage_categories',
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

    public function test_user_can_view_products_index(): void
    {
        $product = $this->createProduct(['name' => 'Test Product']);

        $response = $this->actingAs($this->user)->get(route('inventory.products.index'));

        $response->assertStatus(200);
    }

    public function test_user_can_view_create_product_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('inventory.products.create'));

        $response->assertStatus(200);
    }

    public function test_guest_cannot_access_products(): void
    {
        $response = $this->withMiddleware()->get(route('inventory.products.index'));

        $response->assertRedirect('/login');
    }

    public function test_product_model_has_organization_relationship(): void
    {
        $product = $this->createProduct();

        $this->assertInstanceOf(Organization::class, $product->organization);
        $this->assertEquals($this->organization->id, $product->organization->id);
    }

    public function test_product_model_has_category_relationship(): void
    {
        $product = $this->createProduct();

        $this->assertInstanceOf(Category::class, $product->category);
        $this->assertEquals($this->category->id, $product->category->id);
    }

    public function test_product_model_has_creator_relationship(): void
    {
        $product = $this->createProduct();

        $this->assertInstanceOf(User::class, $product->creator);
        $this->assertEquals($this->user->id, $product->creator->id);
    }

    public function test_product_active_scope(): void
    {
        $activeProduct = $this->createProduct(['status' => 'active', 'published_at' => now()]);
        $inactiveProduct = $this->createProduct(['status' => 'inactive']);
        $draftProduct = $this->createProduct(['status' => 'draft']);

        $activeProducts = Product::active()->get();

        $this->assertTrue($activeProducts->contains($activeProduct));
        $this->assertFalse($activeProducts->contains($inactiveProduct));
        $this->assertFalse($activeProducts->contains($draftProduct));
    }

    public function test_product_in_stock_scope(): void
    {
        $inStockProduct = $this->createProduct(['stock_quantity' => 50, 'manage_stock' => true]);
        $outOfStockProduct = $this->createProduct(['stock_quantity' => 0, 'manage_stock' => true, 'allow_backorders' => false]);

        $inStockProducts = Product::inStock()->get();

        $this->assertTrue($inStockProducts->contains($inStockProduct));
    }

    public function test_product_low_stock_check(): void
    {
        $lowStockProduct = $this->createProduct([
            'stock_quantity' => 5,
            'low_stock_threshold' => 10,
            'manage_stock' => true,
        ]);

        $this->assertTrue($lowStockProduct->isLowStock());
    }

    public function test_product_is_in_stock_attribute(): void
    {
        $inStockProduct = $this->createProduct([
            'stock_quantity' => 50,
            'manage_stock' => true,
        ]);

        $this->assertTrue($inStockProduct->is_in_stock);
    }

    public function test_product_formatted_price_attribute(): void
    {
        $product = $this->createProduct(['selling_price' => 49.99]);

        $this->assertNotEmpty($product->formatted_price);
    }

    public function test_product_discount_percentage_attribute(): void
    {
        $product = $this->createProduct([
            'selling_price' => 80.00,
            'msrp' => 100.00,
        ]);

        $this->assertEquals(20, $product->discount_percentage);
    }

    public function test_product_condition_label_attribute(): void
    {
        $product = $this->createProduct(['condition' => 'NEW']);

        $this->assertNotEmpty($product->condition_label);
    }

    public function test_product_search_scope(): void
    {
        $product1 = $this->createProduct(['name' => 'Unique Widget Pro']);
        $product2 = $this->createProduct(['name' => 'Different Item']);

        $searchResults = Product::search('Widget')->get();

        $this->assertTrue($searchResults->contains($product1));
    }

    public function test_product_by_category_scope(): void
    {
        $category1 = Category::factory()->create(['organization_id' => $this->organization->id]);
        $category2 = Category::factory()->create(['organization_id' => $this->organization->id]);

        $product1 = $this->createProduct(['category_id' => $category1->id]);
        $product2 = $this->createProduct(['category_id' => $category2->id]);

        $categoryProducts = Product::byCategory($category1->id)->get();

        $this->assertTrue($categoryProducts->contains($product1));
        $this->assertFalse($categoryProducts->contains($product2));
    }

    public function test_product_price_range_scope(): void
    {
        $cheapProduct = $this->createProduct(['selling_price' => 10.00]);
        $midProduct = $this->createProduct(['selling_price' => 50.00]);
        $expensiveProduct = $this->createProduct(['selling_price' => 200.00]);

        $rangeProducts = Product::priceRange(25, 100)->get();

        $this->assertFalse($rangeProducts->contains($cheapProduct));
        $this->assertTrue($rangeProducts->contains($midProduct));
        $this->assertFalse($rangeProducts->contains($expensiveProduct));
    }

    public function test_product_for_organization_scope(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherCategory = Category::factory()->create(['organization_id' => $otherOrg->id]);

        $myProduct = $this->createProduct();
        $otherProduct = Product::factory()->create([
            'organization_id' => $otherOrg->id,
            'category_id' => $otherCategory->id,
        ]);

        $orgProducts = Product::forOrganization($this->organization->id)->get();

        $this->assertTrue($orgProducts->contains($myProduct));
        $this->assertFalse($orgProducts->contains($otherProduct));
    }

    public function test_product_has_uuid_primary_key(): void
    {
        $product = $this->createProduct();

        $this->assertIsString($product->id);
        $this->assertEquals(36, strlen($product->id));
    }

    public function test_product_generates_slug(): void
    {
        $product = $this->createProduct(['name' => 'My Amazing Product']);

        $this->assertNotEmpty($product->slug);
    }

    public function test_product_tracks_stock_quantity(): void
    {
        $product = $this->createProduct([
            'stock_quantity' => 100,
            'manage_stock' => true,
        ]);

        $this->assertEquals(100, $product->stock_quantity);
    }
}
