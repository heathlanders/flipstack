<?php

namespace App\Services\Amazon;

use App\Models\Amazon\AmazonListing;
use App\Models\Catalog\Product;
use Illuminate\Support\Facades\Log;

class AmazonStoreImportService extends AmazonBaseService
{
    public function importAllProducts(int $organizationId, int $userId, array $options = []): array
    {
        $skipExistingSkus = $options['skip_existing_skus'] ?? true;

        $reportsService = new AmazonReportsService($this->credential);
        $allProducts = $reportsService->getActiveListingsReport();

        if ($allProducts === null) {
            return ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'total' => 0];
        }

        $imported = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($allProducts as $amazonProduct) {
            try {
                $sku = $amazonProduct['seller-sku'] ?? $amazonProduct['sku'] ?? null;
                $asin = $amazonProduct['asin1'] ?? $amazonProduct['asin'] ?? null;

                if (! $sku) {
                    $failed++;

                    continue;
                }

                // Check for existing listing
                if (AmazonListing::where('amazon_sku', $sku)
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
                        $this->createListingRecord($existingProduct, $amazonProduct);
                        $imported++;

                        continue;
                    }
                }

                // Create new product
                $product = $this->createProductFromAmazon($amazonProduct, $organizationId, $userId);

                if ($product) {
                    $this->createListingRecord($product, $amazonProduct);
                    $imported++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                Log::warning('Amazon import item failed', [
                    'sku' => $amazonProduct['seller-sku'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'total' => count($allProducts),
        ];
    }

    public function createProductFromAmazon(array $amazonProduct, int $organizationId, int $userId): ?Product
    {
        $sku = $amazonProduct['seller-sku'] ?? $amazonProduct['sku'] ?? 'AMAZON-'.uniqid();

        return Product::create([
            'organization_id' => $organizationId,
            'name' => $amazonProduct['item-name'] ?? $amazonProduct['product-name'] ?? 'Untitled',
            'description' => $amazonProduct['item-description'] ?? null,
            'sku' => $sku,
            'barcode' => $amazonProduct['product-id'] ?? null,
            'selling_price' => $amazonProduct['price'] ?? 0,
            'cost_price' => 0,
            'status' => 'active',
            'type' => 'simple',
            'manage_stock' => true,
            'stock_quantity' => (int) ($amazonProduct['quantity'] ?? 0),
            'track_inventory' => true,
            'requires_shipping' => true,
            'created_by' => $userId,
            'condition' => $this->mapConditionFromAmazon($amazonProduct['item-condition'] ?? 'New'),
            'featured_image' => $amazonProduct['image-url'] ?? null,
        ]);
    }

    public function createListingRecord(Product $product, array $amazonProduct): AmazonListing
    {
        $sku = $amazonProduct['seller-sku'] ?? $amazonProduct['sku'] ?? $product->sku;
        $fulfillment = ($amazonProduct['fulfillment-channel'] ?? 'DEFAULT') === 'AMAZON_NA'
            ? AmazonListing::FULFILLMENT_FBA
            : AmazonListing::FULFILLMENT_FBM;

        return AmazonListing::create([
            'product_id' => $product->id,
            'integration_credential_id' => $this->credential->id,
            'team_id' => $this->credential->team_id,
            'asin' => $amazonProduct['asin1'] ?? $amazonProduct['asin'] ?? null,
            'amazon_sku' => $sku,
            'fulfillment_channel' => $fulfillment,
            'status' => ($amazonProduct['status'] ?? 'Active') === 'Active'
                ? AmazonListing::STATUS_ACTIVE
                : AmazonListing::STATUS_INACTIVE,
            'amazon_status' => $amazonProduct['status'] ?? 'Active',
            'listed_price' => $amazonProduct['price'] ?? null,
            'currency' => 'USD',
            'inventory_quantity' => (int) ($amazonProduct['quantity'] ?? 0),
            'last_synced_at' => now(),
            'last_amazon_response' => $amazonProduct,
        ]);
    }

    protected function mapConditionFromAmazon(string $condition): string
    {
        return match (strtolower($condition)) {
            'new' => 'NEW',
            'refurbished' => 'REFURBISHED',
            'usedlikenew' => 'LIKE_NEW',
            'usedverygood' => 'VERY_GOOD',
            'usedgood' => 'GOOD',
            'usedacceptable' => 'ACCEPTABLE',
            default => 'NEW',
        };
    }
}
