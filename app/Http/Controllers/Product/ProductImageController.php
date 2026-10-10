<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Jobs\Inventory\PushListingToMarketplaces;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\User\UserActivityLog;
use App\Services\BackgroundRemovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductImageController extends Controller
{
    public function __construct(
        protected BackgroundRemovalService $bgRemoval,
    ) {}

    /**
     * Re-run background removal on a single product image with chosen settings.
     * Always sources from the pristine original when one is tracked, so re-runs
     * never re-remove from an already-processed (white) image.
     */
    public function reprocess(Request $request, Product $product, ProductImage $image): RedirectResponse
    {
        $this->authorizeImage($product, $image);

        $validated = $request->validate([
            'strength' => 'nullable|string|in:conservative,balanced,aggressive',
            'model' => 'nullable|string|in:isnet-general-use,u2net,u2netp,silueta',
            'padding_pct' => 'nullable|integer|min:0|max:100',
            'center' => 'nullable|boolean',
            'remove_bg' => 'nullable|boolean',
        ]);

        if (! $this->bgRemoval->isAvailable()) {
            return back()->with('error', 'Background removal service is currently unavailable. Try again shortly.');
        }

        $options = array_filter([
            'strength' => $validated['strength'] ?? 'conservative',
            'model' => $validated['model'] ?? null,
            'padding_pct' => $validated['padding_pct'] ?? null,
            'center' => $request->has('center') ? $request->boolean('center') : null,
            'remove_bg' => $request->has('remove_bg') ? $request->boolean('remove_bg') : null,
        ], fn ($v) => $v !== null);

        $result = $this->bgRemoval->removeBackgroundWithOptions(
            $image->sourcePathForProcessing(),
            $options,
        );

        if (! $result['success']) {
            return back()->with('error', 'Image could not be reprocessed. The service may be busy - try again.');
        }

        $image->recordProcessing($result['path'], array_merge($options, [
            'diagnostics' => $result['diagnostics'],
        ]));

        $this->syncProductGalleryFromImages($product);

        UserActivityLog::log(
            'product_image.reprocessed',
            "Reprocessed image for product {$product->name}",
            Auth::user(),
            [
                'product_id' => $product->id,
                'image_id' => $image->id,
                'options' => $options,
                'diagnostics' => $result['diagnostics'],
                'source' => 'web',
            ],
            'info',
            'product',
        );

        return back()->with('success', 'Image reprocessed.');
    }

    /**
     * Re-run background removal on a selected set of this product's images.
     * Lets the user pick which photos to fix (some may already be fine).
     */
    public function reprocessBulk(Request $request, Product $product): RedirectResponse
    {
        if (! Auth::user()?->hasPermission('inventory.update')) {
            abort(403, 'You do not have permission to edit product images.');
        }

        $validated = $request->validate([
            'image_ids' => 'required|array|min:1',
            'image_ids.*' => 'string',
            'strength' => 'nullable|string|in:conservative,balanced,aggressive',
            'model' => 'nullable|string|in:isnet-general-use,u2net,u2netp,silueta',
            'padding_pct' => 'nullable|integer|min:0|max:100',
            'center' => 'nullable|boolean',
            'remove_bg' => 'nullable|boolean',
        ]);

        if (! $this->bgRemoval->isAvailable()) {
            return back()->with('error', 'Background removal service is currently unavailable. Try again shortly.');
        }

        $options = array_filter([
            'strength' => $validated['strength'] ?? 'conservative',
            'model' => $validated['model'] ?? null,
            'padding_pct' => $validated['padding_pct'] ?? null,
            'center' => $request->has('center') ? $request->boolean('center') : null,
            'remove_bg' => $request->has('remove_bg') ? $request->boolean('remove_bg') : null,
        ], fn ($v) => $v !== null);

        $images = $product->images()->whereIn('id', $validated['image_ids'])->get();

        $succeeded = 0;
        $failed = 0;
        foreach ($images as $image) {
            $result = $this->bgRemoval->removeBackgroundWithOptions($image->sourcePathForProcessing(), $options);
            if ($result['success']) {
                $image->recordProcessing($result['path'], array_merge($options, ['diagnostics' => $result['diagnostics']]));
                $succeeded++;
            } else {
                $failed++;
            }
        }

        if ($succeeded > 0) {
            $this->syncProductGalleryFromImages($product);
        }

        UserActivityLog::log(
            'product_image.bulk_reprocessed',
            "Reprocessed {$succeeded} image(s) for product {$product->name}",
            Auth::user(),
            [
                'product_id' => $product->id,
                'image_ids' => $images->pluck('id')->all(),
                'succeeded' => $succeeded,
                'failed' => $failed,
                'options' => $options,
                'source' => 'web',
            ],
            'info',
            'product',
        );

        $msg = "Reprocessed {$succeeded} image".($succeeded === 1 ? '' : 's').'.';
        if ($failed > 0) {
            $msg .= " {$failed} could not be processed.";
        }

        return back()->with($succeeded > 0 ? 'success' : 'error', $msg);
    }

    /**
     * Revert a processed image back to its pristine original.
     */
    public function revert(Product $product, ProductImage $image): RedirectResponse
    {
        $this->authorizeImage($product, $image);

        if (! $image->revertToOriginal()) {
            return back()->with('error', 'No original is available to revert to for this image.');
        }

        $this->syncProductGalleryFromImages($product);

        UserActivityLog::log(
            'product_image.reverted',
            "Reverted image to original for product {$product->name}",
            Auth::user(),
            [
                'product_id' => $product->id,
                'image_id' => $image->id,
                'source' => 'web',
            ],
            'info',
            'product',
        );

        return back()->with('success', 'Image reverted to original.');
    }

    /**
     * Replace an image by uploading a new file. The upload becomes the new
     * pristine original (background-removal cleared until reprocessed).
     */
    public function replace(Request $request, Product $product, ProductImage $image): RedirectResponse
    {
        $this->authorizeImage($product, $image);

        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp,bmp|max:51200',
        ]);

        $path = $request->file('image')->store('queue-photos', 'public');

        $image->url = $path;
        $image->metadata = [
            'original_url' => $path,
            'bg_removed' => false,
        ];
        $image->save();

        $this->syncProductGalleryFromImages($product);

        UserActivityLog::log(
            'product_image.replaced',
            "Replaced image for product {$product->name}",
            Auth::user(),
            [
                'product_id' => $product->id,
                'image_id' => $image->id,
                'source' => 'web',
            ],
            'info',
            'product',
        );

        return back()->with('success', 'Image replaced.');
    }

    /**
     * Permission + ownership gate shared by all image edit actions.
     */
    protected function authorizeImage(Product $product, ProductImage $image): void
    {
        if (! Auth::user()?->hasPermission('inventory.update')) {
            abort(403, 'You do not have permission to edit product images.');
        }

        if ($image->product_id !== $product->id) {
            abort(404);
        }
    }

    /**
     * Mirror the product_images table into Product.gallery_images + featured_image
     * and queue a marketplace listing push. eBay reads gallery_images directly;
     * the change also busts the per-listing product hash so Shopify/Amazon
     * re-push. We saveQuietly (no observer) and dispatch the push explicitly so
     * the push always runs even when gallery_images happens to be unchanged
     * (the job still hash-gates per listing, so it no-ops when truly unchanged).
     * No-ops for products without listings.
     */
    protected function syncProductGalleryFromImages(Product $product): void
    {
        $images = $product->images()->ordered()->get();
        $urls = $images->pluck('url')->filter()->values()->all();
        $primary = $images->firstWhere('is_primary', true) ?? $images->first();

        $product->gallery_images = $urls;
        $product->featured_image = $primary?->url;
        $product->saveQuietly();

        PushListingToMarketplaces::dispatch($product->id);
    }

    /**
     * Manually push the product's current images to its marketplaces. Handles
     * images edited before auto-push existed (gallery_images drifted from the
     * product_images table) and serves as an on-demand re-push.
     */
    public function syncMarketplaces(Product $product): RedirectResponse
    {
        if (! Auth::user()?->hasPermission('inventory.update')) {
            abort(403, 'You do not have permission to edit product images.');
        }

        $this->syncProductGalleryFromImages($product);

        UserActivityLog::log(
            'product_image.marketplace_sync_requested',
            "Queued image sync to marketplaces for product {$product->name}",
            Auth::user(),
            ['product_id' => $product->id, 'source' => 'web'],
            'info',
            'product',
        );

        return back()->with('success', 'Image sync to marketplaces queued.');
    }
}
