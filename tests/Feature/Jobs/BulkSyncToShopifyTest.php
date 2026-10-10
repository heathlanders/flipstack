<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Shopify\BulkSyncToShopify;
use App\Jobs\Shopify\SyncProductToShopify;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\CreatesShopifyFixtures;

class BulkSyncToShopifyTest extends TestCase
{
    use CreatesShopifyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopifyFixtures();
    }

    public function test_dispatches_individual_sync_jobs(): void
    {
        Queue::fake();

        $products = [
            $this->createTestProduct(['status' => 'active']),
            $this->createTestProduct(['status' => 'active']),
            $this->createTestProduct(['status' => 'active']),
        ];

        $job = new BulkSyncToShopify(
            array_map(fn ($p) => $p->id, $products),
            $this->shopifyCredential->id,
            $this->shopifyTeam->id
        );
        $job->handle();

        Queue::assertPushed(SyncProductToShopify::class, 3);
    }

    public function test_handles_empty_product_list(): void
    {
        Queue::fake();

        $job = new BulkSyncToShopify(
            [],
            $this->shopifyCredential->id,
            $this->shopifyTeam->id
        );
        $job->handle();

        Queue::assertNotPushed(SyncProductToShopify::class);
    }

    public function test_skips_inactive_products(): void
    {
        Queue::fake();

        $active = $this->createTestProduct(['status' => 'active']);
        $inactive = $this->createTestProduct(['status' => 'inactive']);

        $job = new BulkSyncToShopify(
            [$active->id, $inactive->id],
            $this->shopifyCredential->id,
            $this->shopifyTeam->id
        );
        $job->handle();

        Queue::assertPushed(SyncProductToShopify::class, 1);
    }
}
