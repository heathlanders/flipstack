<?php

namespace App\Services\Inventory;

use App\Models\Amazon\AmazonListing;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductBundle;
use App\Models\Catalog\ProductBundleItem;
use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\OrderItem;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ProductDeletionService
{
    /**
     * Get the impact summary of archiving/deleting a product.
     */
    public function getDeletionImpact(Product $product): array
    {
        $activeEbayListings = $product->ebayListings()
            ->whereNull('deleted_at')
            ->whereIn('status', ['published', 'active'])
            ->count();

        $activeShopifyListings = $product->shopifyListings()
            ->whereNull('deleted_at')
            ->whereIn('status', ['active', 'published'])
            ->count();

        $activeAmazonListings = 0;
        if (Schema::hasTable('amazon_listings')) {
            $activeAmazonListings = AmazonListing::where('product_id', $product->id)
                ->whereNull('deleted_at')
                ->whereIn('status', ['active', 'published'])
                ->count();
        }

        $totalActiveListings = $activeEbayListings + $activeShopifyListings + $activeAmazonListings;

        $pendingOrderItems = OrderItem::where('product_id', $product->id)
            ->whereHas('order', function ($query) {
                $query->whereNotIn('status', [
                    Order::STATUS_SHIPPED,
                    Order::STATUS_IN_TRANSIT,
                    Order::STATUS_DELIVERED,
                    Order::STATUS_CANCELLED,
                    Order::STATUS_RETURNED,
                ]);
            })
            ->count();

        $bundleMemberships = ProductBundle::where('product_id', $product->id)->count() + ProductBundleItem::where('component_product_id', $product->id)->count();

        $activeIntakeItems = ProductIntakeQueue::where('product_id', $product->id)
            ->whereNotIn('processing_status', ['completed', 'archived', 'listed_on_ebay'])
            ->count();

        $imageCount = $product->images()->count();
        $identifierCount = $product->identifiers()->count();
        $inventoryMovementCount = $product->inventoryMovements()->count();

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'active_ebay_listings' => $activeEbayListings,
            'active_shopify_listings' => $activeShopifyListings,
            'active_amazon_listings' => $activeAmazonListings,
            'total_active_listings' => $totalActiveListings,
            'pending_order_items' => $pendingOrderItems,
            'bundle_memberships' => $bundleMemberships,
            'active_intake_items' => $activeIntakeItems,
            'image_count' => $imageCount,
            'identifier_count' => $identifierCount,
            'inventory_movement_count' => $inventoryMovementCount,
            'has_active_listings' => $totalActiveListings > 0,
            'has_pending_orders' => $pendingOrderItems > 0,
            'is_bundle_member' => $bundleMemberships > 0,
            'has_active_intake' => $activeIntakeItems > 0,
        ];
    }

    /**
     * Archive a product (soft delete).
     *
     * Sets status to 'archived' first to trigger marketplace delisting via observer,
     * then soft-deletes the product.
     */
    public function archive(Product $product, User $user): bool
    {
        return DB::transaction(function () use ($product, $user) {
            $impact = $this->getDeletionImpact($product);

            Log::info('ProductDeletion: archiving product', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'user_id' => $user->id,
                'impact' => $impact,
            ]);

            // Set status to 'archived' first - this triggers the observer
            // which dispatches EndMarketplaceListings to end all active listings
            $product->status = 'archived';
            $product->save();

            // Now soft-delete the product
            return $product->delete();
        });
    }

    /**
     * Restore an archived (soft-deleted) product.
     *
     * Restores the product with 'draft' status. User must manually
     * re-activate and re-create marketplace listings.
     */
    public function restore(Product $product, User $user): bool
    {
        return DB::transaction(function () use ($product, $user) {
            Log::info('ProductDeletion: restoring product', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'user_id' => $user->id,
            ]);

            $product->restore();

            // Set status to draft - user must manually re-activate
            $product->status = 'draft';
            $product->save();

            return true;
        });
    }

    /**
     * Permanently delete an archived product.
     *
     * Only allowed for already soft-deleted products. This permanently
     * removes the product and all related data via database cascades.
     */
    public function forceDelete(Product $product, User $user): bool
    {
        if (! $product->trashed()) {
            throw new \InvalidArgumentException('Only archived products can be permanently deleted. Archive the product first.');
        }

        return DB::transaction(function () use ($product, $user) {
            Log::info('ProductDeletion: permanently deleting product', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'user_id' => $user->id,
            ]);

            // Delete image files from storage before cascade removes DB records
            foreach ($product->images as $image) {
                if ($image->url && ! str_starts_with($image->url, 'http')) {
                    Storage::disk('public')->delete($image->url);
                }
            }

            return $product->forceDelete();
        });
    }

    /**
     * Bulk archive products.
     */
    public function bulkArchive(array $productIds, User $user): array
    {
        $archived = 0;
        $failed = [];

        foreach ($productIds as $productId) {
            try {
                $product = Product::findOrFail($productId);
                $this->archive($product, $user);
                $archived++;
            } catch (\Exception $e) {
                $failed[] = $productId;
                Log::error("ProductDeletion: failed to archive product {$productId}: ".$e->getMessage());
            }
        }

        return [
            'archived' => $archived,
            'failed' => $failed,
            'message' => "{$archived} product(s) archived successfully."
                .($failed ? ' '.count($failed).' failed.' : ''),
        ];
    }

    /**
     * Bulk restore archived products.
     */
    public function bulkRestore(array $productIds, User $user): array
    {
        $restored = 0;
        $failed = [];

        $products = Product::onlyTrashed()->whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            try {
                $this->restore($product, $user);
                $restored++;
            } catch (\Exception $e) {
                $failed[] = $product->id;
                Log::error("ProductDeletion: failed to restore product {$product->id}: ".$e->getMessage());
            }
        }

        return [
            'restored' => $restored,
            'failed' => $failed,
            'message' => "{$restored} product(s) restored successfully."
                .($failed ? ' '.count($failed).' failed.' : ''),
        ];
    }

    /**
     * Bulk permanently delete archived products.
     */
    public function bulkForceDelete(array $productIds, User $user): array
    {
        $deleted = 0;
        $failed = [];

        $products = Product::onlyTrashed()->whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            try {
                $this->forceDelete($product, $user);
                $deleted++;
            } catch (\Exception $e) {
                $failed[] = $product->id;
                Log::error("ProductDeletion: failed to force delete product {$product->id}: ".$e->getMessage());
            }
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
            'message' => "{$deleted} product(s) permanently deleted."
                .($failed ? ' '.count($failed).' failed.' : ''),
        ];
    }
}
