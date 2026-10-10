<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Shopify\RetryFailedShopifySync;
use App\Jobs\Shopify\SyncProductToShopify;
use App\Models\Shopify\ShopifyListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\CreatesShopifyFixtures;

class RetryFailedShopifySyncTest extends TestCase
{
    use CreatesShopifyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopifyFixtures();
    }

    public function test_retries_eligible_listings(): void
    {
        Queue::fake();

        $product = $this->createTestProduct(['status' => 'active']);
        $listing = $this->createShopifyListing($product, [
            'status' => ShopifyListing::STATUS_ERROR,
            'consecutive_errors' => 2,
            'last_error_at' => now()->subMinutes(10), // More than 5 min ago
        ]);

        $job = new RetryFailedShopifySync;
        $job->handle();

        $listing->refresh();
        $this->assertEquals(ShopifyListing::STATUS_PENDING, $listing->status);

        Queue::assertPushed(SyncProductToShopify::class, 1);
    }

    public function test_skips_listings_at_max_errors(): void
    {
        Queue::fake();

        $product = $this->createTestProduct(['status' => 'active']);
        $this->createShopifyListing($product, [
            'status' => ShopifyListing::STATUS_ERROR,
            'consecutive_errors' => 5,
            'last_error_at' => now()->subMinutes(10),
        ]);

        $job = new RetryFailedShopifySync;
        $job->handle();

        Queue::assertNotPushed(SyncProductToShopify::class);
    }

    public function test_skips_recent_errors(): void
    {
        Queue::fake();

        $product = $this->createTestProduct(['status' => 'active']);
        $this->createShopifyListing($product, [
            'status' => ShopifyListing::STATUS_ERROR,
            'consecutive_errors' => 1,
            'last_error_at' => now()->subMinutes(2), // Less than 5 min ago
        ]);

        $job = new RetryFailedShopifySync;
        $job->handle();

        Queue::assertNotPushed(SyncProductToShopify::class);
    }

    public function test_filters_by_credential(): void
    {
        Queue::fake();

        $product = $this->createTestProduct(['status' => 'active']);
        $this->createShopifyListing($product, [
            'status' => ShopifyListing::STATUS_ERROR,
            'consecutive_errors' => 2,
            'last_error_at' => now()->subMinutes(10),
        ]);

        // Use a different credential (different org to avoid unique constraint)
        $otherOrg = \App\Models\Organization\Organization::factory()->create();
        $otherCredential = \App\Models\Integration\IntegrationCredential::create([
            'organization_id' => $otherOrg->id,
            'team_id' => $this->shopifyTeam->id,
            'platform' => 'shopify',
            'environment' => 'production',
            'name' => 'Other Store',
            'is_active' => true,
            'client_id' => 'other-client-id',
            'config' => ['shop_domain' => 'other-store.myshopify.com'],
        ]);

        $job = new RetryFailedShopifySync($otherCredential);
        $job->handle();

        Queue::assertNotPushed(SyncProductToShopify::class);
    }
}
