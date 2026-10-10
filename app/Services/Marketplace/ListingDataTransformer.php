<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Str;

class ListingDataTransformer
{
    /**
     * Transform listing data from one marketplace format to another.
     */
    public function transform(array $data, string $from, string $to): array
    {
        if ($from === $to) {
            return $data;
        }

        return match ("{$from}_to_{$to}") {
            'ebay_to_shopify' => $this->ebayToShopify($data),
            'shopify_to_ebay' => $this->shopifyToEbay($data),
            default => $data,
        };
    }

    /**
     * Map eBay listing fields to Shopify format.
     */
    public function ebayToShopify(array $data): array
    {
        $images = $this->normalizeImages($data['images'] ?? []);

        return [
            'title' => $data['title'] ?? $data['name'] ?? '',
            'body_html' => $data['description'] ?? '',
            'price' => $data['listed_price'] ?? $data['selling_price'] ?? '0.00',
            'compare_at_price' => $data['msrp'] ?? null,
            'sku' => $data['sku'] ?? '',
            'barcode' => $data['upc'] ?? $data['ean'] ?? $data['barcode'] ?? '',
            'weight' => $data['weight'] ?? null,
            'inventory_quantity' => $data['available_quantity'] ?? $data['stock_quantity'] ?? 0,
            'product_type' => $this->buildShopifyProductType($data),
            'vendor' => $data['brand'] ?? '',
            'tags' => $this->buildShopifyTags($data),
            'images' => $images,
            'status' => 'draft',
            // Preserve source metadata
            '_source' => 'ebay',
            '_source_condition' => $data['condition'] ?? null,
            '_source_condition_description' => $data['condition_description'] ?? null,
            '_source_category' => $data['ebay_category_name'] ?? null,
        ];
    }

    /**
     * Map Shopify listing fields to eBay format.
     */
    public function shopifyToEbay(array $data): array
    {
        $images = $this->normalizeImages($data['images'] ?? []);

        return [
            'title' => $data['title'] ?? $data['name'] ?? '',
            'description' => $data['body_html'] ?? $data['description'] ?? '',
            'listed_price' => $data['price'] ?? $data['listed_price'] ?? $data['selling_price'] ?? '0.00',
            'currency' => $data['currency'] ?? 'USD',
            'sku' => $data['sku'] ?? '',
            'condition' => $this->inferEbayCondition($data),
            'condition_description' => $data['_source_condition_description'] ?? '',
            'listed_quantity' => $data['inventory_quantity'] ?? $data['available_quantity'] ?? $data['stock_quantity'] ?? 1,
            'listing_format' => 'FIXED_PRICE',
            'images' => $images,
            'upc' => $data['barcode'] ?? $data['upc'] ?? '',
            'weight' => $data['weight'] ?? null,
            // Preserve source metadata
            '_source' => 'shopify',
            '_source_product_type' => $data['product_type'] ?? null,
            '_source_tags' => $data['tags'] ?? null,
        ];
    }

    /**
     * Normalize image data to a common array format of URL strings.
     */
    public function normalizeImages(array $images): array
    {
        $normalized = [];

        foreach ($images as $image) {
            if (is_string($image)) {
                $normalized[] = $image;
            } elseif (is_array($image)) {
                // Handle Shopify image format {src: '...'} or {url: '...'}
                $url = $image['src'] ?? $image['url'] ?? $image['image_url'] ?? null;
                if ($url) {
                    $normalized[] = $url;
                }
            }
        }

        return $normalized;
    }

    /**
     * Build Shopify product_type from eBay listing data.
     */
    private function buildShopifyProductType(array $data): string
    {
        $parts = [];

        if (! empty($data['ebay_category_name'])) {
            $parts[] = $data['ebay_category_name'];
        }

        if (! empty($data['condition']) && $data['condition'] !== 'NEW') {
            $parts[] = '('.ucfirst(strtolower($data['condition'])).')';
        }

        return implode(' ', $parts) ?: '';
    }

    /**
     * Build Shopify tags from eBay listing data.
     */
    private function buildShopifyTags(array $data): string
    {
        $tags = [];

        if (! empty($data['condition'])) {
            $tags[] = 'condition:'.strtolower($data['condition']);
        }

        if (! empty($data['brand'])) {
            $tags[] = 'brand:'.Str::slug($data['brand']);
        }

        $tags[] = 'cross-listed';
        $tags[] = 'source:ebay';

        return implode(', ', $tags);
    }

    /**
     * Infer eBay condition from Shopify data.
     */
    private function inferEbayCondition(array $data): string
    {
        $condition = $data['condition'] ?? $data['_source_condition'] ?? null;

        if ($condition) {
            return strtoupper($condition);
        }

        // Try to infer from product_type or tags
        $productType = strtolower($data['product_type'] ?? '');
        $tags = strtolower($data['tags'] ?? '');

        if (Str::contains($productType.' '.$tags, ['used', 'pre-owned', 'preowned'])) {
            return 'USED_EXCELLENT';
        }

        if (Str::contains($productType.' '.$tags, ['refurbished', 'renewed'])) {
            return 'SELLER_REFURBISHED';
        }

        return 'NEW';
    }
}
