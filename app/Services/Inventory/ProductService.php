<?php

namespace App\Services\Inventory;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Create a new product.
     */
    public function create(array $data, User $user): Product
    {
        return DB::transaction(function () use ($data, $user) {
            $organizationId = $user->organization_id ?? $user->current_organization_id ?? 1;

            // Prepare product data
            $productData = array_merge($data, [
                'organization_id' => $organizationId,
                'created_by' => $user->id,
                'slug' => $data['slug'] ?? Str::slug($data['name']),
            ]);

            // Remove nested data
            $images = $productData['images'] ?? [];
            unset($productData['images'], $productData['images_to_delete']);

            // Create product
            $product = Product::create($productData);

            // Create images
            if (! empty($images)) {
                $this->syncImages($product, $images);
            }

            // Log activity
            $this->logActivity($product, 'created', $user, ['name' => $product->name]);

            return $product->fresh(['category', 'brand', 'images']);
        });
    }

    /**
     * Update a product.
     */
    public function update(Product $product, array $data, User $user): Product
    {
        return DB::transaction(function () use ($product, $data, $user) {
            $originalData = $product->toArray();

            // Handle images separately
            $images = $data['images'] ?? null;
            $imagesToDelete = $data['images_to_delete'] ?? [];
            unset($data['images'], $data['images_to_delete']);

            // Update product
            $product->update($data);

            // Handle image deletions
            if (! empty($imagesToDelete)) {
                ProductImage::whereIn('id', $imagesToDelete)
                    ->where('product_id', $product->id)
                    ->delete();
            }

            // Sync images if provided
            if ($images !== null) {
                $this->syncImages($product, $images);
            }

            // Log activity with changes
            $changes = $this->getChanges($originalData, $product->toArray());
            if (! empty($changes)) {
                $this->logActivity($product, 'updated', $user, ['changes' => $changes]);
            }

            return $product->fresh(['category', 'brand', 'images']);
        });
    }

    /**
     * Delete (archive) a product. Delegates to ProductDeletionService.
     */
    public function delete(Product $product, User $user): bool
    {
        return app(ProductDeletionService::class)->archive($product, $user);
    }

    /**
     * Duplicate a product.
     */
    public function duplicate(Product $product, User $user): Product
    {
        return DB::transaction(function () use ($product, $user) {
            $product->load('images');

            // Prepare duplicate data
            $data = $product->replicate()->toArray();
            $data['name'] = $product->name.' (Copy)';
            $data['slug'] = Str::slug($data['name']).'-'.Str::random(5);
            $data['sku'] = $product->sku.'-COPY-'.strtoupper(Str::random(4));
            $data['status'] = 'draft';
            $data['total_sales'] = 0;
            $data['view_count'] = 0;
            $data['review_count'] = 0;
            $data['average_rating'] = null;

            // Create duplicate
            $newProduct = $this->create($data, $user);

            // Duplicate images
            foreach ($product->images as $image) {
                ProductImage::create([
                    'product_id' => $newProduct->id,
                    'url' => $image->url,
                    'alt_text' => $image->alt_text,
                    'sort_order' => $image->sort_order,
                ]);
            }

            return $newProduct->fresh(['category', 'brand', 'images']);
        });
    }

    /**
     * Dispatch a bulk action to the appropriate method.
     */
    public function bulkAction(string $action, array $productIds, User $user): array
    {
        $deletionService = app(ProductDeletionService::class);

        return match ($action) {
            'delete' => $deletionService->bulkArchive($productIds, $user),
            'restore' => $deletionService->bulkRestore($productIds, $user),
            'force_delete' => $deletionService->bulkForceDelete($productIds, $user),
            'activate' => $this->bulkUpdateStatus($productIds, 'active', $user),
            'deactivate' => $this->bulkUpdateStatus($productIds, 'inactive', $user),
            'archive' => $deletionService->bulkArchive($productIds, $user),
            default => ['message' => "Unknown action: {$action}"],
        };
    }

    /**
     * Bulk delete (archive) products. Delegates to ProductDeletionService.
     */
    public function bulkDelete(array $productIds, User $user): array
    {
        return app(ProductDeletionService::class)->bulkArchive($productIds, $user);
    }

    /**
     * Bulk update status.
     */
    public function bulkUpdateStatus(array $productIds, string $status, User $user): array
    {
        $updated = Product::whereIn('id', $productIds)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);

        // Log activity for each product
        $products = Product::whereIn('id', $productIds)->get();
        foreach ($products as $product) {
            $this->logActivity($product, 'status_changed', $user, ['status' => $status]);
        }

        return [
            'updated' => $updated,
        ];
    }

    /**
     * Bulk update category.
     */
    public function bulkUpdateCategory(array $productIds, int $categoryId, User $user): array
    {
        $updated = Product::whereIn('id', $productIds)->update([
            'category_id' => $categoryId,
            'updated_at' => now(),
        ]);

        return [
            'updated' => $updated,
        ];
    }

    /**
     * Bulk update price.
     */
    public function bulkUpdatePrice(array $productIds, array $data, User $user): array
    {
        $priceType = $data['price_type']; // fixed or percentage
        $priceField = $data['price_field']; // selling_price or cost_price
        $value = $data['value'];
        $operation = $data['operation'] ?? null; // increase or decrease (for percentage)

        $updated = 0;
        $products = Product::whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            $currentPrice = $product->{$priceField} ?? 0;

            if ($priceType === 'fixed') {
                $newPrice = $value;
            } else {
                // Percentage
                $adjustmentAmount = ($currentPrice * $value) / 100;
                $newPrice = $operation === 'increase'
                    ? $currentPrice + $adjustmentAmount
                    : $currentPrice - $adjustmentAmount;
            }

            $product->{$priceField} = max(0, round($newPrice, 2));
            $product->save();
            $updated++;

            $this->logActivity($product, 'price_updated', $user, [
                'field' => $priceField,
                'old_value' => $currentPrice,
                'new_value' => $product->{$priceField},
            ]);
        }

        return [
            'updated' => $updated,
        ];
    }

    /**
     * Bulk adjust stock.
     */
    public function bulkAdjustStock(array $productIds, array $data, User $user): array
    {
        $adjustmentType = $data['adjustment_type']; // set, add, subtract
        $quantity = (int) $data['quantity'];
        $reason = $data['reason'] ?? 'Bulk stock adjustment';

        $updated = 0;
        $products = Product::whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            $oldQuantity = $product->stock_quantity ?? 0;

            $newQuantity = match ($adjustmentType) {
                'set' => $quantity,
                'add' => $oldQuantity + $quantity,
                'subtract' => max(0, $oldQuantity - $quantity),
                default => $oldQuantity,
            };

            $product->stock_quantity = $newQuantity;
            $product->save();
            $updated++;

            $this->logActivity($product, 'stock_adjusted', $user, [
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'adjustment_type' => $adjustmentType,
                'reason' => $reason,
            ]);
        }

        return [
            'updated' => $updated,
        ];
    }

    /**
     * Get activity log for a product.
     */
    public function getActivityLog(Product $product, int $limit = 10): array
    {
        // This would typically come from an activity_logs table
        // For now, return a basic structure
        return [
            [
                'action' => 'created',
                'user' => $product->creator?->name ?? 'System',
                'created_at' => $product->created_at?->format('M d, Y H:i'),
                'description' => 'Product created',
            ],
            [
                'action' => 'updated',
                'user' => 'System',
                'created_at' => $product->updated_at?->format('M d, Y H:i'),
                'description' => 'Product last updated',
            ],
        ];
    }

    /**
     * Sync product images.
     */
    protected function syncImages(Product $product, array $images): void
    {
        foreach ($images as $index => $imageData) {
            if (isset($imageData['id'])) {
                // Update existing image
                ProductImage::where('id', $imageData['id'])
                    ->where('product_id', $product->id)
                    ->update([
                        'url' => $imageData['url'],
                        'alt_text' => $imageData['alt_text'] ?? null,
                        'sort_order' => $imageData['sort_order'] ?? $index,
                    ]);
            } else {
                // Create new image
                ProductImage::create([
                    'product_id' => $product->id,
                    'url' => $imageData['url'],
                    'alt_text' => $imageData['alt_text'] ?? null,
                    'sort_order' => $imageData['sort_order'] ?? $index,
                ]);
            }
        }
    }

    /**
     * Get changes between original and updated data.
     */
    protected function getChanges(array $original, array $updated): array
    {
        $changes = [];
        $trackFields = [
            'name', 'sku', 'status', 'selling_price', 'cost_price',
            'stock_quantity', 'category_id', 'brand_id', 'condition',
        ];

        foreach ($trackFields as $field) {
            $oldValue = $original[$field] ?? null;
            $newValue = $updated[$field] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Emit a domain-level product audit event.
     */
    protected function logActivity(Product $product, string $action, User $user, array $data = []): void
    {
        audit_event(
            action: 'product.'.$action,
            description: "Product {$product->name} ({$product->sku}): {$action}",
            subject: $product,
            metadata: array_merge($data, ['source' => 'web']),
            severity: 'info',
            category: 'product',
            actor: $user,
        );
    }
}
