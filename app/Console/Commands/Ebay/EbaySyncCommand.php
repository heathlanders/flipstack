<?php

namespace App\Console\Commands\Ebay;

use App\Jobs\Ebay\BulkSyncToEbay;
use App\Jobs\Ebay\SyncProductToEbay;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Services\EbayInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class EbaySyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ebay:sync 
                            {--credential= : Integration credential ID}
                            {--team= : Team ID}
                            {--product= : Specific product ID to sync}
                            {--sku= : Specific product SKU to sync}
                            {--category= : eBay category ID to use}
                            {--all : Sync all pending products}
                            {--limit=50 : Maximum products to sync}
                            {--sync : Run synchronously (not queued)}
                            {--dry-run : Show what would be synced without syncing}';

    /**
     * The console command description.
     */
    protected $description = 'Sync products to eBay';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('eBay Product Sync');
        $this->line('');

        // Get credential
        $credential = $this->getCredential();
        if (! $credential) {
            return Command::FAILURE;
        }

        $this->displayCredentialInfo($credential);

        // Check credential readiness
        if (! $credential->isEbayReady()) {
            $this->error('Credential is not fully configured.');
            $this->warn('Missing: '.$this->getMissingConfig($credential));
            $this->line('Run "php artisan ebay:setup" to configure.');

            return Command::FAILURE;
        }

        // Determine what to sync
        if ($this->option('product')) {
            return $this->syncSingleProduct($credential, $this->option('product'));
        }

        if ($this->option('sku')) {
            return $this->syncBySku($credential, $this->option('sku'));
        }

        if ($this->option('all')) {
            return $this->syncAllPending($credential);
        }

        // Interactive mode
        return $this->interactiveSync($credential);
    }

    /**
     * Get the integration credential
     */
    protected function getCredential(): ?IntegrationCredential
    {
        if ($credentialId = $this->option('credential')) {
            $credential = IntegrationCredential::find($credentialId);
            if (! $credential) {
                $this->error("Credential ID {$credentialId} not found.");

                return null;
            }

            return $credential;
        }

        if ($teamId = $this->option('team')) {
            $team = \App\Models\Organization\Team::find($teamId);

            if (! $team) {
                $this->error("Team {$teamId} not found.");

                return null;
            }

            $credential = IntegrationCredential::where('organization_id', $team->organization_id)
                ->where('platform', 'ebay')
                ->where('is_active', true)
                ->first();

            if (! $credential) {
                $this->error("No active eBay credential found for team {$teamId}.");

                return null;
            }

            return $credential;
        }

        // List available credentials
        $credentials = IntegrationCredential::where('platform', 'ebay')
            ->where('is_active', true)
            ->get();

        if ($credentials->isEmpty()) {
            $this->error('No active eBay credentials found.');

            return null;
        }

        if ($credentials->count() === 1) {
            return $credentials->first();
        }

        // Let user choose
        $choices = $credentials->mapWithKeys(function ($c) {
            return [$c->id => "{$c->name} (Org: {$c->organization_id}, Env: {$c->environment})"];
        })->toArray();

        $selected = $this->choice('Select eBay credential:', $choices);

        return $credentials->firstWhere('id', array_search($selected, $choices));
    }

    /**
     * Display credential info
     */
    protected function displayCredentialInfo(IntegrationCredential $credential): void
    {
        $this->table(
            ['Setting', 'Value'],
            [
                ['Credential ID', $credential->id],
                ['Name', $credential->name ?? 'N/A'],
                ['Environment', $credential->environment],
                ['Marketplace', $credential->marketplace_id ?? 'EBAY_US'],
                ['Organization ID', $credential->organization_id],
                ['Has Policies', $credential->hasEbayPolicies() ? '[OK] Yes' : '[X] No'],
                ['Has Location', $credential->hasMerchantLocation() ? '[OK] Yes' : '[X] No'],
            ]
        );
        $this->line('');
    }

    /**
     * Get missing configuration items
     */
    protected function getMissingConfig(IntegrationCredential $credential): string
    {
        $missing = [];

        if (! $credential->fulfillment_policy_id) {
            $missing[] = 'Fulfillment Policy';
        }
        if (! $credential->payment_policy_id) {
            $missing[] = 'Payment Policy';
        }
        if (! $credential->return_policy_id) {
            $missing[] = 'Return Policy';
        }
        if (! $credential->merchant_location_key) {
            $missing[] = 'Merchant Location';
        }

        return implode(', ', $missing);
    }

    /**
     * Sync a single product by ID
     */
    protected function syncSingleProduct(IntegrationCredential $credential, string $productId): int
    {
        $product = Product::find($productId);

        if (! $product) {
            $this->error("Product {$productId} not found.");

            return Command::FAILURE;
        }

        $this->info("Syncing product: {$product->name}");
        $this->line("SKU: {$product->sku}");
        $this->line("Price: \${$product->selling_price}");
        $this->line("Quantity: {$product->stock_quantity}");
        $this->line('');

        if ($this->option('dry-run')) {
            $this->warn('Dry run - no sync performed.');

            return Command::SUCCESS;
        }

        if ($this->option('sync')) {
            return $this->runSynchronously($credential, $product);
        }

        // Queue the job
        SyncProductToEbay::dispatch(
            $product,
            $credential,
            $this->option('category')
        );

        $this->info('[OK] Sync job queued successfully.');
        $this->line('Monitor progress in Horizon: /horizon');

        return Command::SUCCESS;
    }

    /**
     * Sync a product by SKU
     */
    protected function syncBySku(IntegrationCredential $credential, string $sku): int
    {
        $product = Product::where('sku', $sku)->first();

        if (! $product) {
            $this->error("Product with SKU '{$sku}' not found.");

            return Command::FAILURE;
        }

        return $this->syncSingleProduct($credential, $product->id);
    }

    /**
     * Sync all pending products
     */
    protected function syncAllPending(IntegrationCredential $credential): int
    {
        $limit = (int) $this->option('limit');

        // Count products to sync
        $query = Product::readyForEbay()
            ->notOnEbay($credential->id);

        if ($credential->organization_id) {
            $query->where('organization_id', $credential->organization_id);
        }

        $total = $query->count();
        $toSync = min($total, $limit);

        $this->info("Found {$total} products ready for eBay.");
        $this->line("Will sync: {$toSync} (limit: {$limit})");
        $this->line('');

        if ($total === 0) {
            $this->warn('No products to sync.');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->displayProductsPreview($query->limit($limit)->get());
            $this->warn('Dry run - no sync performed.');

            return Command::SUCCESS;
        }

        if (! $this->confirm("Proceed with syncing {$toSync} products?")) {
            $this->line('Cancelled.');

            return Command::SUCCESS;
        }

        // Dispatch bulk sync job
        $batchId = BulkSyncToEbay::syncAllPending($credential);

        $this->info('[OK] Bulk sync job queued successfully.');
        $this->line("Batch ID: {$batchId}");
        $this->line('Monitor progress in Horizon: /horizon');

        return Command::SUCCESS;
    }

    /**
     * Interactive sync mode
     */
    protected function interactiveSync(IntegrationCredential $credential): int
    {
        $action = $this->choice('What would you like to do?', [
            'list' => 'List products ready for sync',
            'sync-all' => 'Sync all pending products',
            'sync-one' => 'Sync a specific product',
            'status' => 'View sync status',
            'cancel' => 'Cancel',
        ]);

        return match ($action) {
            'list' => $this->listReadyProducts($credential),
            'sync-all' => $this->syncAllPending($credential),
            'sync-one' => $this->interactiveSyncOne($credential),
            'status' => $this->call('ebay:status', ['--credential' => $credential->id]),
            default => Command::SUCCESS,
        };
    }

    /**
     * List products ready for sync
     */
    protected function listReadyProducts(IntegrationCredential $credential): int
    {
        $products = Product::readyForEbay()
            ->notOnEbay($credential->id)
            ->limit(20)
            ->get();

        if ($products->isEmpty()) {
            $this->info('No products ready for sync.');

            return Command::SUCCESS;
        }

        $this->displayProductsPreview($products);

        return Command::SUCCESS;
    }

    /**
     * Display products preview table
     */
    protected function displayProductsPreview($products): void
    {
        $rows = $products->map(function ($p) {
            return [
                Str::limit($p->id, 8),
                $p->sku,
                Str::limit($p->name, 30),
                '$'.number_format($p->selling_price, 2),
                $p->stock_quantity,
                $p->condition ?? 'N/A',
            ];
        });

        $this->table(
            ['ID', 'SKU', 'Name', 'Price', 'Qty', 'Condition'],
            $rows
        );
    }

    /**
     * Interactive sync one product
     */
    protected function interactiveSyncOne(IntegrationCredential $credential): int
    {
        $productId = $this->ask('Enter product ID or SKU');

        // Try as UUID first
        $product = Product::find($productId);

        // Try as SKU
        if (! $product) {
            $product = Product::where('sku', $productId)->first();
        }

        if (! $product) {
            $this->error('Product not found.');

            return Command::FAILURE;
        }

        return $this->syncSingleProduct($credential, $product->id);
    }

    /**
     * Run sync synchronously (not queued)
     */
    protected function runSynchronously(IntegrationCredential $credential, Product $product): int
    {
        $this->line('Running sync synchronously...');
        $this->line('');

        try {
            $service = new EbayInventoryService($credential);

            $this->line('Creating inventory item...');
            $listing = $service->syncProduct($product, $this->option('category'));

            $this->info('');
            $this->info('[OK] Sync completed successfully!');
            $this->line('');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Listing ID', $listing->id],
                    ['eBay SKU', $listing->ebay_sku],
                    ['eBay Offer ID', $listing->ebay_offer_id],
                    ['eBay Listing ID', $listing->ebay_listing_id],
                    ['Status', $listing->status],
                    ['eBay URL', $listing->ebay_listing_url ?? 'N/A'],
                ]
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Sync failed: '.$e->getMessage());

            if ($this->output->isVerbose()) {
                $this->line('');
                $this->line($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
