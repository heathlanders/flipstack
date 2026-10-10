<?php

namespace App\Console\Commands\Ebay;

use App\Jobs\Ebay\UpdateEbayListing;
use App\Models\Catalog\Product;
use App\Models\Ebay\EbayListing;
use App\Models\Ebay\EbaySyncLog;
use App\Models\User\UserActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ResyncEbayListing extends Command
{
    protected $signature = 'ebay:resync {product : Product UUID or SKU}';

    protected $description = 'Mirror current product images into gallery_images and re-push the eBay listing(s) (images + item specifics) via the working inventory-update path. Also unsticks listings left non-published by a failed sync.';

    public function handle(): int
    {
        $key = $this->argument('product');

        // `id` is a UUID column; comparing it to a non-UUID string errors in PG.
        $product = Str::isUuid($key)
            ? Product::where('id', $key)->first()
            : Product::where('sku', $key)->first();

        if (! $product) {
            $this->error("Product not found: {$key}");

            return self::FAILURE;
        }

        // Mirror the product_images table into gallery_images + featured_image so
        // the eBay payload reflects the current (e.g. reprocessed) images.
        $images = $product->images()->orderBy('sort_order')->get();
        $product->gallery_images = $images->pluck('url')->filter()->values()->all();
        $primary = $images->firstWhere('is_primary', true) ?: $images->first();
        $product->featured_image = $primary?->url;
        $product->saveQuietly();

        $this->info('Mirrored '.count($product->gallery_images)." image(s) into gallery_images for {$product->name}.");

        $listings = EbayListing::where('product_id', $product->id)->get();

        if ($listings->isEmpty()) {
            $this->warn('No eBay listings found for this product. Nothing to push.');

            return self::SUCCESS;
        }

        foreach ($listings as $listing) {
            // Unstick a listing left in a non-publishable state by a failed sync
            // (UpdateEbayListing skips anything that is not published).
            if (! empty($listing->ebay_listing_id)
                && ! $listing->isPublished()
                && $listing->status !== EbayListing::STATUS_OUT_OF_STOCK) {
                $listing->update([
                    'status' => EbayListing::STATUS_PUBLISHED,
                    'consecutive_errors' => 0,
                ]);
                $this->line("  Listing {$listing->id}: status reset to published.");
            }

            $before = (int) (EbaySyncLog::max('id') ?? 0);

            UpdateEbayListing::dispatchSync($listing);

            $logs = EbaySyncLog::where('id', '>', $before)->orderBy('id')->get();

            if ($logs->isEmpty()) {
                $this->warn("  Listing {$listing->id}: no sync log produced (skipped? not published / no credential).");

                continue;
            }

            foreach ($logs as $log) {
                $line = "  Listing {$listing->id}: {$log->operation} -> {$log->status}";
                if ($log->error_message) {
                    $line .= " ({$log->error_message})";
                }
                $log->status === EbaySyncLog::STATUS_SUCCESS ? $this->info($line) : $this->error($line);
            }
        }

        // Audit (best-effort; CLI has no actor, so set org explicitly and never
        // let an audit failure fail the command).
        try {
            UserActivityLog::create([
                'organization_id' => $product->organization_id,
                'action' => 'listing.ebay.resynced',
                'description' => "Manually re-synced eBay listing(s) for product {$product->name}",
                'metadata' => ['product_id' => $product->id, 'listing_count' => $listings->count(), 'source' => 'cli'],
                'severity' => 'info',
                'category' => 'integration',
            ]);
        } catch (\Throwable $e) {
            $this->warn('Audit log skipped: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
