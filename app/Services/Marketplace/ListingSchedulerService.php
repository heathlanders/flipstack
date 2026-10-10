<?php

namespace App\Services\Marketplace;

use App\Jobs\CreateEbayListingJob;
use App\Jobs\Shopify\SyncProductToShopify;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Marketplace\ScheduledListing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ListingSchedulerService
{
    /**
     * Create a new scheduled listing.
     */
    public function schedule(array $data, string $orgId): ScheduledListing
    {
        return ScheduledListing::create(array_merge($data, [
            'organization_id' => $orgId,
            'status' => ScheduledListing::STATUS_PENDING,
        ]));
    }

    /**
     * Cancel a scheduled listing.
     */
    public function cancel(ScheduledListing $listing): void
    {
        $listing->update([
            'status' => ScheduledListing::STATUS_CANCELLED,
        ]);
    }

    /**
     * Process all due listings and dispatch the appropriate jobs.
     */
    public function processDueListings(): int
    {
        $dueListings = ScheduledListing::due()->get();
        $count = 0;

        foreach ($dueListings as $listing) {
            $listing->update(['status' => ScheduledListing::STATUS_PROCESSING]);

            try {
                if ($listing->marketplace === 'ebay') {
                    CreateEbayListingJob::dispatch(
                        $listing->product_id,
                        $listing->integration_credential_id,
                        $listing->created_by
                    );

                    $listing->update([
                        'status' => ScheduledListing::STATUS_PUBLISHED,
                        'published_at' => now(),
                    ]);
                } elseif ($listing->marketplace === 'shopify') {
                    $product = Product::findOrFail($listing->product_id);
                    $credential = IntegrationCredential::findOrFail($listing->integration_credential_id);

                    SyncProductToShopify::dispatch($product, $credential);

                    $listing->update([
                        'status' => ScheduledListing::STATUS_PUBLISHED,
                        'published_at' => now(),
                    ]);
                } else {
                    Log::info('Scheduled listing dispatch not yet implemented for marketplace.', [
                        'scheduled_listing_id' => $listing->id,
                        'marketplace' => $listing->marketplace,
                    ]);

                    $listing->update([
                        'status' => ScheduledListing::STATUS_FAILED,
                        'error_message' => "Marketplace '{$listing->marketplace}' is not yet supported for scheduled listings.",
                    ]);
                }

                $count++;
            } catch (\Throwable $e) {
                Log::error('Failed to process scheduled listing.', [
                    'scheduled_listing_id' => $listing->id,
                    'error' => $e->getMessage(),
                ]);

                $listing->update([
                    'status' => ScheduledListing::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);

                $count++;
            }
        }

        return $count;
    }

    /**
     * Get upcoming scheduled listings for an organization.
     */
    public function getUpcoming(string $orgId, int $days = 7): Collection
    {
        return ScheduledListing::where('organization_id', $orgId)
            ->where('status', ScheduledListing::STATUS_PENDING)
            ->where('scheduled_at', '<=', now()->addDays($days))
            ->orderBy('scheduled_at')
            ->get();
    }
}
