<?php

namespace App\Services\Amazon;

use App\Models\Amazon\AmazonSyncLog;
use App\Models\Catalog\Product;

class AmazonCatalogService extends AmazonBaseService
{
    public function searchByGtin(string $gtin): ?array
    {
        $syncLog = $this->createSyncLog(AmazonSyncLog::OP_MATCH_ASIN, [
            'amazon_sku' => $gtin,
        ]);

        try {
            $response = $this->get('/catalog/2022-04-01/items', [
                'marketplaceIds' => $this->marketplaceId,
                'identifiers' => $gtin,
                'identifiersType' => 'UPC',
                'includedData' => 'summaries,identifiers,attributes',
            ], $syncLog);

            $items = $response['items'] ?? [];

            if (empty($items)) {
                return null;
            }

            $item = $items[0];
            $summary = $item['summaries'][0] ?? [];

            return [
                'asin' => $item['asin'] ?? null,
                'title' => $summary['itemName'] ?? null,
                'product_type' => $summary['productType'] ?? null,
                'brand' => $summary['brand'] ?? null,
                'match_source' => 'gtin',
                'match_confidence' => 100.0,
            ];
        } catch (AmazonApiException $e) {
            if ($e->isNotFound()) {
                return null;
            }
            throw $e;
        }
    }

    public function searchByKeywords(string $keywords): array
    {
        $syncLog = $this->createSyncLog(AmazonSyncLog::OP_MATCH_ASIN);

        $response = $this->get('/catalog/2022-04-01/items', [
            'marketplaceIds' => $this->marketplaceId,
            'keywords' => $keywords,
            'includedData' => 'summaries,identifiers',
        ], $syncLog);

        $items = $response['items'] ?? [];
        $results = [];

        foreach ($items as $item) {
            $summary = $item['summaries'][0] ?? [];
            $results[] = [
                'asin' => $item['asin'] ?? null,
                'title' => $summary['itemName'] ?? null,
                'product_type' => $summary['productType'] ?? null,
                'brand' => $summary['brand'] ?? null,
                'match_source' => 'search',
                'match_confidence' => null,
            ];
        }

        return $results;
    }

    public function matchProduct(Product $product): ?array
    {
        // Try GTIN first (UPC/EAN/barcode)
        $gtins = array_filter([
            $product->upc,
            $product->ean,
            $product->barcode,
        ]);

        foreach ($gtins as $gtin) {
            $result = $this->searchByGtin($gtin);
            if ($result) {
                return $result;
            }
        }

        // Fallback to keyword search
        $keywords = $product->name;
        if ($product->brand?->name) {
            $keywords = $product->brand->name.' '.$keywords;
        }

        $results = $this->searchByKeywords($keywords);

        if (! empty($results)) {
            $best = $results[0];
            $best['match_confidence'] = 60.0;

            return $best;
        }

        return null;
    }
}
