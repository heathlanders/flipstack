<?php

namespace App\Services\Shopify;

use App\Models\Catalog\Product;
use App\Models\Shopify\ShopifyListing;
use App\Models\Shopify\ShopifySyncLog;

class ShopifyProductService extends ShopifyBaseService
{
    public function createProduct(Product $product, ShopifyListing $listing): array
    {
        $syncLog = $this->createSyncLog(ShopifySyncLog::OP_CREATE_PRODUCT, [
            'product_id' => $product->id,
            'shopify_listing_id' => $listing->id,
        ]);

        $productData = $this->toShopifyProductData($product);

        $response = $this->post('products', ['product' => $productData], $syncLog);

        $shopifyProduct = $response['product'] ?? [];
        $variant = $shopifyProduct['variants'][0] ?? [];

        $listing->update([
            'shopify_product_id' => $shopifyProduct['id'] ?? null,
            'shopify_variant_id' => $variant['id'] ?? null,
            'shopify_inventory_item_id' => $variant['inventory_item_id'] ?? null,
            'shopify_handle' => $shopifyProduct['handle'] ?? null,
            'status' => $shopifyProduct['status'] === 'active'
                ? ShopifyListing::STATUS_ACTIVE
                : ShopifyListing::STATUS_DRAFT,
            'listed_price' => $variant['price'] ?? $product->selling_price,
            'compare_at_price' => $variant['compare_at_price'] ?? null,
            'product_data_hash' => $listing->generateProductHash(),
            'last_sync_payload' => $productData,
            'last_shopify_response' => $shopifyProduct,
            'last_synced_at' => now(),
            'consecutive_errors' => 0,
        ]);

        audit_event(
            action: 'listing.shopify.published',
            description: "Published {$product->sku} to Shopify",
            subject: $listing->fresh(),
            metadata: [
                'shopify_product_id' => $shopifyProduct['id'] ?? null,
                'sku' => $product->sku,
                'price' => $variant['price'] ?? $product->selling_price,
                'source' => app()->runningInConsole() ? 'job' : 'web',
            ],
            severity: 'info',
            category: 'marketplace',
            organization: $product->organization,
        );

        return $shopifyProduct;
    }

    public function updateProduct(Product $product, ShopifyListing $listing, bool $force = false): ?array
    {
        if (! $force && ! $listing->hasProductChanged()) {
            return null;
        }

        $syncLog = $this->createSyncLog(ShopifySyncLog::OP_UPDATE_PRODUCT, [
            'product_id' => $product->id,
            'shopify_listing_id' => $listing->id,
            'shopify_product_id' => $listing->shopify_product_id,
        ]);

        $productData = $this->toShopifyProductData($product);
        $productData['id'] = $listing->shopify_product_id;

        if ($listing->shopify_variant_id) {
            $productData['variants'][0]['id'] = $listing->shopify_variant_id;
        }

        $response = $this->put("products/{$listing->shopify_product_id}", ['product' => $productData], $syncLog);

        $shopifyProduct = $response['product'] ?? [];
        $variant = $shopifyProduct['variants'][0] ?? [];

        $listing->update([
            'listed_price' => $variant['price'] ?? $listing->listed_price,
            'compare_at_price' => $variant['compare_at_price'] ?? $listing->compare_at_price,
            'product_data_hash' => $listing->generateProductHash(),
            'last_sync_payload' => $productData,
            'last_shopify_response' => $shopifyProduct,
            'last_synced_at' => now(),
            'consecutive_errors' => 0,
        ]);

        return $shopifyProduct;
    }

    public function deleteProduct(ShopifyListing $listing): void
    {
        $syncLog = $this->createSyncLog(ShopifySyncLog::OP_DELETE_PRODUCT, [
            'shopify_listing_id' => $listing->id,
            'shopify_product_id' => $listing->shopify_product_id,
        ]);

        $this->delete("products/{$listing->shopify_product_id}", $syncLog);

        $listing->update([
            'status' => ShopifyListing::STATUS_DELETED,
            'last_synced_at' => now(),
        ]);
    }

    public function archiveProduct(ShopifyListing $listing): void
    {
        $syncLog = $this->createSyncLog(ShopifySyncLog::OP_ARCHIVE_PRODUCT, [
            'shopify_listing_id' => $listing->id,
            'shopify_product_id' => $listing->shopify_product_id,
        ]);

        $this->put("products/{$listing->shopify_product_id}", [
            'product' => [
                'id' => $listing->shopify_product_id,
                'status' => 'archived',
            ],
        ], $syncLog);

        $listing->update([
            'status' => ShopifyListing::STATUS_ARCHIVED,
            'last_synced_at' => now(),
        ]);
    }

    public function getProduct(int $shopifyProductId): array
    {
        $response = $this->get("products/{$shopifyProductId}");

        return $response['product'] ?? [];
    }

    public function listProducts(array $params = []): array
    {
        return $this->paginateAll('products', 'products', $params);
    }

    public function toShopifyProductData(Product $product): array
    {
        $data = [
            'title' => $product->name,
            'body_html' => $product->description ?? $product->short_description ?? '',
            'vendor' => $product->brand?->name ?? '',
            'product_type' => $this->resolveProductType($product),
            'status' => 'active',
            'tags' => $this->buildTags($product),
            'variants' => [
                [
                    'price' => (string) $product->selling_price,
                    'compare_at_price' => $product->msrp && $product->msrp > $product->selling_price
                        ? (string) $product->msrp
                        : null,
                    'sku' => $product->sku ?? $product->generateEbaySku(),
                    'barcode' => $product->upc ?? $product->barcode ?? $product->ean ?? null,
                    'weight' => $product->weight ? (float) $product->weight : null,
                    'weight_unit' => 'lb',
                    'inventory_management' => $product->manage_stock ? 'shopify' : null,
                    'requires_shipping' => $product->requires_shipping ?? true,
                ],
            ],
        ];

        $images = $this->buildImages($product);
        if (! empty($images)) {
            $data['images'] = $images;
        }

        $metafields = $this->buildMetafields($product);
        if (! empty($metafields)) {
            $data['metafields'] = $metafields;
        }

        // Add SEO
        if ($product->seo_title || $product->seo_description) {
            $data['metafields_global_title_tag'] = $product->seo_title;
            $data['metafields_global_description_tag'] = $product->seo_description;
        }

        // Apply marketplace overrides
        $overrides = $product->marketplace_overrides['shopify'] ?? [];
        if (! empty($overrides)) {
            $data = array_merge($data, $overrides);
        }

        return $data;
    }

    protected function resolveProductType(Product $product): string
    {
        $source = $this->credential->config['shopify_product_type_source'] ?? 'internal_category';

        return match ($source) {
            'ebay_category' => $this->getEbayCategoryName($product),
            'none' => '',
            default => $product->category?->name ?? '',
        };
    }

    protected function getEbayCategoryName(Product $product): string
    {
        $listing = $product->ebayListings()
            ->whereNotIn('status', ['deleted'])
            ->first();

        return $listing?->ebay_category_name ?? $product->category?->name ?? '';
    }

    protected function buildImages(Product $product): array
    {
        $images = [];
        $seenUrls = [];

        // Primary source: ProductImage relationship
        if ($product->relationLoaded('images') ? $product->images->isNotEmpty() : $product->images()->exists()) {
            $productImages = $product->images->sortBy('sort_order');

            // Ensure primary image is first
            $primary = $productImages->where('is_primary', true)->first();
            $rest = $productImages->where('is_primary', '!=', true);

            $ordered = $primary ? collect([$primary])->concat($rest) : $productImages;

            foreach ($ordered as $img) {
                $url = $this->ensureAbsoluteUrl($img->url);
                if (empty($url) || isset($seenUrls[$url])) {
                    continue;
                }
                $seenUrls[$url] = true;
                $images[] = [
                    'src' => $url,
                    'alt' => $img->alt_text ?? $product->name,
                ];
            }

            return $images;
        }

        // Fallback: flat featured_image / gallery_images fields
        if ($product->featured_image) {
            $url = $this->ensureAbsoluteUrl($product->featured_image);
            $seenUrls[$url] = true;
            $images[] = [
                'src' => $url,
                'alt' => $product->name,
            ];
        }

        if ($product->gallery_images) {
            foreach ($product->gallery_images as $img) {
                $url = is_array($img) ? ($img['url'] ?? $img['src'] ?? '') : $img;
                $url = $this->ensureAbsoluteUrl($url);
                if (empty($url) || isset($seenUrls[$url])) {
                    continue;
                }
                $seenUrls[$url] = true;
                $images[] = [
                    'src' => $url,
                    'alt' => $product->name,
                ];
            }
        }

        return $images;
    }

    protected function ensureAbsoluteUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        // Relative path -- prepend app URL
        $appUrl = rtrim(config('app.url'), '/');

        if (str_starts_with($url, '/storage/') || str_starts_with($url, 'storage/')) {
            return $appUrl.'/'.ltrim($url, '/');
        }

        return $appUrl.'/storage/'.ltrim($url, '/');
    }

    protected function buildTags(Product $product): string
    {
        $tags = [];

        if ($product->search_keywords) {
            $keywords = is_array($product->search_keywords)
                ? $product->search_keywords
                : array_map('trim', explode(',', $product->search_keywords));
            $tags = array_merge($tags, $keywords);
        }

        if ($product->condition) {
            $tags[] = $product->condition_label;
        }

        if ($product->category?->name) {
            $tags[] = $product->category->name;
        }

        if ($product->brand?->name) {
            $tags[] = $product->brand->name;
        }

        $tags = array_filter(array_unique($tags));

        return implode(', ', $tags);
    }

    protected function buildMetafields(Product $product): array
    {
        $metafields = [];

        $singleLineFields = [
            'condition' => $product->condition,
            'mpn' => $product->mpn,
            'upc' => $product->upc,
            'ean' => $product->ean,
        ];

        foreach ($singleLineFields as $key => $value) {
            if ($value !== null && $value !== '') {
                $metafields[] = [
                    'namespace' => 'custom',
                    'key' => $key,
                    'value' => (string) $value,
                    'type' => 'single_line_text_field',
                ];
            }
        }

        if ($product->condition_description) {
            $metafields[] = [
                'namespace' => 'custom',
                'key' => 'condition_description',
                'value' => $product->condition_description,
                'type' => 'multi_line_text_field',
            ];
        }

        if ($product->length || $product->width || $product->height) {
            $metafields[] = [
                'namespace' => 'custom',
                'key' => 'dimensions',
                'value' => json_encode([
                    'length' => $product->length,
                    'width' => $product->width,
                    'height' => $product->height,
                ]),
                'type' => 'json',
            ];
        }

        return $metafields;
    }
}
