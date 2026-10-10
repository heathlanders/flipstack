<?php

namespace App\Services\Shopify;

class ShopifyMetafieldService extends ShopifyBaseService
{
    public function getMetafields(string $ownerResource, int $ownerId, array $params = []): array
    {
        $response = $this->get("{$ownerResource}/{$ownerId}/metafields", $params);

        return $response['metafields'] ?? [];
    }

    public function setMetafield(string $ownerResource, int $ownerId, string $namespace, string $key, $value, string $type = 'single_line_text_field'): array
    {
        $response = $this->post("{$ownerResource}/{$ownerId}/metafields", [
            'metafield' => [
                'namespace' => $namespace,
                'key' => $key,
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'type' => $type,
            ],
        ]);

        return $response['metafield'] ?? [];
    }

    public function deleteMetafield(string $ownerResource, int $ownerId, int $metafieldId): void
    {
        $this->delete("{$ownerResource}/{$ownerId}/metafields/{$metafieldId}");
    }
}
