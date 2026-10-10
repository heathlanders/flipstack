<?php

namespace Tests\Feature\Product;

use App\Jobs\Inventory\PushListingToMarketplaces;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;
use App\Services\BackgroundRemovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AssertsAudits;
use Tests\TestCase;

class ProductImageEditTest extends TestCase
{
    use AssertsAudits;
    use RefreshDatabase;

    protected Organization $organization;

    protected Team $team;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable only CSRF, keeping route-model binding (SubstituteBindings) intact.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        Storage::fake('public');
        Queue::fake(); // saving the product triggers PushListingToMarketplaces via observer
        config(['services.background_removal.url' => 'http://bg-test']);

        // Route-level `can:` gating is bypassed; controller hasPermission() checks still run.
        Gate::before(fn () => true);

        $this->organization = Organization::factory()->create();
        $this->team = Team::factory()->create(['organization_id' => $this->organization->id]);

        $editorRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'permissions' => ['inventory.view', 'inventory.update'],
        ]);

        $this->user = $this->makeUser($editorRole);
    }

    protected function makeUser(Role $role): User
    {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $user->roles()->attach($role->id, ['organization_id' => $this->organization->id, 'team_id' => null]);
        $user->current_team_id = $this->team->id;
        $user->save();
        $this->team->users()->attach($user->id, ['role' => 'admin']);

        OnboardingProgress::create([
            'user_id' => $user->id,
            'organization_id' => $this->organization->id,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'current_step' => 'completed',
            'steps_completed' => ['welcome', 'profile', 'organization', 'team'],
        ]);

        return $user;
    }

    protected function makeProduct(): Product
    {
        return Product::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Image with a tracked original on the fake disk, ready to reprocess/revert.
     */
    protected function makeImageWithOriginal(Product $product): ProductImage
    {
        Storage::disk('public')->put('queue-photos/orig.jpg', 'ORIGINALBYTES');
        Storage::disk('public')->put('queue-photos-processed/orig.png', 'PROCESSEDBYTES');

        return ProductImage::create([
            'product_id' => $product->id,
            'url' => 'queue-photos-processed/orig.png',
            'is_primary' => true,
            'sort_order' => 0,
            'metadata' => ['original_url' => 'queue-photos/orig.jpg', 'bg_removed' => true],
        ]);
    }

    protected function fakeBgServer(): void
    {
        Http::fake([
            'bg-test/health' => Http::response(['status' => 'healthy'], 200),
            'bg-test/api/v1/process*' => Http::response([
                'success' => true,
                'image' => base64_encode('NEWPNGDATA'),
                'diagnostics' => ['coverage' => 0.62, 'holes_filled' => 1200, 'strength' => 'conservative'],
            ], 200),
        ]);
    }

    public function test_reprocess_updates_url_metadata_and_audits(): void
    {
        $this->fakeBgServer();
        $product = $this->makeProduct();
        $image = $this->makeImageWithOriginal($product);

        $response = $this->actingAs($this->user)->post(
            route('inventory.products.images.reprocess', ['product' => $product->id, 'image' => $image->id]),
            ['strength' => 'conservative'],
        );

        $response->assertRedirect();

        $image->refresh();
        $this->assertNotSame('queue-photos-processed/orig.png', $image->url);
        $this->assertStringStartsWith('queue-photos-processed/orig__v', $image->url);
        $this->assertTrue($image->metadata['bg_removed']);
        $this->assertSame('queue-photos/orig.jpg', $image->metadata['original_url']);

        $this->assertAudited('product_image.reprocessed', $this->user, [
            'image_id' => $image->id,
            'product_id' => $product->id,
        ]);

        // gallery_images mirrored to the new processed url + marketplace push queued.
        $product->refresh();
        $this->assertContains($image->url, $product->gallery_images);
        Queue::assertPushed(PushListingToMarketplaces::class);
    }

    public function test_reprocess_legacy_row_sources_from_surviving_original(): void
    {
        // Legacy row: url points at a processed file that no longer exists on
        // disk, no metadata. The original survives under the same basename.
        $this->fakeBgServer();
        $product = $this->makeProduct();
        Storage::disk('public')->put('queue-photos/legacy123.jpg', 'ORIGINALBYTES');
        $image = ProductImage::create([
            'product_id' => $product->id,
            'url' => 'queue-photos-processed/legacy123.jpg', // missing on disk
            'is_primary' => true,
            'sort_order' => 0,
            'metadata' => [],
        ]);

        $this->actingAs($this->user)->post(
            route('inventory.products.images.reprocess', ['product' => $product->id, 'image' => $image->id]),
            ['strength' => 'conservative'],
        )->assertRedirect();

        $image->refresh();
        $this->assertStringStartsWith('queue-photos-processed/legacy123__v', $image->url);
        $this->assertSame('queue-photos/legacy123.jpg', $image->metadata['original_url']);
        $this->assertTrue($image->metadata['bg_removed']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/process'));
    }

    public function test_bulk_reprocess_processes_selected_images_and_audits(): void
    {
        $this->fakeBgServer();
        $product = $this->makeProduct();
        $img1 = $this->makeImageWithOriginal($product);
        Storage::disk('public')->put('queue-photos/orig2.jpg', 'O2');
        $img2 = ProductImage::create([
            'product_id' => $product->id,
            'url' => 'queue-photos-processed/orig2.png',
            'sort_order' => 1,
            'metadata' => ['original_url' => 'queue-photos/orig2.jpg', 'bg_removed' => true],
        ]);
        // A third image we deliberately do NOT select.
        $img3 = ProductImage::create([
            'product_id' => $product->id,
            'url' => 'queue-photos/untouched.jpg',
            'sort_order' => 2,
            'metadata' => [],
        ]);

        $this->actingAs($this->user)->post(
            route('inventory.products.images.reprocess-bulk', $product->id),
            ['image_ids' => [$img1->id, $img2->id], 'strength' => 'conservative'],
        )->assertRedirect();

        $img1->refresh();
        $img2->refresh();
        $img3->refresh();
        $this->assertStringContainsString('__v', $img1->url);
        $this->assertStringContainsString('__v', $img2->url);
        $this->assertSame('queue-photos/untouched.jpg', $img3->url); // unselected, unchanged

        $this->assertAudited('product_image.bulk_reprocessed', $this->user, [
            'product_id' => $product->id,
            'succeeded' => 2,
        ]);
    }

    public function test_revert_restores_original_and_audits(): void
    {
        $product = $this->makeProduct();
        $image = $this->makeImageWithOriginal($product);

        $response = $this->actingAs($this->user)->post(
            route('inventory.products.images.revert', ['product' => $product->id, 'image' => $image->id]),
        );

        $response->assertRedirect();

        $image->refresh();
        $this->assertSame('queue-photos/orig.jpg', $image->url);
        $this->assertFalse($image->metadata['bg_removed']);

        $this->assertAudited('product_image.reverted', $this->user, ['image_id' => $image->id]);
    }

    public function test_revert_without_original_is_rejected_and_not_audited(): void
    {
        $product = $this->makeProduct();
        $image = ProductImage::create([
            'product_id' => $product->id,
            'url' => 'queue-photos/legacy.jpg',
            'is_primary' => true,
            'sort_order' => 0,
            'metadata' => [],
        ]);

        $this->actingAs($this->user)->post(
            route('inventory.products.images.revert', ['product' => $product->id, 'image' => $image->id]),
        )->assertRedirect();

        $image->refresh();
        $this->assertSame('queue-photos/legacy.jpg', $image->url);
        $this->assertNotAudited('product_image.reverted', $this->user);
    }

    public function test_replace_stores_new_original_and_audits(): void
    {
        $product = $this->makeProduct();
        $image = $this->makeImageWithOriginal($product);

        $response = $this->actingAs($this->user)->post(
            route('inventory.products.images.replace', ['product' => $product->id, 'image' => $image->id]),
            ['image' => UploadedFile::fake()->image('replacement.jpg')],
        );

        $response->assertRedirect();

        $image->refresh();
        $this->assertStringStartsWith('queue-photos/', $image->url);
        Storage::disk('public')->assertExists($image->url);
        $this->assertFalse($image->metadata['bg_removed']);
        $this->assertSame($image->url, $image->metadata['original_url']);

        $this->assertAudited('product_image.replaced', $this->user, ['image_id' => $image->id]);
    }

    public function test_sync_marketplaces_mirrors_gallery_and_queues_push(): void
    {
        $product = $this->makeProduct();
        // Image already reprocessed before auto-push existed: product_images is
        // current, but gallery_images is stale/empty.
        Storage::disk('public')->put('queue-photos-processed/done__v1.png', 'X');
        $image = ProductImage::create([
            'product_id' => $product->id,
            'url' => 'queue-photos-processed/done__v1.png',
            'is_primary' => true,
            'sort_order' => 0,
            'metadata' => ['original_url' => 'queue-photos/done.jpg', 'bg_removed' => true],
        ]);
        $product->update(['gallery_images' => []]);

        $this->actingAs($this->user)->post(
            route('inventory.products.images.sync-marketplaces', $product->id),
        )->assertRedirect();

        $product->refresh();
        $this->assertSame(['queue-photos-processed/done__v1.png'], $product->gallery_images);
        $this->assertSame('queue-photos-processed/done__v1.png', $product->featured_image);
        Queue::assertPushed(PushListingToMarketplaces::class);
        $this->assertAudited('product_image.marketplace_sync_requested', $this->user, ['product_id' => $product->id]);
    }

    public function test_non_editor_is_forbidden_and_not_audited(): void
    {
        $this->fakeBgServer();
        $viewerRole = Role::factory()->create([
            'name' => 'Viewer',
            'slug' => 'viewer',
            'is_system' => true,
            'permissions' => ['inventory.view'],
        ]);
        $viewer = $this->makeUser($viewerRole);

        $product = $this->makeProduct();
        $image = $this->makeImageWithOriginal($product);

        $this->actingAs($viewer)->post(
            route('inventory.products.images.reprocess', ['product' => $product->id, 'image' => $image->id]),
            ['strength' => 'conservative'],
        )->assertForbidden();

        $this->assertNotAudited('product_image.reprocessed', $viewer);
    }

    public function test_cross_product_image_returns_404(): void
    {
        $productA = $this->makeProduct();
        $productB = $this->makeProduct();
        $image = $this->makeImageWithOriginal($productB);

        $this->actingAs($this->user)->post(
            route('inventory.products.images.revert', ['product' => $productA->id, 'image' => $image->id]),
        )->assertNotFound();
    }

    public function test_service_serializes_strength_model_and_erode_into_query(): void
    {
        Storage::disk('public')->put('queue-photos/src.jpg', 'SRCBYTES');
        Http::fake([
            'bg-test/api/v1/process*' => Http::response([
                'image' => base64_encode('OUT'),
                'diagnostics' => [],
            ], 200),
        ]);

        $service = new BackgroundRemovalService;
        $result = $service->removeBackgroundWithOptions('queue-photos/src.jpg', [
            'strength' => 'conservative',
            'model' => 'u2net',
            'erode_size' => 5,
        ]);

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            $url = $request->url();

            return str_contains($url, 'strength=conservative')
                && str_contains($url, 'model=u2net')
                && str_contains($url, 'erode_size=5');
        });
    }
}
