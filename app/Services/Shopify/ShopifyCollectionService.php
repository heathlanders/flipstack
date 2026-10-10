<?php

namespace App\Services\Shopify;

use Illuminate\Support\Facades\Cache;

class ShopifyCollectionService extends ShopifyBaseService
{
    public function getCustomCollections(array $params = []): array
    {
        return $this->paginateAll('custom_collections', 'custom_collections', $params);
    }

    public function getSmartCollections(array $params = []): array
    {
        return $this->paginateAll('smart_collections', 'smart_collections', $params);
    }

    public function createCustomCollection(string $title, array $rules = []): array
    {
        $response = $this->post('custom_collections', [
            'custom_collection' => array_merge(['title' => $title], $rules),
        ]);

        return $response['custom_collection'] ?? [];
    }

    public function addProductToCollection(int $collectionId, int $productId): array
    {
        $response = $this->post('collects', [
            'collect' => [
                'collection_id' => $collectionId,
                'product_id' => $productId,
            ],
        ]);

        return $response['collect'] ?? [];
    }

    public function getCollectsForProduct(int $shopifyProductId): array
    {
        return $this->paginateAll('collects', 'collects', [
            'product_id' => $shopifyProductId,
        ]);
    }

    public function removeCollect(int $collectId): void
    {
        $this->delete("collects/{$collectId}");
    }

    public function getAllCollections(bool $bustCache = false): array
    {
        $cacheKey = "shopify_collections:{$this->credential->id}";

        if ($bustCache) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addMinutes(15), function () {
            $custom = $this->getCustomCollections();
            $smart = $this->getSmartCollections();

            $collections = [];

            foreach ($custom as $c) {
                $collections[] = [
                    'id' => $c['id'],
                    'title' => $c['title'],
                    'type' => 'custom',
                ];
            }

            foreach ($smart as $c) {
                $collections[] = [
                    'id' => $c['id'],
                    'title' => $c['title'],
                    'type' => 'smart',
                ];
            }

            usort($collections, fn ($a, $b) => strcasecmp($a['title'], $b['title']));

            return $collections;
        });
    }
}
