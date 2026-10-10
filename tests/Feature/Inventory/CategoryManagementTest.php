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

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected User $user;

    protected Role $adminRole;

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
                'inventory.manage_categories', 'categories.view', 'categories.create',
                'categories.edit', 'categories.delete',
            ],
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($this->adminRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);
    }

    protected function createCategory(array $attributes = []): Category
    {
        return Category::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
        ], $attributes));
    }

    public function test_category_model_has_uuid_primary_key(): void
    {
        $category = $this->createCategory();

        $this->assertIsString($category->id);
        $this->assertEquals(36, strlen($category->id));
    }

    public function test_category_has_organization_relationship(): void
    {
        $category = $this->createCategory();

        $this->assertInstanceOf(Organization::class, $category->organization);
        $this->assertEquals($this->organization->id, $category->organization->id);
    }

    public function test_category_can_have_parent(): void
    {
        $parentCategory = $this->createCategory(['name' => 'Parent Category']);
        $childCategory = $this->createCategory([
            'name' => 'Child Category',
            'parent_id' => $parentCategory->id,
        ]);

        $this->assertEquals($parentCategory->id, $childCategory->parent_id);
        $this->assertInstanceOf(Category::class, $childCategory->parent);
        $this->assertEquals('Parent Category', $childCategory->parent->name);
    }

    public function test_category_can_have_children(): void
    {
        $parentCategory = $this->createCategory(['name' => 'Parent Category']);
        $child1 = $this->createCategory([
            'name' => 'Child 1',
            'parent_id' => $parentCategory->id,
            'sort_order' => 1,
        ]);
        $child2 = $this->createCategory([
            'name' => 'Child 2',
            'parent_id' => $parentCategory->id,
            'sort_order' => 2,
        ]);

        $this->assertCount(2, $parentCategory->children);
        $this->assertTrue($parentCategory->children->contains($child1));
        $this->assertTrue($parentCategory->children->contains($child2));
    }

    public function test_category_has_products_relationship(): void
    {
        $category = $this->createCategory();
        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $category->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertCount(1, $category->products);
        $this->assertTrue($category->products->contains($product));
    }

    public function test_category_active_scope(): void
    {
        $activeCategory = $this->createCategory(['is_active' => true]);
        $inactiveCategory = $this->createCategory(['is_active' => false]);

        $activeCategories = Category::active()->get();

        $this->assertTrue($activeCategories->contains($activeCategory));
        $this->assertFalse($activeCategories->contains($inactiveCategory));
    }

    public function test_category_root_categories_scope(): void
    {
        $rootCategory = $this->createCategory(['parent_id' => null, 'depth' => 0]);
        $childCategory = $this->createCategory([
            'parent_id' => $rootCategory->id,
            'depth' => 1,
        ]);

        $rootCategories = Category::rootCategories()->get();

        $this->assertTrue($rootCategories->contains($rootCategory));
    }

    public function test_category_for_organization_scope(): void
    {
        $otherOrg = Organization::factory()->create();

        $myCategory = $this->createCategory();
        $otherCategory = Category::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $orgCategories = Category::forOrganization($this->organization->id)->get();

        $this->assertTrue($orgCategories->contains($myCategory));
        $this->assertFalse($orgCategories->contains($otherCategory));
    }

    public function test_category_full_path_attribute(): void
    {
        $parentCategory = $this->createCategory(['name' => 'Electronics']);
        $childCategory = $this->createCategory([
            'name' => 'Phones',
            'parent_id' => $parentCategory->id,
        ]);
        $grandchildCategory = $this->createCategory([
            'name' => 'Smartphones',
            'parent_id' => $childCategory->id,
        ]);

        $this->assertEquals('Electronics > Phones > Smartphones', $grandchildCategory->full_path);
    }

    public function test_category_has_slug(): void
    {
        $category = $this->createCategory(['name' => 'Test Category Name']);

        $this->assertNotEmpty($category->slug);
    }

    public function test_category_has_description(): void
    {
        $category = $this->createCategory(['description' => 'A test category description']);

        $this->assertEquals('A test category description', $category->description);
    }

    public function test_category_has_color(): void
    {
        $category = $this->createCategory(['color' => '#FF5733']);

        $this->assertEquals('#FF5733', $category->color);
    }

    public function test_category_has_icon(): void
    {
        $category = $this->createCategory(['icon' => 'box']);

        $this->assertEquals('box', $category->icon);
    }

    public function test_category_has_sort_order(): void
    {
        $category = $this->createCategory(['sort_order' => 5]);

        $this->assertEquals(5, $category->sort_order);
    }

    public function test_category_has_metadata(): void
    {
        $metadata = ['custom_field' => 'value', 'another' => 123];
        $category = $this->createCategory(['metadata' => $metadata]);

        $this->assertIsArray($category->metadata);
        $this->assertEquals('value', $category->metadata['custom_field']);
    }

    public function test_category_depth_attribute(): void
    {
        $category = $this->createCategory(['depth' => 2]);

        $this->assertEquals(2, $category->depth);
    }

    public function test_category_nested_set_attributes(): void
    {
        $category = $this->createCategory(['lft' => 1, 'rgt' => 10]);

        $this->assertEquals(1, $category->lft);
        $this->assertEquals(10, $category->rgt);
    }

    public function test_category_is_active_cast(): void
    {
        $activeCategory = $this->createCategory(['is_active' => true]);
        $inactiveCategory = $this->createCategory(['is_active' => false]);

        $this->assertTrue($activeCategory->is_active);
        $this->assertFalse($inactiveCategory->is_active);
    }

    public function test_category_children_ordered_by_sort_order(): void
    {
        $parent = $this->createCategory(['name' => 'Parent']);
        $child3 = $this->createCategory(['name' => 'Third', 'parent_id' => $parent->id, 'sort_order' => 3]);
        $child1 = $this->createCategory(['name' => 'First', 'parent_id' => $parent->id, 'sort_order' => 1]);
        $child2 = $this->createCategory(['name' => 'Second', 'parent_id' => $parent->id, 'sort_order' => 2]);

        $children = $parent->children;

        $this->assertEquals('First', $children->first()->name);
        $this->assertEquals('Third', $children->last()->name);
    }

    public function test_category_factory_creates_valid_category(): void
    {
        $category = Category::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->assertNotNull($category->id);
        $this->assertNotNull($category->name);
        $this->assertNotNull($category->slug);
        $this->assertNotNull($category->organization_id);
    }

    public function test_category_factory_inactive_state(): void
    {
        $category = Category::factory()->inactive()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->assertFalse($category->is_active);
    }

    public function test_category_factory_with_parent_state(): void
    {
        $parent = $this->createCategory(['depth' => 0]);
        $child = Category::factory()->withParent($parent)->create();

        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertEquals($parent->organization_id, $child->organization_id);
        $this->assertEquals(1, $child->depth);
    }

    public function test_multiple_categories_can_share_same_parent(): void
    {
        $parent = $this->createCategory(['name' => 'Parent']);
        $children = [];

        for ($i = 0; $i < 5; $i++) {
            $children[] = $this->createCategory([
                'name' => "Child $i",
                'parent_id' => $parent->id,
            ]);
        }

        $this->assertCount(5, $parent->children);
    }

    public function test_category_can_be_deleted(): void
    {
        $category = $this->createCategory();
        $categoryId = $category->id;

        $category->delete();

        $this->assertNull(Category::find($categoryId));
    }

    public function test_category_fillable_attributes(): void
    {
        $category = $this->createCategory([
            'name' => 'Test Name',
            'description' => 'Test Description',
            'color' => '#123456',
            'icon' => 'folder',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this->assertEquals('Test Name', $category->name);
        $this->assertEquals('Test Description', $category->description);
        $this->assertEquals('#123456', $category->color);
        $this->assertEquals('folder', $category->icon);
        $this->assertEquals(10, $category->sort_order);
        $this->assertTrue($category->is_active);
    }
}
