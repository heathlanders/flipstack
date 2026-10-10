<?php

namespace App\Console\Commands\Shopify;

use App\Models\Integration\IntegrationCredential;
use App\Services\Shopify\ShopifyConfigService;
use App\Services\Shopify\ShopifyInventoryService;
use App\Services\Shopify\ShopifyOAuthService;
use App\Services\Shopify\ShopifyProductService;
use App\Services\Shopify\ShopifyWebhookService;
use Illuminate\Console\Command;

class TestShopifyConnectionCommand extends Command
{
    protected $signature = 'shopify:test-connection
                           {--credential-id= : Test specific credential ID}
                           {--full : Run full test suite (products, inventory, webhooks)}
                           {--cleanup : Delete test data created during full test}';

    protected $description = 'Test Shopify API connection and functionality';

    protected array $results = [];

    protected ?int $testProductId = null;

    public function handle(ShopifyConfigService $configService, ShopifyOAuthService $oauthService): int
    {
        $this->info('Shopify Connection Test');
        $this->info('=======================');
        $this->newLine();

        // Step 1: Check env config
        $this->check('API credentials configured', $configService->isConfigured());

        if (! $configService->isConfigured()) {
            $this->error('SHOPIFY_API_KEY and SHOPIFY_API_SECRET must be set in .env');

            return $this->displaySummary();
        }

        // Step 2: Find credential
        $credential = $this->findCredential();

        if (! $credential) {
            $this->check('Integration credential found', false);
            $this->error('No Shopify integration credential found.');

            return $this->displaySummary();
        }

        $this->check("Integration credential active (ID: {$credential->id})", $credential->is_active);

        // Step 3: Check token
        $token = $credential->getActiveShopifyToken();
        $this->check('OAuth token valid', $token !== null);

        if (! $token) {
            $this->error('No active OAuth token. Re-connect via the Shopify OAuth flow.');

            return $this->displaySummary();
        }

        // Step 4: Verify API connection
        $shopDomain = $credential->config['shop_domain'] ?? 'unknown';
        $start = microtime(true);
        $verified = $oauthService->verifyCredentials($credential);
        $elapsed = round((microtime(true) - $start) * 1000);
        $this->check("API connection verified (200 OK, {$elapsed}ms)", $verified);

        if (! $verified) {
            $this->error('API verification failed. Token may be revoked.');

            return $this->displaySummary();
        }

        // Step 5: Shop info
        try {
            $shopInfo = $oauthService->getShopInfo($shopDomain, $token->decrypted_access_token);
            $shopName = $shopInfo['name'] ?? 'Unknown';
            $this->check("Shop info: \"{$shopName}\" ({$shopDomain})", true);
        } catch (\Exception $e) {
            $this->check('Shop info fetch', false);
        }

        // Step 6: Locations
        try {
            $inventoryService = new ShopifyInventoryService($credential);
            $locations = $inventoryService->getLocations();
            $primaryId = $locations[0]['id'] ?? 'none';
            $this->check('Locations: '.count($locations)." found (primary: {$primaryId})", ! empty($locations));
        } catch (\Exception $e) {
            $this->check('Location fetch', false);
        }

        // Full tests
        if ($this->option('full')) {
            $this->runFullTests($credential, $primaryId ?? null);
        }

        // Cleanup only
        if ($this->option('cleanup') && ! $this->option('full')) {
            $this->cleanupTestProduct($credential);
        }

        return $this->displaySummary();
    }

    protected function runFullTests(IntegrationCredential $credential, $locationId): void
    {
        $this->newLine();
        $this->info('--- Full Test Suite ---');

        // Create test product
        try {
            $productService = new ShopifyProductService($credential);

            $org = $credential->organization;
            $user = $org->users()->first();

            $product = \App\Models\Catalog\Product::create([
                'organization_id' => $org->id,
                'name' => '[CONNECTION-TEST] '.now()->format('Y-m-d H:i:s'),
                'description' => 'Automated connection test - safe to delete',
                'sku' => 'CONNTEST-'.now()->timestamp,
                'selling_price' => 1.00,
                'stock_quantity' => 10,
                'status' => 'active',
                'condition' => 'NEW',
                'manage_stock' => true,
                'created_by' => $user?->id,
            ]);

            $listing = \App\Models\Shopify\ShopifyListing::create([
                'product_id' => $product->id,
                'integration_credential_id' => $credential->id,
                'team_id' => $credential->team_id,
                'shopify_sku' => $product->sku,
                'status' => 'pending',
                'currency' => 'USD',
            ]);

            $result = $productService->createProduct($product, $listing);
            $this->testProductId = $result['id'] ?? null;
            $this->check("Product create: ID {$this->testProductId}", $this->testProductId !== null);
        } catch (\Exception $e) {
            $this->check('Product create', false);
            $this->warn("  Error: {$e->getMessage()}");

            return;
        }

        // Fetch product
        try {
            $fetched = $productService->getProduct($this->testProductId);
            $this->check('Product fetch: confirmed', ! empty($fetched));
        } catch (\Exception $e) {
            $this->check('Product fetch', false);
        }

        // Set inventory
        if ($locationId && $listing->shopify_inventory_item_id) {
            try {
                $inventoryService = new ShopifyInventoryService($credential);
                $inventoryService->setInventoryLevel($locationId, $listing->shopify_inventory_item_id, 10);
                $this->check("Inventory set: 10 units at location {$locationId}", true);
            } catch (\Exception $e) {
                $this->check('Inventory set', false);
            }
        }

        // Webhooks
        try {
            $webhookService = new ShopifyWebhookService($credential);
            $webhooks = $webhookService->getWebhooks();
            $this->check('Webhooks: '.count($webhooks).' registered', true);
        } catch (\Exception $e) {
            $this->check('Webhook list', false);
        }

        // Register/delete webhook cycle
        try {
            $webhookService = $webhookService ?? new ShopifyWebhookService($credential);
            $result = $webhookService->registerWebhooks();
            $registered = collect($result)->filter(fn ($r) => isset($r['id']))->count();
            $alreadyExists = collect($result)->filter(fn ($r) => ($r['status'] ?? '') === 'already_exists')->count();
            $this->check("Webhook register/delete cycle: OK ({$registered} new, {$alreadyExists} existing)", true);
        } catch (\Exception $e) {
            $this->check('Webhook register/delete cycle', false);
        }

        // Cleanup
        $this->cleanupTestProduct($credential);
    }

    protected function cleanupTestProduct(IntegrationCredential $credential): void
    {
        if (! $this->testProductId) {
            // Try finding any leftover test products
            $listing = \App\Models\Shopify\ShopifyListing::where('integration_credential_id', $credential->id)
                ->whereHas('product', function ($q) {
                    $q->where('name', 'like', '[CONNECTION-TEST]%');
                })
                ->first();

            if ($listing && $listing->shopify_product_id) {
                $this->testProductId = $listing->shopify_product_id;
            }
        }

        if ($this->testProductId) {
            try {
                $listing = \App\Models\Shopify\ShopifyListing::where('shopify_product_id', $this->testProductId)->first();

                if ($listing) {
                    $productService = new ShopifyProductService($credential);
                    $productService->deleteProduct($listing);
                }

                $this->check('Test product cleaned up', true);
            } catch (\Exception $e) {
                $this->check('Test product cleanup', false);
                $this->warn("  Cleanup error: {$e->getMessage()}");
            }
        }
    }

    protected function findCredential(): ?IntegrationCredential
    {
        if ($id = $this->option('credential-id')) {
            return IntegrationCredential::find($id);
        }

        return IntegrationCredential::where('platform', 'shopify')
            ->where('is_active', true)
            ->first();
    }

    protected function check(string $label, bool $passed): void
    {
        $icon = $passed ? '[PASS]' : '[FAIL]';
        $style = $passed ? 'info' : 'error';

        $this->line("  <{$style}>{$icon}</{$style}> {$label}");
        $this->results[] = ['label' => $label, 'passed' => $passed];
    }

    protected function displaySummary(): int
    {
        $this->newLine();

        $total = count($this->results);
        $passed = collect($this->results)->filter(fn ($r) => $r['passed'])->count();

        $this->info("Result: {$passed}/{$total} checks passed");

        return $passed === $total ? Command::SUCCESS : Command::FAILURE;
    }
}
