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
use Illuminate\Support\Str;

class RetryFailedEbaySync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted
     */
    public int $tries = 1;

    /**
     * Maximum consecutive errors before giving up
     */
    protected int $maxConsecutiveErrors = 5;

    /**
     * Create a new job instance
     */
    public function __construct(
        public ?IntegrationCredential $credential = null,
        public ?int $teamId = null,
        public int $limit = 50
    ) {
        $this->onQueue('ebay-retry');
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        Log::info('RetryFailedEbaySync: Starting retry process', [
            'credential_id' => $this->credential?->id,
            'team_id' => $this->teamId,
            'limit' => $this->limit,
        ]);

        $listings = $this->getRetryableListings();

        if ($listings->isEmpty()) {
            Log::info('RetryFailedEbaySync: No retryable listings found');

            return;
        }

        Log::info('RetryFailedEbaySync: Found listings to retry', [
            'count' => $listings->count(),
        ]);

        $batchId = (string) Str::uuid();
        $retried = 0;
        $skipped = 0;

        foreach ($listings as $index => $listing) {
            // Check if we should retry this listing
            if (! $this->shouldRetry($listing)) {
                $skipped++;

                continue;
            }

            // Determine what operation to retry
            $job = $this->createRetryJob($listing, $batchId);

            if ($job) {
                // Dispatch with delay to avoid rate limiting
                dispatch($job->delay(now()->addSeconds($index * 3)));
                $retried++;
            } else {
                $skipped++;
            }
        }

        Log::info('RetryFailedEbaySync: Retry jobs dispatched', [
            'batch_id' => $batchId,
            'retried' => $retried,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Get listings that can be retried
     */
    protected function getRetryableListings()
    {
        $query = EbayListing::query()
            ->where('status', EbayListing::STATUS_ERROR)
            ->where('consecutive_errors', '<', $this->maxConsecutiveErrors)
            ->whereNotNull('last_error_at')
            ->where('last_error_at', '<', now()->subMinutes(5)) // Wait at least 5 minutes
            ->orderBy('last_error_at', 'asc'); // Oldest errors first

        if ($this->credential) {
            $query->where('integration_credential_id', $this->credential->id);
        }

        if ($this->teamId) {
            $query->where('team_id', $this->teamId);
        }

        return $query->limit($this->limit)->get();
    }

    /**
     * Determine if a listing should be retried
     */
    protected function shouldRetry(EbayListing $listing): bool
    {
        // Skip if too many consecutive errors
        if ($listing->consecutive_errors >= $this->maxConsecutiveErrors) {
            Log::info('RetryFailedEbaySync: Skipping listing - too many errors', [
                'listing_id' => $listing->id,
                'consecutive_errors' => $listing->consecutive_errors,
            ]);

            return false;
        }

        // Skip if credential is not active or auto_sync is disabled
        $credential = $listing->integrationCredential;
        if (! $credential || ! $credential->is_active) {
            return false;
        }

        if (! $credential->auto_sync_enabled) {
            return false;
        }

        // Skip if product is no longer valid
        $product = $listing->product;
        if (! $product || $product->status !== 'active') {
            return false;
        }

        // Check the last error code - some errors are not retryable
        $nonRetryableErrors = [
            'INVALID_CATEGORY',
            'CATEGORY_NOT_FOUND',
            'DUPLICATE_LISTING',
            'ITEM_NOT_AVAILABLE',
            'SELLER_NOT_REGISTERED',
        ];

        if (in_array($listing->last_error_code, $nonRetryableErrors)) {
            Log::info('RetryFailedEbaySync: Skipping listing - non-retryable error', [
                'listing_id' => $listing->id,
                'error_code' => $listing->last_error_code,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Create the appropriate retry job based on listing status
     */
    protected function createRetryJob(EbayListing $listing, string $batchId): ?SyncProductToEbay
    {
        $credential = $listing->integrationCredential;
        $product = $listing->product;

        if (! $credential || ! $product) {
            return null;
        }

        // Reset the listing status to pending for full retry
        $listing->update([
            'status' => EbayListing::STATUS_PENDING,
        ]);

        return new SyncProductToEbay($product);
    }

    /**
     * Get the tags for the job
     */
    public function tags(): array
    {
        $tags = ['ebay-retry'];

        if ($this->credential) {
            $tags[] = 'credential:'.$this->credential->id;
        }

        if ($this->teamId) {
            $tags[] = 'team:'.$this->teamId;
        }

        return $tags;
    }

    /**
     * Static helper to retry all failed for a credential
     */
    public static function retryForCredential(IntegrationCredential $credential, int $limit = 50): void
    {
        dispatch(new self($credential, null, $limit));
    }

    /**
     * Static helper to retry all failed for a team
     */
    public static function retryForTeam(int $teamId, int $limit = 50): void
    {
        dispatch(new self(null, $teamId, $limit));
    }

    /**
     * Static helper to mark listing as permanently failed
     */
    public static function markAsPermanentlyFailed(EbayListing $listing, string $reason): void
    {
        $listing->update([
            'status' => EbayListing::STATUS_ERROR,
            'consecutive_errors' => 999, // Prevent future retries
            'last_error_message' => "Permanently failed: {$reason}",
            'last_error_at' => now(),
        ]);

        Log::warning('RetryFailedEbaySync: Listing marked as permanently failed', [
            'listing_id' => $listing->id,
            'reason' => $reason,
        ]);
    }
}
