<?php

namespace Tests\Feature\Marketplaces;

use App\Jobs\Shopify\BulkSyncToShopify;
use App\Jobs\Shopify\ImportShopifyProducts;
use App\Jobs\Shopify\SyncProductToShopify;
use App\Models\Shopify\ShopifyListing;
use App\Models\Shopify\ShopifySyncLog;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\CreatesShopifyFixtures;

class ShopifyMarketplaceTest extends TestCase
{
    use CreatesShopifyFixtures;
    use RefreshDatabase;
    use WithoutMiddleware;

    protected User $nonAdminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopifyFixtures();
        $this->mockShopifyConfigured();

        $buyerRole = Role::factory()->create([
            'name' => 'Buyer',
            'slug' => 'buyer',
            'is_system' => true,
            'permissions' => ['inventory.view'],
        ]);

        $this->nonAdminUser = User::factory()->create([
            'organization_id' => $this->shopifyOrganization->id,
        ]);
        $this->nonAdminUser->roles()->attach($buyerRole->id, [
            'organization_id' => $this->shopifyOrganization->id,
            'team_id' => null,
        ]);

        OnboardingProgress::create([
            'user_id' => $this->nonAdminUser->id,
            'organization_id' => $this->shopifyOrganization->id,
            'started_at' => now(),
            'completed_at' => now(),
            'current_step' => 'completed',
            'steps_completed' => [],
        ]);
    }

    public function test_index_page_renders(): void
    {
        $response = $this->actingAs($this->shopifyUser)->get(route('marketplaces.shopify.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Shopify/Settings/Index'));
    }

    public function test_index_shows_connection_status_connected(): void
    {
        $response = $this->actingAs($this->shopifyUser)->get(route('marketplaces.shopify.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Shopify/Settings/Index')
            ->where('integrationStatus.connected', true)
            ->where('integrationStatus.shop_name', 'Test Store')
            ->where('integrationStatus.shop_domain', 'test-store.myshopify.com')
        );
    }

    public function test_index_shows_disconnected_status(): void
    {
        // Deactivate credential
        $this->shopifyCredential->update(['is_active' => false]);

        $response = $this->actingAs($this->shopifyUser)->get(route('marketplaces.shopify.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('integrationStatus.connected', false)
        );
    }

    public function test_index_shows_listing_stats(): void
    {
        $product1 = $this->createTestProduct();
        $product2 = $this->createTestProduct();
        $product3 = $this->createTestProduct();
        $product4 = $this->createTestProduct();

        $this->createShopifyListing($product1, ['status' => ShopifyListing::STATUS_ACTIVE]);
        $this->createShopifyListing($product2, ['status' => ShopifyListing::STATUS_ACTIVE]);
        $this->createShopifyListing($product3, ['status' => ShopifyListing::STATUS_DRAFT]);
        $this->createShopifyListing($product4, ['status' => ShopifyListing::STATUS_ERROR]);

        $response = $this->actingAs($this->shopifyUser)->get(route('marketplaces.shopify.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('stats.total', 4)
            ->where('stats.active', 2)
            ->where('stats.draft', 1)
            ->where('stats.error', 1)
        );
    }

    public function test_products_page_renders(): void
    {
        $response = $this->actingAs($this->shopifyUser)->get(route('marketplaces.shopify.products'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Shopify/Products/Index'));
    }

    public function test_products_page_lists_active_products(): void
    {
        $active = $this->createTestProduct(['status' => 'active', 'stock_quantity' => 5]);
        $inactive = $this->createTestProduct(['status' => 'inactive', 'stock_quantity' => 5]);
        $noStock = $this->createTestProduct(['status' => 'active', 'stock_quantity' => 0]);

        $response = $this->actingAs($this->shopifyUser)->get(route('marketplaces.shopify.products'));

        $response->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
        );
    }

    public function test_sync_product_dispatches_job(): void
    {
        Queue::fake();

        $product = $this->createTestProduct();

        $response = $this->actingAs($this->shopifyUser)
            ->post(route('marketplaces.shopify.products.sync', $product));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Queue::assertPushed(SyncProductToShopify::class);
    }

    public function test_sync_product_requires_active_credential(): void
    {
        $this->shopifyCredential->update(['is_active' => false]);

        $product = $this->createTestProduct();

        $response = $this->actingAs($this->shopifyUser)
            ->post(route('marketplaces.shopify.products.sync', $product));

        $response->assertRedirect();
        $response->assertSessionHasErrors('shopify');
    }

    public function test_sync_bulk_dispatches_job(): void
    {
        Queue::fake();

        $products = [
            $this->createTestProduct(),
            $this->createTestProduct(),
        ];

        $response = $this->actingAs($this->shopifyUser)
            ->post(route('marketplaces.shopify.sync.bulk'), [
                'product_ids' => array_map(fn ($p) => $p->id, $products),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Queue::assertPushed(BulkSyncToShopify::class);
    }

    public function test_sync_bulk_validates_product_ids(): void
    {
        $response = $this->actingAs($this->shopifyUser)
            ->post(route('marketplaces.shopify.sync.bulk'), []);

        $response->assertSessionHasErrors('product_ids');
    }

    public function test_start_import_creates_bulk_job_and_dispatches(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->shopifyUser)
            ->post(route('marketplaces.shopify.import.start'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bulk_operation_jobs', [
            'organization_id' => $this->shopifyOrganization->id,
            'operation_type' => 'shopify_import',
            'status' => 'pending',
        ]);

        Queue::assertPushed(ImportShopifyProducts::class);
    }

    public function test_start_import_requires_connection(): void
    {
        $this->shopifyCredential->update(['is_active' => false]);

        $response = $this->actingAs($this->shopifyUser)
            ->post(route('marketplaces.shopify.import.start'));

        $response->assertRedirect();
        $response->assertSessionHasErrors('shopify');
    }

    public function test_logs_page_renders(): void
    {
        $response = $this->actingAs($this->shopifyUser)->get(route('marketplaces.shopify.logs'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Shopify/SyncLogs/Index'));
    }

    public function test_logs_page_filters_by_operation(): void
    {
        ShopifySyncLog::create([
            'integration_credential_id' => $this->shopifyCredential->id,
            'team_id' => $this->shopifyTeam->id,
            'operation' => ShopifySyncLog::OP_CREATE_PRODUCT,
            'direction' => ShopifySyncLog::DIRECTION_OUTBOUND,
            'status' => ShopifySyncLog::STATUS_SUCCESS,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        ShopifySyncLog::create([
            'integration_credential_id' => $this->shopifyCredential->id,
            'team_id' => $this->shopifyTeam->id,
            'operation' => ShopifySyncLog::OP_SET_INVENTORY,
            'direction' => ShopifySyncLog::DIRECTION_OUTBOUND,
            'status' => ShopifySyncLog::STATUS_SUCCESS,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->shopifyUser)
            ->get(route('marketplaces.shopify.logs', ['operation' => ShopifySyncLog::OP_CREATE_PRODUCT]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('logs.data', 1));
    }
}
