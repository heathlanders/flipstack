<?php

namespace App\Jobs\Ebay;

use App\Models\Ebay\EbayListing;
use App\Models\Integration\IntegrationCredential;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEbayScheduledTasks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted
     */
    public int $tries = 1;

    /**
     * Task type to process
     */
    public string $task;

    /**
     * Task types
     */
    const TASK_UPDATE_STALE = 'update_stale';

    const TASK_RETRY_FAILED = 'retry_failed';

    const TASK_SYNC_QUANTITIES = 'sync_quantities';

    const TASK_CLEANUP_LOGS = 'cleanup_logs';

    /**
     * Create a new job instance
     */
    public function __construct(string $task = self::TASK_UPDATE_STALE)
    {
        $this->task = $task;
        $this->onQueue('ebay-scheduled');
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        Log::info('ProcessEbayScheduledTasks: Starting', ['task' => $this->task]);

        match ($this->task) {
            self::TASK_UPDATE_STALE => $this->updateStaleListings(),
            self::TASK_RETRY_FAILED => $this->retryFailedListings(),
            self::TASK_SYNC_QUANTITIES => $this->syncQuantities(),
            self::TASK_CLEANUP_LOGS => $this->cleanupOldLogs(),
            default => Log::warning('ProcessEbayScheduledTasks: Unknown task', ['task' => $this->task]),
        };

        Log::info('ProcessEbayScheduledTasks: Completed', ['task' => $this->task]);
    }

    /**
     * Update listings that haven't been synced recently
     */
    protected function updateStaleListings(): void
    {
        $credentials = IntegrationCredential::where('platform', 'ebay')
            ->where('is_active', true)
            ->where('auto_sync_enabled', true)
            ->get();

        $totalUpdated = 0;

        foreach ($credentials as $credential) {
            $count = UpdateEbayInventory::updateAllStale($credential, 24);
            $totalUpdated += $count;

            Log::info('ProcessEbayScheduledTasks: Stale listings queued', [
                'credential_id' => $credential->id,
                'count' => $count,
            ]);
        }

        Log::info('ProcessEbayScheduledTasks: Total stale listings queued', [
            'total' => $totalUpdated,
        ]);
    }

    /**
     * Retry failed listings
     */
    protected function retryFailedListings(): void
    {
        $credentials = IntegrationCredential::where('platform', 'ebay')
            ->where('is_active', true)
            ->get();

        foreach ($credentials as $credential) {
            // Check if credential is properly configured
            if (! $credential->isEbayReady()) {
                continue;
            }

            $failedCount = EbayListing::forCredential($credential->id)
                ->where('status', EbayListing::STATUS_ERROR)
                ->where('consecutive_errors', '<', 5)
                ->count();

            if ($failedCount > 0) {
                RetryFailedEbaySync::retryForCredential($credential, min($failedCount, 25));

                Log::info('ProcessEbayScheduledTasks: Retry job dispatched', [
                    'credential_id' => $credential->id,
                    'failed_count' => $failedCount,
                ]);
            }
        }
    }

    /**
     * Sync quantities from products to eBay
     */
    protected function syncQuantities(): void
    {
        $credentials = IntegrationCredential::where('platform', 'ebay')
            ->where('is_active', true)
            ->where('auto_sync_enabled', true)
            ->get();

        foreach ($credentials as $credential) {
            // Find listings where product quantity doesn't match
            $listings = EbayListing::forCredential($credential->id)
                ->published()
                ->with('product')
                ->get();

            $needsUpdate = 0;

            foreach ($listings as $index => $listing) {
                if (! $listing->product) {
                    continue;
                }

                $productQty = $listing->product->available_quantity ?? $listing->product->stock_quantity;

                // Only update if quantity actually changed
                if ($listing->available_quantity !== $productQty) {
                    dispatch(
                        (new UpdateEbayInventory($listing, 'quantity'))
                            ->delay(now()->addSeconds($needsUpdate * 2))
                    );
                    $needsUpdate++;
                }
            }

            if ($needsUpdate > 0) {
                Log::info('ProcessEbayScheduledTasks: Quantity sync jobs dispatched', [
                    'credential_id' => $credential->id,
                    'count' => $needsUpdate,
                ]);
            }
        }
    }

    /**
     * Clean up old sync logs
     */
    protected function cleanupOldLogs(): void
    {
        // Keep logs for 30 days
        $cutoffDate = now()->subDays(30);

        $deleted = \App\Models\Ebay\EbaySyncLog::where('created_at', '<', $cutoffDate)->delete();

        Log::info('ProcessEbayScheduledTasks: Old logs cleaned up', [
            'deleted_count' => $deleted,
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);
    }

    /**
     * Get the tags for the job
     */
    public function tags(): array
    {
        return [
            'ebay-scheduled',
            'task:'.$this->task,
        ];
    }
}
