<?php

namespace Tests\Feature\Inventory;

use App\Contracts\MarketplaceAdapter;
use App\Jobs\Ebay\RetryFailedEbaySync;
use App\Jobs\Inventory\EndMarketplaceListings;
use App\Models\Catalog\Product;
use App\Models\Ebay\EbayListing;
use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Services\MarketplaceInventorySync;
use App\Services\MarketplaceListingSync;
use App\Services\MarketplacePriceSync;
use App\Services\MarketplaceRegistry;
use App\Services\MarketplaceSyncRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceInventorySyncTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->organization = Organization::factory()->create([
            'sku_prefix' => 'MIS',
            'sku_prefix_locked' => true,
        ]);

        $this->team = Team::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        config([
            'services.ebay.sandbox.client_id' => 'test-client-id',
            'services.ebay.sandbox.client_secret' => 'test-client-secret',
            'services.ebay.sandbox.ru_name' => 'test-ru-name',
            'services.ebay.production.client_id' => 'test-client-id',
            'services.ebay.production.client_secret' => 'test-client-secret',
            'services.ebay.production.ru_name' => 'test-ru-name',
        ]);
    }

    protected function createCredential(string $platform, bool $autoSync = false, array $extra = []): IntegrationCredential
    {
        return IntegrationCredential::create(array_merge([
            'organization_id' => $this->organization->id,
            'team_id' => $this->team->id,
            'platform' => $platform,
            'environment' => 'production',
            'name' => ucfirst($platform).' Test',
            'is_active' => true,
            'auto_sync_enabled' => $autoSync,
            'client_id' => 'test-client-id-'.$platform,
            'client_secret' => 'test-client-secret',
            'config' => [],
        ], $extra));
    }

    protected function createEbayReadyCredential(bool $autoSync = false): IntegrationCredential
    {
        return $this->createCredential('ebay', $autoSync, [
            'fulfillment_policy_id' => 'test-policy',
            'payment_policy_id' => 'test-policy',
            'return_policy_id' => 'test-policy',
            'merchant_location_key' => 'test-location',
        ]);
    }

    protected function createEbayListing(Product $product, IntegrationCredential $credential, array $extra = []): EbayListing
    {
        return EbayListing::create(array_merge([
            'product_id' => $product->id,
            'integration_credential_id' => $credential->id,
            'team_id' => $this->team->id,
            'status' => 'published',
            'ebay_sku' => 'TEST-SKU-'.uniqid(),
            'listed_price' => 19.99,
            'listed_quantity' => 10,
        ], $extra));
    }

    // -------------------------------------------------------
    // auto_sync_enabled guard tests
    // -------------------------------------------------------

    public function test_skips_push_when_auto_sync_disabled(): void
    {
        $credential = $this->createEbayReadyCredential(autoSync: false);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'available_quantity' => 5,
        ]);

        $listing = $this->createEbayListing($product, $credential);

        $sync = new MarketplaceInventorySync;
        $sync->pushQuantityToAll($product);

        // No eBay update jobs should be dispatched
        Queue::assertNotPushed(\App\Jobs\Ebay\UpdateEbayInventory::class);

        // Listing quantity should be unchanged
        $listing->refresh();
        $this->assertEquals(10, $listing->listed_quantity);
    }

    public function test_pushes_when_auto_sync_enabled(): void
    {
        $credential = $this->createEbayReadyCredential(autoSync: true);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'available_quantity' => 3,
        ]);

        $this->createEbayListing($product, $credential);

        $sync = new MarketplaceInventorySync;
        $sync->pushQuantityToAll($product);

        // When auto_sync is enabled, UpdateEbayInventory job should be dispatched
        Queue::assertPushed(\App\Jobs\Ebay\UpdateEbayInventory::class);
    }

    public function test_mixed_credentials_only_syncs_enabled_ones(): void
    {
        // eBay credential with sync OFF
        $ebayCredential = $this->createEbayReadyCredential(autoSync: false);

        // Shopify credential with sync ON
        $shopifyCredential = $this->createCredential('shopify', true);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'available_quantity' => 5,
        ]);

        // Mock both adapters to verify which platforms get called
        $ebayAdapter = $this->createMock(MarketplaceAdapter::class);
        $ebayAdapter->method('getPlatform')->willReturn('ebay');
        $ebayAdapter->method('getCredential')->willReturn($ebayCredential);
        $ebayAdapter->method('isAvailable')->willReturn(true);
        // eBay adapter should NEVER be asked for listings or quantity updates
        $ebayAdapter->expects($this->never())->method('getActiveListings');
        $ebayAdapter->expects($this->never())->method('updateQuantity');

        $shopifyAdapter = $this->createMock(MarketplaceAdapter::class);
        $shopifyAdapter->method('getPlatform')->willReturn('shopify');
        $shopifyAdapter->method('getCredential')->willReturn($shopifyCredential);
        $shopifyAdapter->method('isAvailable')->willReturn(true);
        // Shopify adapter SHOULD be asked for active listings
        $shopifyAdapter->expects($this->once())->method('getActiveListings')->willReturn(collect());

        $registry = $this->createMock(MarketplaceRegistry::class);
        $registry->method('getAdaptersForOrganization')
            ->willReturn([$ebayAdapter, $shopifyAdapter]);

        $sync = new MarketplaceInventorySync($registry, new MarketplaceSyncRuleEngine);
        $sync->pushQuantityToAll($product);
    }

    public function test_logs_skip_with_credential_and_platform_info(): void
    {
        $credential = $this->createEbayReadyCredential(autoSync: false);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'available_quantity' => 5,
        ]);

        $this->createEbayListing($product, $credential);

        Log::spy();

        $sync = new MarketplaceInventorySync;
        $sync->pushQuantityToAll($product);

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) use ($credential, $product) {
                return str_contains($message, 'auto_sync_enabled is off')
                    && ($context['platform'] ?? null) === 'ebay'
                    && ($context['credential_id'] ?? null) === $credential->id
                    && ($context['product_id'] ?? null) === $product->id;
            })
            ->atLeast()->once();
    }

    public function test_order_triggered_push_respects_auto_sync_flag(): void
    {
        $credential = $this->createEbayReadyCredential(autoSync: false);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'available_quantity' => 5,
        ]);

        $this->createEbayListing($product, $credential);

        // Simulate what happens when PushInventoryToMarketplaces runs
        // (the same path triggered by order import -> reserveInventoryForOrder)
        $sync = app(MarketplaceInventorySync::class);
        $sync->pushQuantityToAll($product);

        // No eBay update jobs should be dispatched
        Queue::assertNotPushed(\App\Jobs\Ebay\UpdateEbayInventory::class);
    }

    public function test_enabling_auto_sync_allows_push(): void
    {
        $credential = $this->createEbayReadyCredential(autoSync: false);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'available_quantity' => 3,
        ]);

        $this->createEbayListing($product, $credential);

        // First push: should be blocked
        $sync = new MarketplaceInventorySync;
        $sync->pushQuantityToAll($product);
        Queue::assertNotPushed(\App\Jobs\Ebay\UpdateEbayInventory::class);

        // Enable auto_sync
        $credential->update(['auto_sync_enabled' => true]);

        // Second push: should go through
        $sync = new MarketplaceInventorySync;
        $sync->pushQuantityToAll($product);
        Queue::assertPushed(\App\Jobs\Ebay\UpdateEbayInventory::class);
    }

    // -------------------------------------------------------
    // Price sync guard tests
    // -------------------------------------------------------

    public function test_price_sync_skips_when_auto_sync_disabled(): void
    {
        $credential = $this->createEbayReadyCredential(autoSync: false);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'selling_price' => 29.99,
        ]);

        $this->createEbayListing($product, $credential);

        Log::spy();

        $sync = new MarketplacePriceSync;
        $sync->pushPriceToAll($product);

        Queue::assertNotPushed(\App\Jobs\Ebay\UpdateEbayInventory::class);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => str_contains($msg, 'MarketplacePriceSync') && str_contains($msg, 'auto_sync_enabled is off'))
            ->atLeast()->once();
    }

    public function test_price_sync_pushes_when_auto_sync_enabled(): void
    {
        $credential = $this->createEbayReadyCredential(autoSync: true);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'selling_price' => 29.99,
        ]);

        $this->createEbayListing($product, $credential);

        $sync = new MarketplacePriceSync;
        $sync->pushPriceToAll($product);

        Queue::assertPushed(\App\Jobs\Ebay\UpdateEbayInventory::class);
    }

    // -------------------------------------------------------
    // Listing sync guard tests
    // -------------------------------------------------------

    public function test_listing_sync_skips_when_auto_sync_disabled(): void
    {
        $credential = $this->createEbayReadyCredential(autoSync: false);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->createEbayListing($product, $credential);

        Log::spy();

        $sync = new MarketplaceListingSync;
        $sync->pushListingToAll($product);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => str_contains($msg, 'MarketplaceListingSync') && str_contains($msg, 'auto_sync_enabled is off'))
            ->atLeast()->once();
    }

    // -------------------------------------------------------
    // End marketplace listings guard tests
    // -------------------------------------------------------

    public function test_end_listings_skips_when_auto_sync_disabled(): void
    {
        $credential = $this->createEbayReadyCredential(autoSync: false);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'archived',
        ]);

        $listing = $this->createEbayListing($product, $credential);

        Log::spy();

        $job = new EndMarketplaceListings($product->id);
        $job->handle(new MarketplaceRegistry);

        // Listing should still be published (not ended)
        $listing->refresh();
        $this->assertEquals('published', $listing->status);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => str_contains($msg, 'EndMarketplaceListings') && str_contains($msg, 'auto_sync_enabled is off'))
            ->atLeast()->once();
    }

    // -------------------------------------------------------
    // Retry failed eBay sync guard tests
    // -------------------------------------------------------

    public function test_retry_failed_skips_when_auto_sync_disabled(): void
    {
        $credential = $this->createEbayReadyCredential(autoSync: false);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
        ]);

        $listing = $this->createEbayListing($product, $credential, [
            'status' => EbayListing::STATUS_ERROR,
            'consecutive_errors' => 1,
            'last_error_at' => now()->subMinutes(10),
            'last_error_message' => 'Test error',
            'last_error_code' => 'TEMPORARY_ERROR',
        ]);

        $job = new RetryFailedEbaySync($credential);
        $job->handle();

        // Listing should remain in error status (not reset to pending)
        $listing->refresh();
        $this->assertEquals(EbayListing::STATUS_ERROR, $listing->status);
    }

    public function test_retry_failed_proceeds_when_auto_sync_enabled(): void
    {
        $credential = $this->createEbayReadyCredential(autoSync: true);

        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
        ]);

        $listing = $this->createEbayListing($product, $credential, [
            'status' => EbayListing::STATUS_ERROR,
            'consecutive_errors' => 1,
            'last_error_at' => now()->subMinutes(10),
            'last_error_message' => 'Test error',
            'last_error_code' => 'TEMPORARY_ERROR',
        ]);

        $job = new RetryFailedEbaySync($credential);
        $job->handle();

        // Listing should be reset to pending for retry
        $listing->refresh();
        $this->assertEquals(EbayListing::STATUS_PENDING, $listing->status);
    }
}
