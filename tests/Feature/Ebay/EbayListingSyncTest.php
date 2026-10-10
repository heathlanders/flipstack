<?php

namespace Tests\Feature\Ebay;

use App\Jobs\Ebay\SyncProductToEbay;
use App\Jobs\Ebay\UpdateEbayListing;
use App\Models\Catalog\Product;
use App\Models\Ebay\EbayListing;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Services\Ebay\EbayListingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EbayListingSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->team = Team::factory()->create(['organization_id' => $this->organization->id]);
    }

    protected function credential(): IntegrationCredential
    {
        return IntegrationCredential::create([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => 'ebay',
            'is_active' => true,
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
        ]);
    }

    protected function product(): Product
    {
        return Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    protected function listing(Product $product, IntegrationCredential $cred, string $status, ?string $ebayListingId = '110123'): EbayListing
    {
        return EbayListing::create([
            'product_id' => $product->id,
            'integration_credential_id' => $cred->id,
            'team_id' => $this->team->id,
            'ebay_sku' => 'SKU-'.substr($product->id, 0, 8),
            'ebay_listing_id' => $ebayListingId,
            'status' => $status,
        ]);
    }

    public function test_constructor_accepts_all_caller_arguments(): void
    {
        // Regression: callers pass (product, credential, categoryId, force, userId).
        $product = $this->product();
        $cred = $this->credential();

        $job = new SyncProductToEbay($product, $cred, '9355', false, 42);

        $this->assertSame($product->id, $job->product->id);
        $this->assertSame($cred->id, $job->credential->id);
        $this->assertSame('9355', $job->categoryId);
        $this->assertFalse($job->forceUpdate);
        $this->assertSame(42, $job->userId);
    }

    public function test_existing_published_listing_routes_to_update_job(): void
    {
        Queue::fake();
        $cred = $this->credential();
        $product = $this->product();
        $this->listing($product, $cred, EbayListing::STATUS_PUBLISHED);

        app(EbayListingService::class)->syncProduct($product, $cred);

        Queue::assertPushed(UpdateEbayListing::class);
    }

    public function test_pending_listing_is_unstuck_then_updated(): void
    {
        Queue::fake();
        $cred = $this->credential();
        $product = $this->product();
        $listing = $this->listing($product, $cred, EbayListing::STATUS_PENDING);

        app(EbayListingService::class)->syncProduct($product, $cred);

        $listing->refresh();
        $this->assertSame(EbayListing::STATUS_PUBLISHED, $listing->status);
        Queue::assertPushed(UpdateEbayListing::class);
    }

    public function test_throws_when_no_credential_available(): void
    {
        $product = $this->product();

        $this->expectException(\RuntimeException::class);
        app(EbayListingService::class)->syncProduct($product);
    }
}
