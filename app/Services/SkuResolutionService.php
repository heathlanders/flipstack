<?php

namespace App\Services;

use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\SupplierProduct;
use App\Services\Inventory\WarehouseAutoBuilder;

class SkuResolutionService
{
    /**
     * Resolve a single product's SKU by setting owner (supplier), internal SKU, and location.
     */
    public function resolve(Product $product, array $data): void
    {
        $organizationId = $product->organization_id;
        $owner = trim($data['owner'] ?? '');
        $internalSku = trim($data['internal_sku'] ?? '');
        $location = trim($data['location'] ?? '');

        // Update product SKU if a new internal SKU was provided
        if ($internalSku !== '' && $internalSku !== $product->sku) {
            $product->update([
                'sku' => $internalSku,
                'attributes' => array_merge($product->attributes ?? [], [
                    'raw_sku' => $product->sku,
                ]),
            ]);
        }

        // Create/link supplier if owner provided
        if ($owner !== '') {
            $supplier = Supplier::firstOrCreate(
                ['organization_id' => $organizationId, 'code' => $owner],
                ['name' => $owner, 'supplier_status' => 'active']
            );

            SupplierProduct::updateOrCreate(
                ['supplier_id' => $supplier->id, 'product_id' => $product->id],
                [
                    'supplier_sku' => $product->sku,
                    'cost_price' => 0,
                    'is_active' => true,
                ]
            );
        }

        // Create/link inventory location if provided
        if ($location !== '') {
            // Check if product has structured warehouse location data from import
            $warehouseData = $data['warehouse_data'] ?? null;
            $binId = null;

            if ($warehouseData !== null) {
                $builder = new WarehouseAutoBuilder;
                $warehouse = $builder->resolveMainWarehouse($organizationId);
                $bin = $builder->buildFromParsedLocation($warehouseData, $organizationId, $warehouse->id);
                $binId = $bin?->id;

                InventoryLevel::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'location_id' => $warehouse->id,
                        'organization_id' => $organizationId,
                    ],
                    [
                        'quantity_on_hand' => $product->stock_quantity ?? 0,
                        'quantity_reserved' => 0,
                        'bin_id' => $binId,
                    ]
                );
            } else {
                $inventoryLocation = InventoryLocation::firstOrCreate(
                    ['organization_id' => $organizationId, 'code' => $location],
                    ['name' => $location, 'is_active' => true]
                );

                InventoryLevel::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'location_id' => $inventoryLocation->id,
                        'organization_id' => $organizationId,
                    ],
                    [
                        'quantity_on_hand' => $product->stock_quantity ?? 0,
                        'quantity_reserved' => 0,
                    ]
                );
            }
        }

        // Recalculate completeness score after resolution
        $this->updateCompletenessScore($product);
    }

    /**
     * Bulk resolve products matching a prefix pattern.
     * Returns the number of products resolved.
     */
    public function bulkResolveByPrefix(
        int $organizationId,
        string $prefix,
        string $owner,
        ?string $locationPattern = null
    ): int {
        $products = Product::where('organization_id', $organizationId)
            ->whereHas('ebayListing')
            ->whereDoesntHave('supplierProducts')
            ->where(function ($q) use ($prefix) {
                $q->where('sku', 'like', $prefix.'%')
                    ->orWhereJsonContains('attributes->raw_sku', null)
                    ->where('sku', 'like', $prefix.'%');
            })
            ->get();

        $count = 0;
        foreach ($products as $product) {
            $this->resolve($product, [
                'owner' => $owner,
                'internal_sku' => '',
                'location' => $locationPattern ?? '',
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Calculate and store a completeness score (0-10) on the product.
     */
    public function updateCompletenessScore(Product $product): void
    {
        $score = 0;

        if (! empty($product->gallery_images) || ! empty($product->featured_image)) {
            $score++;
        }
        if (! empty($product->description)) {
            $score++;
        }
        if ($product->weight > 0) {
            $score++;
        }
        if ($product->length > 0 && $product->width > 0 && $product->height > 0) {
            $score++;
        }
        if (! empty($product->upc) || ! empty($product->ean)) {
            $score++;
        }
        if ($product->brand_id) {
            $score++;
        }
        if (! empty($product->condition)) {
            $score++;
        }
        if ($product->category_id) {
            $score++;
        }
        if ($product->supplierProducts()->exists()) {
            $score++;
        }
        if (InventoryLevel::where('product_id', $product->id)->exists()) {
            $score++;
        }

        $attributes = $product->attributes ?? [];
        $attributes['completeness_score'] = $score;
        $product->update(['attributes' => $attributes]);
    }
}
