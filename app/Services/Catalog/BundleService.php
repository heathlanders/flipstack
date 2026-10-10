<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductBundle;
use App\Models\Catalog\ProductBundleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BundleService
{
    public function create(array $data, string $organizationId): ProductBundle
    {
        return DB::transaction(function () use ($data, $organizationId) {
            // Mark the product as a bundle
            $product = Product::findOrFail($data['product_id']);
            $product->update(['is_bundle' => true]);

            $bundle = ProductBundle::create([
                'organization_id' => $organizationId,
                'product_id' => $data['product_id'],
                'name' => $data['name'] ?? $product->name,
                'description' => $data['description'] ?? null,
                'bundle_type' => $data['bundle_type'] ?? 'fixed',
                'pick_n_quantity' => $data['pick_n_quantity'] ?? null,
                'is_active' => true,
            ]);

            if (! empty($data['components'])) {
                foreach ($data['components'] as $idx => $component) {
                    ProductBundleItem::create([
                        'bundle_id' => $bundle->id,
                        'component_product_id' => $component['product_id'],
                        'quantity' => $component['quantity'] ?? 1,
                        'sort_order' => $idx,
                        'is_required' => $component['is_required'] ?? true,
                    ]);
                }
            }

            // Update bundle product availability
            $this->updateBundleAvailability($bundle);

            Log::info('Product bundle created', ['bundle_id' => $bundle->id, 'product_id' => $product->id]);

            return $bundle->fresh(['items.componentProduct', 'product']);
        });
    }

    public function updateBundleAvailability(ProductBundle $bundle): void
    {
        $available = $bundle->calculateAvailableQuantity();
        $bundle->product?->update(['available_quantity' => $available]);
    }

    public function reserveComponentsForOrder(string $productId, int $orderQuantity): void
    {
        $bundle = ProductBundle::where('product_id', $productId)->with('items.componentProduct')->first();
        if (! $bundle) {
            return;
        }

        foreach ($bundle->items as $item) {
            $componentQty = $item->quantity * $orderQuantity;
            $product = $item->componentProduct;
            if ($product) {
                $product->decrementAvailableQuantity($componentQty);
            }
        }

        $this->updateBundleAvailability($bundle);
    }

    public function getStats(string $organizationId): array
    {
        $base = ProductBundle::withoutGlobalScopes()->where('organization_id', $organizationId);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
        ];
    }
}
