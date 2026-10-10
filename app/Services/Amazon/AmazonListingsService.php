<?php

namespace App\Services\Amazon;

use App\Models\Amazon\AmazonListing;
use App\Models\Amazon\AmazonSyncLog;
use App\Models\Catalog\Product;
use Illuminate\Support\Facades\Cache;

class AmazonListingsService extends AmazonBaseService
{
    /**
     * Fetch the SP-API attribute schema for a productType. Required when
     * creating new ASINs (vs. matching to existing). Cached 24h because the
     * schema is large and stable.
     *
     * @return array{attributes: array, requirements: array}|null
     */
    public function getProductTypeDefinition(string $productType, ?string $marketplaceId = null): ?array
    {
        $marketplaceId = $marketplaceId ?? $this->marketplaceId;
        $cacheKey = "amazon:product_type_def:{$marketplaceId}:{$productType}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($productType, $marketplaceId) {
            try {
                $response = $this->get(
                    "/definitions/2020-09-01/productTypes/{$productType}",
                    ['marketplaceIds' => $marketplaceId]
                );

                return [
                    'attributes' => $response['schema']['attributes'] ?? [],
                    'requirements' => $response['schema']['required'] ?? [],
                    'product_type_version' => $response['productTypeVersion']['version'] ?? null,
                ];
            } catch (AmazonApiException $e) {
                if ($e->isNotFound()) {
                    return null;
                }
                throw $e;
            }
        });
    }

    public function createListing(Product $product, AmazonListing $listing): array
    {
        $syncLog = $this->createSyncLog(AmazonSyncLog::OP_CREATE_LISTING, [
            'product_id' => $product->id,
            'amazon_listing_id' => $listing->id,
        ]);

        $listingData = $this->toAmazonListingData($product, $listing);

        $response = $this->put(
            "/listings/2021-08-01/items/{$this->getSellerIdFromCredential()}/{$listing->amazon_sku}",
            array_merge($listingData, [
                'marketplaceIds' => [$this->marketplaceId],
            ]),
            $syncLog
        );

        $status = $response['status'] ?? 'ACCEPTED';

        $listing->update([
            'status' => $status === 'ACCEPTED' ? AmazonListing::STATUS_CREATED : AmazonListing::STATUS_ERROR,
            'amazon_status' => $status,
            'product_data_hash' => $listing->generateProductHash(),
            'last_sync_payload' => $listingData,
            'last_amazon_response' => $response,
            'last_synced_at' => now(),
            'consecutive_errors' => 0,
        ]);

        if ($status === 'ACCEPTED') {
            audit_event(
                action: 'listing.amazon.published',
                description: "Published {$product->sku} to Amazon",
                subject: $listing->fresh(),
                metadata: [
                    'amazon_sku' => $listing->amazon_sku,
                    'asin' => $listing->asin,
                    'marketplace_id' => $this->marketplaceId,
                    'source' => app()->runningInConsole() ? 'job' : 'web',
                ],
                severity: 'info',
                category: 'marketplace',
                organization: $product->organization,
            );
        }

        return $response;
    }

    public function updateListing(Product $product, AmazonListing $listing, bool $force = false): ?array
    {
        if (! $force && ! $listing->hasProductChanged()) {
            return null;
        }

        $syncLog = $this->createSyncLog(AmazonSyncLog::OP_UPDATE_LISTING, [
            'product_id' => $product->id,
            'amazon_listing_id' => $listing->id,
            'asin' => $listing->asin,
        ]);

        $listingData = $this->toAmazonListingData($product, $listing);

        $response = $this->patch(
            "/listings/2021-08-01/items/{$this->getSellerIdFromCredential()}/{$listing->amazon_sku}",
            array_merge($listingData, [
                'marketplaceIds' => [$this->marketplaceId],
            ]),
            $syncLog
        );

        $listing->update([
            'product_data_hash' => $listing->generateProductHash(),
            'last_sync_payload' => $listingData,
            'last_amazon_response' => $response,
            'last_synced_at' => now(),
            'consecutive_errors' => 0,
        ]);

        return $response;
    }

    public function deleteListing(AmazonListing $listing): void
    {
        $syncLog = $this->createSyncLog(AmazonSyncLog::OP_DELETE_LISTING, [
            'amazon_listing_id' => $listing->id,
            'asin' => $listing->asin,
        ]);

        $this->delete(
            "/listings/2021-08-01/items/{$this->getSellerIdFromCredential()}/{$listing->amazon_sku}?marketplaceIds={$this->marketplaceId}",
            $syncLog
        );

        $listing->update([
            'status' => AmazonListing::STATUS_DELETED,
            'last_synced_at' => now(),
        ]);
    }

    public function toAmazonListingData(Product $product, AmazonListing $listing): array
    {
        $attributes = [
            'item_name' => [['value' => $product->name, 'marketplace_id' => $this->marketplaceId]],
            'product_description' => [['value' => strip_tags($product->description ?? $product->short_description ?? $product->name), 'marketplace_id' => $this->marketplaceId]],
            'condition_type' => [['value' => $this->mapCondition($product->condition), 'marketplace_id' => $this->marketplaceId]],
            'purchasable_offer' => [[
                'currency' => $product->currency ?? 'USD',
                'our_price' => [['schedule' => [['value_with_tax' => (float) $product->selling_price]]]],
                'marketplace_id' => $this->marketplaceId,
            ]],
            'fulfillment_availability' => [[
                'fulfillment_channel_code' => $listing->fulfillment_channel,
                'quantity' => $listing->isFbm() ? ($product->available_quantity ?? $product->stock_quantity) : null,
                'marketplace_id' => $this->marketplaceId,
            ]],
        ];

        // Add identifiers
        if ($product->upc) {
            $attributes['externally_assigned_product_identifier'] = [[
                'type' => 'upc',
                'value' => $product->upc,
                'marketplace_id' => $this->marketplaceId,
            ]];
        } elseif ($product->ean) {
            $attributes['externally_assigned_product_identifier'] = [[
                'type' => 'ean',
                'value' => $product->ean,
                'marketplace_id' => $this->marketplaceId,
            ]];
        }

        // Add brand
        if ($product->brand?->name) {
            $attributes['brand'] = [['value' => $product->brand->name, 'marketplace_id' => $this->marketplaceId]];
        }

        // Add weight
        if ($product->weight) {
            $attributes['item_package_weight'] = [[
                'unit' => 'pounds',
                'value' => (float) $product->weight,
                'marketplace_id' => $this->marketplaceId,
            ]];
        }

        // Add dimensions
        if ($product->length && $product->width && $product->height) {
            $attributes['item_package_dimensions'] = [[
                'length' => ['unit' => 'inches', 'value' => (float) $product->length],
                'width' => ['unit' => 'inches', 'value' => (float) $product->width],
                'height' => ['unit' => 'inches', 'value' => (float) $product->height],
                'marketplace_id' => $this->marketplaceId,
            ]];
        }

        // Images: Amazon takes a main image plus up to 8 alt images.
        // Image URLs must be publicly reachable HTTPS.
        $imageUrls = $product->relationLoaded('images')
            ? $product->images->pluck('url')->filter()->values()
            : $product->images()->orderBy('sort_order')->pluck('url')->filter()->values();

        if ($imageUrls->isEmpty() && $product->featured_image) {
            $imageUrls = collect([$product->featured_image]);
        }

        if ($imageUrls->isNotEmpty()) {
            $attributes['main_product_image_locator'] = [[
                'media_location' => $imageUrls->first(),
                'marketplace_id' => $this->marketplaceId,
            ]];

            foreach ($imageUrls->slice(1, 8)->values() as $idx => $url) {
                $key = 'other_product_image_locator_'.($idx + 1);
                $attributes[$key] = [[
                    'media_location' => $url,
                    'marketplace_id' => $this->marketplaceId,
                ]];
            }
        }

        $data = [
            'productType' => $listing->amazon_product_type ?? 'PRODUCT',
            'requirements' => 'LISTING',
            'attributes' => $attributes,
        ];

        // Apply marketplace overrides
        $overrides = $product->marketplace_overrides['amazon'] ?? [];
        if (! empty($overrides)) {
            $data['attributes'] = array_merge($data['attributes'], $overrides);
        }

        return $data;
    }

    protected function mapCondition(?string $condition): string
    {
        return match (strtoupper($condition ?? '')) {
            'NEW', 'NEW_WITH_TAGS', 'NWT' => 'new_new',
            'LIKE_NEW', 'NEW_WITHOUT_TAGS', 'NWOT' => 'new_open_box',
            'VERY_GOOD', 'EXCELLENT' => 'used_very_good',
            'GOOD' => 'used_good',
            'ACCEPTABLE', 'FAIR' => 'used_acceptable',
            'REFURBISHED' => 'refurbished_refurbished',
            default => 'new_new',
        };
    }

    protected function getSellerIdFromCredential(): string
    {
        return $this->credential->config['amazon_seller_id'] ?? '';
    }
}
