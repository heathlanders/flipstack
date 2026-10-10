<?php

namespace App\Services\Marketplace;

use App\Models\Catalog\Product;
use App\Models\Marketplace\MarketplaceProductMatch;
use App\Models\Shopify\ShopifyListing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductMatchingService
{
    /**
     * Match an array of Shopify products against existing Products in the organization.
     *
     * @return array{matched: int, unmatched: int, skipped: int, total: int}
     */
    public function matchShopifyProducts(
        int $organizationId,
        int $credentialId,
        int $bulkOperationJobId,
        array $shopifyProducts
    ): array {
        $matched = 0;
        $unmatched = 0;
        $skipped = 0;

        // Track which product IDs have been matched to detect duplicates
        $matchedProductIds = [];

        foreach ($shopifyProducts as $shopifyProduct) {
            try {
                $shopifyProductId = (string) $shopifyProduct['id'];
                $variant = $shopifyProduct['variants'][0] ?? [];
                $sku = $variant['sku'] ?? null;
                $barcode = $variant['barcode'] ?? null;
                $title = $shopifyProduct['title'] ?? 'Untitled';

                // Step 1: Already linked? Skip if ShopifyListing exists
                if ($this->isAlreadyLinked($shopifyProductId, $credentialId)) {
                    $skipped++;

                    continue;
                }

                // Step 2: Already in a pending match session? Skip to avoid duplicates on re-run
                if ($this->hasPendingMatch($shopifyProductId, $credentialId, $bulkOperationJobId)) {
                    $skipped++;

                    continue;
                }

                // Run the matching waterfall
                $matchResult = $this->runMatchingWaterfall($organizationId, $sku, $barcode, $title);

                // Check for duplicate product match
                $isDuplicate = false;
                if ($matchResult['product_id']) {
                    if (isset($matchedProductIds[$matchResult['product_id']])) {
                        $isDuplicate = true;
                        // Also flag the earlier match that used this product as needing review
                        $this->flagDuplicateMatch($matchedProductIds[$matchResult['product_id']], $bulkOperationJobId);
                    }
                    $matchedProductIds[$matchResult['product_id']] = $shopifyProductId;
                }

                $status = MarketplaceProductMatch::STATUS_PENDING;

                MarketplaceProductMatch::updateOrCreate(
                    [
                        'source_marketplace' => 'shopify',
                        'source_listing_id' => $shopifyProductId,
                        'integration_credential_id' => $credentialId,
                    ],
                    [
                        'organization_id' => $organizationId,
                        'bulk_operation_job_id' => $bulkOperationJobId,
                        'product_id' => $isDuplicate ? null : $matchResult['product_id'],
                        'source_variant_id' => isset($variant['id']) ? (string) $variant['id'] : null,
                        'source_inventory_item_id' => isset($variant['inventory_item_id']) ? (string) $variant['inventory_item_id'] : null,
                        'source_sku' => $sku ?: null,
                        'source_barcode' => $barcode ?: null,
                        'source_title' => mb_substr($title, 0, 500),
                        'source_price' => $variant['price'] ?? null,
                        'source_quantity' => $variant['inventory_quantity'] ?? null,
                        'source_data' => $shopifyProduct,
                        'match_type' => $isDuplicate ? null : $matchResult['match_type'],
                        'confidence' => $isDuplicate ? null : $matchResult['confidence'],
                        'status' => $status,
                    ]
                );

                if ($matchResult['product_id'] && ! $isDuplicate) {
                    $matched++;
                } else {
                    $unmatched++;
                }
            } catch (\Exception $e) {
                Log::warning('ProductMatchingService: failed to match Shopify product', [
                    'shopify_product_id' => $shopifyProduct['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                $unmatched++;
            }
        }

        return [
            'matched' => $matched,
            'unmatched' => $unmatched,
            'skipped' => $skipped,
            'total' => count($shopifyProducts),
        ];
    }

    /**
     * Run the matching waterfall: SKU > UPC/EAN/barcode > title fuzzy.
     *
     * @return array{product_id: ?string, match_type: ?string, confidence: ?int}
     */
    protected function runMatchingWaterfall(int $organizationId, ?string $sku, ?string $barcode, string $title): array
    {
        // Step 1: SKU match (confidence 100)
        if ($sku && $sku !== '') {
            $product = Product::where('organization_id', $organizationId)
                ->where('sku', $sku)
                ->first();

            if ($product) {
                return [
                    'product_id' => $product->id,
                    'match_type' => MarketplaceProductMatch::MATCH_SKU,
                    'confidence' => 100,
                ];
            }
        }

        // Step 2: UPC/EAN/barcode match (confidence 95)
        if ($barcode && $barcode !== '' && $this->looksLikeIdentifier($barcode)) {
            $product = $this->matchByBarcode($organizationId, $barcode);

            if ($product) {
                $matchType = $this->detectBarcodeMatchType($barcode);

                return [
                    'product_id' => $product->id,
                    'match_type' => $matchType,
                    'confidence' => 95,
                ];
            }
        }

        // Step 3: Title fuzzy match via pg_trgm
        $titleMatch = $this->matchByTitle($organizationId, $title);

        if ($titleMatch) {
            return $titleMatch;
        }

        // No match
        return [
            'product_id' => null,
            'match_type' => null,
            'confidence' => null,
        ];
    }

    protected function isAlreadyLinked(string $shopifyProductId, int $credentialId): bool
    {
        return ShopifyListing::where('shopify_product_id', $shopifyProductId)
            ->where('integration_credential_id', $credentialId)
            ->exists();
    }

    protected function hasPendingMatch(string $shopifyProductId, int $credentialId, int $bulkJobId): bool
    {
        return MarketplaceProductMatch::where('source_marketplace', 'shopify')
            ->where('source_listing_id', $shopifyProductId)
            ->where('integration_credential_id', $credentialId)
            ->where('bulk_operation_job_id', $bulkJobId)
            ->exists();
    }

    protected function matchByBarcode(int $organizationId, string $barcode): ?Product
    {
        // Check direct columns first (faster)
        $product = Product::where('organization_id', $organizationId)
            ->where(function ($q) use ($barcode) {
                $q->where('upc', $barcode)
                    ->orWhere('ean', $barcode)
                    ->orWhere('barcode', $barcode);
            })
            ->first();

        if ($product) {
            return $product;
        }

        // Fall back to ProductIdentifier table
        return Product::where('organization_id', $organizationId)
            ->byAnyIdentifier($barcode)
            ->first();
    }

    /**
     * Fuzzy title match using PostgreSQL pg_trgm similarity().
     *
     * @return array{product_id: string, match_type: string, confidence: int}|null
     */
    protected function matchByTitle(int $organizationId, string $title): ?array
    {
        $result = DB::selectOne(
            'SELECT id, name, similarity(name, ?) as sim
             FROM products
             WHERE organization_id = ?
               AND similarity(name, ?) > 0.4
             ORDER BY sim DESC
             LIMIT 1',
            [$title, $organizationId, $title]
        );

        if (! $result) {
            return null;
        }

        $confidence = (int) round($result->sim * 100);

        return [
            'product_id' => $result->id,
            'match_type' => MarketplaceProductMatch::MATCH_TITLE,
            'confidence' => $confidence,
        ];
    }

    protected function looksLikeIdentifier(string $value): bool
    {
        $cleaned = preg_replace('/[\s\-]/', '', $value);

        // UPC (12 digits), EAN (13 digits), ISBN (10 or 13)
        return preg_match('/^\d{10,14}$/', $cleaned) === 1;
    }

    protected function detectBarcodeMatchType(string $barcode): string
    {
        $length = strlen(preg_replace('/[\s\-]/', '', $barcode));

        if ($length === 12) {
            return MarketplaceProductMatch::MATCH_UPC;
        }

        if ($length === 13) {
            return MarketplaceProductMatch::MATCH_EAN;
        }

        return MarketplaceProductMatch::MATCH_BARCODE;
    }

    /**
     * Flag an earlier match row as needing review because a duplicate product was found.
     */
    protected function flagDuplicateMatch(string $shopifyProductId, int $bulkJobId): void
    {
        MarketplaceProductMatch::where('source_listing_id', $shopifyProductId)
            ->where('bulk_operation_job_id', $bulkJobId)
            ->update([
                'product_id' => null,
                'match_type' => null,
                'confidence' => null,
            ]);
    }
}
