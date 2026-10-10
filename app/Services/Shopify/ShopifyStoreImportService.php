<?php

namespace App\Services\Shopify;

use App\Models\Catalog\Product;
use App\Models\Shopify\ShopifyListing;
use Illuminate\Support\Facades\Log;

class ShopifyStoreImportService extends ShopifyBaseService
{
    /**
     * Fetch all active Shopify products without importing them.
     */
    public function fetchAllProducts(): array
    {
        return $this->paginateAll('products', 'products', ['status' => 'active']);
    }

    public function importAllProducts(int $organizationId, int $userId, array $options = []): array
    {
        $skipExistingSkus = $options['skip_existing_skus'] ?? true;
        $allProducts = $this->paginateAll('products', 'products', ['status' => 'active']);

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $createdProductIds = [];

        foreach ($allProducts as $shopifyProduct) {
            try {
                $variant = $shopifyProduct['variants'][0] ?? [];
                $sku = $variant['sku'] ?? 'SHOPIFY-'.$shopifyProduct['id'];

                // Check for existing listing
                if (ShopifyListing::where('shopify_product_id', $shopifyProduct['id'])
                    ->where('integration_credential_id', $this->credential->id)
                    ->exists()) {
                    $skipped++;

                    continue;
                }

                // Check for existing product by SKU
                if ($skipExistingSkus) {
                    $existingProduct = Product::where('organization_id', $organizationId)
                        ->where('sku', $sku)
                        ->first();

                    if ($existingProduct) {
                        // Create listing linking existing product to Shopify
                        $this->createListingRecord($existingProduct, $shopifyProduct);
                        $imported++;

                        continue;
                    }
                }

                // Create new product
                $product = $this->createProductFromShopify($shopifyProduct, $organizationId, $userId);

                if ($product) {
                    $this->createListingRecord($product, $shopifyProduct);
                    $createdProductIds[] = $product->id;
                    $imported++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                Log::warning('Shopify import item failed', [
                    'shopify_product_id' => $shopifyProduct['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'total' => count($allProducts),
            'created_product_ids' => $createdProductIds,
        ];
    }

    public function createProductFromShopify(array $shopifyProduct, int $organizationId, int $userId): ?Product
    {
        $variant = $shopifyProduct['variants'][0] ?? [];

        return Product::create([
            'organization_id' => $organizationId,
            'name' => $shopifyProduct['title'] ?? 'Untitled',
            'description' => $shopifyProduct['body_html'] ?? null,
            'sku' => $variant['sku'] ?? 'SHOPIFY-'.$shopifyProduct['id'],
            'barcode' => $variant['barcode'] ?? null,
            'selling_price' => $variant['price'] ?? 0,
            'msrp' => $variant['compare_at_price'] ?? null,
            'cost_price' => 0,
            'weight' => $variant['weight'] ?? null,
            'status' => 'active',
            'type' => 'simple',
            'manage_stock' => $variant['inventory_management'] === 'shopify',
            'stock_quantity' => $variant['inventory_quantity'] ?? 0,
            'track_inventory' => $variant['inventory_management'] === 'shopify',
            'requires_shipping' => $variant['requires_shipping'] ?? true,
            'created_by' => $userId,
            'condition' => 'NEW',
            'featured_image' => $shopifyProduct['image']['src'] ?? null,
        ]);
    }

    public function createListingRecord(Product $product, array $shopifyProduct): ShopifyListing
    {
        $variant = $shopifyProduct['variants'][0] ?? [];

        return ShopifyListing::create([
            'product_id' => $product->id,
            'integration_credential_id' => $this->credential->id,
            'team_id' => $this->credential->team_id,
            'shopify_product_id' => $shopifyProduct['id'],
            'shopify_variant_id' => $variant['id'] ?? null,
            'shopify_inventory_item_id' => $variant['inventory_item_id'] ?? null,
            'shopify_sku' => $variant['sku'] ?? 'SHOPIFY-'.$shopifyProduct['id'],
            'shopify_handle' => $shopifyProduct['handle'] ?? null,
            'status' => $shopifyProduct['status'] === 'active'
                ? ShopifyListing::STATUS_ACTIVE
                : ShopifyListing::STATUS_DRAFT,
            'listed_price' => $variant['price'] ?? null,
            'compare_at_price' => $variant['compare_at_price'] ?? null,
            'currency' => 'USD',
            'inventory_quantity' => $variant['inventory_quantity'] ?? 0,
            'last_synced_at' => now(),
            'last_shopify_response' => $shopifyProduct,
        ]);
    }
}
