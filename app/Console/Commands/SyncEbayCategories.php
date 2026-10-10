<?php

namespace App\Console\Commands;

use App\Services\EbayCategoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncEbayCategories extends Command
{
    protected $signature = 'ebay:sync-categories 
                           {--force : Force sync even if recently synced}
                           {--environment=production : eBay environment (sandbox|production)}';

    protected $description = 'Sync all eBay categories from API';

    public function handle(EbayCategoryService $categoryService): int
    {
        $environment = $this->option('environment');
        $force = $this->option('force');

        $this->info("Starting eBay category sync for {$environment} environment...");

        // Initialize service with specified environment
        $categoryService = new EbayCategoryService(app(\App\Services\EbayRateLimiter::class), $environment);

        if (! $categoryService->isAvailable()) {
            $this->error("eBay credentials not available for {$environment} environment");

            return 1;
        }

        try {
            $this->info('Connecting to eBay API...');

            // Show progress bar
            $progressBar = $this->output->createProgressBar(100);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %memory:6s%');

            $stats = $categoryService->syncAllCategories();

            $progressBar->finish();
            $this->newLine(2);

            $this->info('eBay category sync completed successfully!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Categories Synced', number_format($stats['total_synced'])],
                    ['New Categories Added', number_format($stats['categories_added'])],
                    ['Categories Updated', number_format($stats['categories_updated'])],
                    ['Leaf Categories (can list)', number_format($stats['leaf_categories'])],
                    ['Errors', $stats['errors']],
                ]
            );

            if ($stats['errors'] > 0) {
                $this->warn("{$stats['errors']} errors occurred during sync. Check logs for details.");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Category sync failed: '.$e->getMessage());
            Log::error('eBay category sync command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }
}
