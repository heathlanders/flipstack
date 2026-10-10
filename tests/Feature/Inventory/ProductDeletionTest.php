<?php

namespace Tests\Feature\Inventory;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Organization\Organization;
use App\Models\User\Role;
use App\Models\User\User;
use App\Services\Inventory\ProductDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class ProductDeletionTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected User $user;

    protected Role $adminRole;

    protected Category $category;

    protected ProductDeletionService $deletionService;

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
                'inventory.bulk.manage',
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

        $this->deletionService = app(ProductDeletionService::class);
    }

    protected function createProduct(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
        ], $attributes));
    }

    // ==========================================
    // Archive (Soft Delete) Tests
    // ==========================================

    public function test_archive_sets_deleted_at_and_status(): void
    {
        $product = $this->createProduct(['status' => 'active']);

        $result = $this->deletionService->archive($product, $this->user);

        $this->assertTrue($result);
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $product->refresh();
        $this->assertEquals('archived', $product->status);
        $this->assertNotNull($product->deleted_at);
    }

    public function test_archived_products_hidden_from_normal_queries(): void
    {
        $activeProduct = $this->createProduct(['name' => 'Active', 'status' => 'active']);
        $archivedProduct = $this->createProduct(['name' => 'To Archive', 'status' => 'active']);

        $this->deletionService->archive($archivedProduct, $this->user);

        // Normal query should only return the active product
        $products = Product::where('organization_id', $this->organization->id)->get();
        $this->assertCount(1, $products);
        $this->assertEquals($activeProduct->id, $products->first()->id);
    }

    public function test_archived_products_visible_with_trashed(): void
    {
        $product = $this->createProduct(['status' => 'active']);
        $this->deletionService->archive($product, $this->user);

        $found = Product::withTrashed()->find($product->id);
        $this->assertNotNull($found);
        $this->assertTrue($found->trashed());
    }

    public function test_archive_via_web_route(): void
    {
        $product = $this->createProduct(['status' => 'active']);

        $response = $this->actingAs($this->user)
            ->delete(route('inventory.products.destroy', $product));

        $response->assertRedirect(route('inventory.products.index'));
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    // ==========================================
    // Restore Tests
    // ==========================================

    public function test_restore_clears_deleted_at_and_sets_draft(): void
    {
        $product = $this->createProduct(['status' => 'active']);
        $this->deletionService->archive($product, $this->user);

        $product->refresh();
        $this->assertTrue($product->trashed());

        $result = $this->deletionService->restore($product, $this->user);

        $this->assertTrue($result);
        $product->refresh();
        $this->assertNull($product->deleted_at);
        $this->assertEquals('draft', $product->status);
    }

    public function test_restore_via_web_route(): void
    {
        $product = $this->createProduct(['status' => 'active']);
        $this->deletionService->archive($product, $this->user);

        $response = $this->actingAs($this->user)
            ->post(route('inventory.products.restore', $product->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => 'draft',
            'deleted_at' => null,
        ]);
    }

    // ==========================================
    // Force Delete Tests
    // ==========================================

    public function test_force_delete_only_works_on_archived_products(): void
    {
        $product = $this->createProduct(['status' => 'active']);

        $this->expectException(\InvalidArgumentException::class);
        $this->deletionService->forceDelete($product, $this->user);
    }

    public function test_force_delete_permanently_removes_product(): void
    {
        $product = $this->createProduct(['status' => 'active']);
        $this->deletionService->archive($product, $this->user);

        $product->refresh();
        $this->deletionService->forceDelete($product, $this->user);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertNull(Product::withTrashed()->find($product->id));
    }

    public function test_force_delete_via_web_route(): void
    {
        $product = $this->createProduct(['status' => 'active']);
        $this->deletionService->archive($product, $this->user);

        $response = $this->actingAs($this->user)
            ->delete(route('inventory.products.force-destroy', $product->id));

        $response->assertRedirect(route('inventory.products.index'));
        $this->assertNull(Product::withTrashed()->find($product->id));
    }

    public function test_force_delete_cascades_related_records(): void
    {
        $product = $this->createProduct(['status' => 'active']);

        ProductImage::create([
            'product_id' => $product->id,
            'url' => 'test.jpg',
            'sort_order' => 0,
        ]);

        $this->deletionService->archive($product, $this->user);
        $product->refresh();
        $this->deletionService->forceDelete($product, $this->user);

        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id]);
    }

    // ==========================================
    // Deletion Impact Tests
    // ==========================================

    public function test_deletion_impact_returns_correct_counts(): void
    {
        $product = $this->createProduct(['status' => 'active']);

        ProductImage::create([
            'product_id' => $product->id,
            'url' => 'test1.jpg',
            'sort_order' => 0,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'url' => 'test2.jpg',
            'sort_order' => 1,
        ]);

        $impact = $this->deletionService->getDeletionImpact($product);

        $this->assertEquals($product->id, $impact['product_id']);
        $this->assertEquals(2, $impact['image_count']);
        $this->assertFalse($impact['has_active_listings']);
        $this->assertFalse($impact['has_pending_orders']);
        $this->assertFalse($impact['is_bundle_member']);
    }

    public function test_deletion_impact_via_web_route(): void
    {
        $product = $this->createProduct(['status' => 'active']);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.products.deletion-impact', $product));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'product_id',
            'product_name',
            'total_active_listings',
            'pending_order_items',
            'image_count',
            'has_active_listings',
            'has_pending_orders',
        ]);
    }

    // ==========================================
    // Bulk Operation Tests
    // ==========================================

    public function test_bulk_archive(): void
    {
        $products = collect([
            $this->createProduct(['name' => 'Product 1', 'status' => 'active']),
            $this->createProduct(['name' => 'Product 2', 'status' => 'active']),
            $this->createProduct(['name' => 'Product 3', 'status' => 'active']),
        ]);

        $result = $this->deletionService->bulkArchive(
            $products->pluck('id')->toArray(),
            $this->user
        );

        $this->assertEquals(3, $result['archived']);
        $this->assertEmpty($result['failed']);

        foreach ($products as $product) {
            $this->assertSoftDeleted('products', ['id' => $product->id]);
        }
    }

    public function test_bulk_restore(): void
    {
        $products = collect([
            $this->createProduct(['name' => 'Product 1', 'status' => 'active']),
            $this->createProduct(['name' => 'Product 2', 'status' => 'active']),
        ]);

        $ids = $products->pluck('id')->toArray();
        $this->deletionService->bulkArchive($ids, $this->user);

        $result = $this->deletionService->bulkRestore($ids, $this->user);

        $this->assertEquals(2, $result['restored']);

        foreach ($products as $product) {
            $product->refresh();
            $this->assertNull($product->deleted_at);
            $this->assertEquals('draft', $product->status);
        }
    }

    public function test_bulk_force_delete_only_trashed(): void
    {
        $archivedProduct = $this->createProduct(['status' => 'active']);
        $activeProduct = $this->createProduct(['status' => 'active']);

        $this->deletionService->archive($archivedProduct, $this->user);

        // Bulk force delete with both IDs - only the archived one should be deleted
        $result = $this->deletionService->bulkForceDelete(
            [$archivedProduct->id, $activeProduct->id],
            $this->user
        );

        // Only the archived product should be force-deleted (bulkForceDelete uses onlyTrashed)
        $this->assertEquals(1, $result['deleted']);
        $this->assertNull(Product::withTrashed()->find($archivedProduct->id));
        $this->assertNotNull(Product::find($activeProduct->id));
    }

    public function test_bulk_archive_via_web_route(): void
    {
        $products = collect([
            $this->createProduct(['name' => 'Product 1', 'status' => 'active']),
            $this->createProduct(['name' => 'Product 2', 'status' => 'active']),
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('inventory.products.bulk-action'), [
                'action' => 'delete',
                'product_ids' => $products->pluck('id')->toArray(),
            ]);

        $response->assertRedirect();

        foreach ($products as $product) {
            $this->assertSoftDeleted('products', ['id' => $product->id]);
        }
    }

    // ==========================================
    // Show Archived Products Tests
    // ==========================================

    public function test_archived_product_viewable_on_show_page(): void
    {
        $product = $this->createProduct(['status' => 'active']);
        $this->deletionService->archive($product, $this->user);

        $response = $this->actingAs($this->user)
            ->get(route('inventory.products.show', $product->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Inventory/Products/Show')
            ->where('isArchived', true)
        );
    }

    public function test_index_with_archived_filter(): void
    {
        $activeProduct = $this->createProduct(['name' => 'Active Product', 'status' => 'active']);
        $toArchive = $this->createProduct(['name' => 'Archived Product', 'status' => 'active']);
        $this->deletionService->archive($toArchive, $this->user);

        // Normal view should not show archived
        $response = $this->actingAs($this->user)
            ->get(route('inventory.products.index'));
        $response->assertStatus(200);

        // Archived view should show soft-deleted products
        $response = $this->actingAs($this->user)
            ->get(route('inventory.products.index', ['status' => 'archived']));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('showArchived', true));
    }

    // ==========================================
    // Permission Tests
    // ==========================================

    public function test_user_without_delete_permission_cannot_archive(): void
    {
        $viewerRole = Role::factory()->create([
            'name' => 'Viewer',
            'slug' => 'viewer',
            'is_system' => false,
            'permissions' => ['inventory.view'],
        ]);

        $viewer = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $viewer->roles()->attach($viewerRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        $product = $this->createProduct(['status' => 'active']);

        $response = $this->actingAs($viewer)
            ->delete(route('inventory.products.destroy', $product));

        $response->assertStatus(403);
        $this->assertNull(Product::find($product->id)->deleted_at);
    }

    public function test_user_without_delete_permission_cannot_restore(): void
    {
        $viewerRole = Role::factory()->create([
            'name' => 'Viewer',
            'slug' => 'viewer',
            'is_system' => false,
            'permissions' => ['inventory.view'],
        ]);

        $viewer = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $viewer->roles()->attach($viewerRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        $product = $this->createProduct(['status' => 'active']);
        $this->deletionService->archive($product, $this->user);

        $response = $this->actingAs($viewer)
            ->post(route('inventory.products.restore', $product->id));

        $response->assertStatus(403);
    }
}
