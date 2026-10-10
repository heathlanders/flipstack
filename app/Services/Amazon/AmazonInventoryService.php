<?php

namespace App\Services\Amazon;

use App\Models\Amazon\AmazonSyncLog;

class AmazonInventoryService extends AmazonBaseService
{
    public function updateFbmQuantity(string $sellerSku, int $quantity): array
    {
        $syncLog = $this->createSyncLog(AmazonSyncLog::OP_SET_INVENTORY, [
            'amazon_sku' => $sellerSku,
        ]);

        $sellerId = $this->credential->config['amazon_seller_id'] ?? '';

        return $this->patch(
            "/listings/2021-08-01/items/{$sellerId}/{$sellerSku}",
            [
                'productType' => 'PRODUCT',
                'patches' => [
                    [
                        'op' => 'replace',
                        'path' => '/attributes/fulfillment_availability',
                        'value' => [[
                            'fulfillment_channel_code' => 'DEFAULT',
                            'quantity' => $quantity,
                            'marketplace_id' => $this->marketplaceId,
                        ]],
                    ],
                ],
                'marketplaceIds' => [$this->marketplaceId],
            ],
            $syncLog
        );
    }

    public function getFbaInventory(array $sellerSkus = []): array
    {
        $syncLog = $this->createSyncLog(AmazonSyncLog::OP_SYNC_FBA_INVENTORY);

        $params = [
            'granularityType' => 'Marketplace',
            'granularityId' => $this->marketplaceId,
            'marketplaceIds' => $this->marketplaceId,
        ];

        if (! empty($sellerSkus)) {
            $params['sellerSkus'] = implode(',', $sellerSkus);
        }

        $response = $this->get('/fba/inventory/v1/summaries', $params, $syncLog);

        return $response['payload']['inventorySummaries'] ?? [];
    }
}
