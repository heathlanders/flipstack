<?php

namespace App\Console\Commands\Ebay;

use App\Jobs\Ebay\BulkSyncToEbay;
use App\Models\Catalog\Product;
use App\Models\Organization\Team;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Throwable;

class SyncProductsToEbayCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ebay:sync-products 
                            {--team= : The team ID to sync products for}
                            {--all : Sync all teams}
                            {--force : Force update even if no changes detected}
                            {--chunk=50 : Number of products per batch job}';

    /**
     * The console command description.
     */
    protected $description = 'Bulk sync products to eBay listings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $teamId = $this->option('team');
        $syncAll = $this->option('all');
        $forceUpdate = $this->option('force');
        $chunkSize = (int) $this->option('chunk');

        if (! $teamId && ! $syncAll) {
            $this->error('Please specify --team=ID or --all');

            return Command::FAILURE;
        }

        $teams = $syncAll
            ? Team::whereHas('ebayIntegration', fn ($q) => $q->where('is_active', true))->get()
            : Team::where('id', $teamId)->get();

        if ($teams->isEmpty()) {
            $this->warn('No teams found with active eBay integration.');

            return Command::SUCCESS;
        }

        $totalProducts = 0;
        $totalBatches = 0;

        foreach ($teams as $team) {
            $this->info("Processing team: {$team->name} (ID: {$team->id})");

            $products = Product::where('team_id', $team->id)
                ->where('ebay_enabled', true)
                ->pluck('id')
                ->toArray();

            if (empty($products)) {
                $this->line('  No eBay-enabled products found.');

                continue;
            }

            $chunks = array_chunk($products, $chunkSize);
            $jobs = [];

            foreach ($chunks as $productIds) {
                $jobs[] = new BulkSyncToEbay($productIds, $team->id, $forceUpdate);
            }

            $batch = Bus::batch($jobs)
                ->name("eBay Sync - Team {$team->id}")
                ->allowFailures()
                ->onQueue('ebay')
                ->then(function (Batch $batch) use ($team) {
                    // All jobs completed successfully
                    \Log::info("eBay bulk sync completed for team {$team->id}", [
                        'batch_id' => $batch->id,
                        'total_jobs' => $batch->totalJobs,
                    ]);
                })
                ->catch(function (Batch $batch, Throwable $e) use ($team) {
                    // First batch job failure
                    \Log::error("eBay bulk sync had failures for team {$team->id}", [
                        'batch_id' => $batch->id,
                        'error' => $e->getMessage(),
                    ]);
                })
                ->finally(function (Batch $batch) use ($team) {
                    // Batch completed (with or without failures)
                    \Log::info("eBay bulk sync finished for team {$team->id}", [
                        'batch_id' => $batch->id,
                        'failed_jobs' => $batch->failedJobs,
                        'pending_jobs' => $batch->pendingJobs,
                    ]);
                })
                ->dispatch();

            $totalProducts += count($products);
            $totalBatches += count($chunks);

            $this->line("  Queued {$batch->totalJobs} jobs for ".count($products).' products');
            $this->line("  Batch ID: {$batch->id}");
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Teams processed: {$teams->count()}");
        $this->line("  Total products: {$totalProducts}");
        $this->line("  Total batch jobs: {$totalBatches}");

        return Command::SUCCESS;
    }
}
