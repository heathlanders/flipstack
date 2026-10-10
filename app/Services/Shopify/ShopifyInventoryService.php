<?php

namespace App\Services\Shopify;

use App\Models\Shopify\ShopifySyncLog;

class ShopifyInventoryService extends ShopifyBaseService
{
    public function getLocations(): array
    {
        $syncLog = $this->createSyncLog(ShopifySyncLog::OP_GET_LOCATIONS);

        $response = $this->get('locations', [], $syncLog);

        return $response['locations'] ?? [];
    }

    public function getInventoryLevels(int $locationId, array $inventoryItemIds = []): array
    {
        $params = ['location_ids' => $locationId];

        if (! empty($inventoryItemIds)) {
            $params['inventory_item_ids'] = implode(',', $inventoryItemIds);
        }

        $response = $this->get('inventory_levels', $params);

        return $response['inventory_levels'] ?? [];
    }

    public function setInventoryLevel(int $locationId, int $inventoryItemId, int $available): array
    {
        $syncLog = $this->createSyncLog(ShopifySyncLog::OP_SET_INVENTORY, [
            'shopify_variant_id' => $inventoryItemId,
        ]);

        return $this->post('inventory_levels/set', [
            'location_id' => $locationId,
            'inventory_item_id' => $inventoryItemId,
            'available' => $available,
        ], $syncLog);
    }

    public function adjustInventoryLevel(int $locationId, int $inventoryItemId, int $adjustment): array
    {
        $syncLog = $this->createSyncLog(ShopifySyncLog::OP_ADJUST_INVENTORY, [
            'shopify_variant_id' => $inventoryItemId,
        ]);

        return $this->post('inventory_levels/adjust', [
            'location_id' => $locationId,
            'inventory_item_id' => $inventoryItemId,
            'available_adjustment' => $adjustment,
        ], $syncLog);
    }

    public function getInventoryItem(int $inventoryItemId): array
    {
        $response = $this->get("inventory_items/{$inventoryItemId}");

        return $response['inventory_item'] ?? [];
    }
}
