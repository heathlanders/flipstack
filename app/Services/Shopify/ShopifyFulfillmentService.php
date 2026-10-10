<?php

namespace App\Services\Shopify;

use App\Models\Shopify\ShopifySyncLog;

class ShopifyFulfillmentService extends ShopifyBaseService
{
    public function getFulfillmentOrders(int $orderId): array
    {
        $response = $this->get("orders/{$orderId}/fulfillment_orders");

        return $response['fulfillment_orders'] ?? [];
    }

    public function createFulfillment(array $fulfillmentData): array
    {
        $syncLog = $this->createSyncLog(ShopifySyncLog::OP_FULFILL_ORDER);

        $response = $this->post('fulfillments', [
            'fulfillment' => $fulfillmentData,
        ], $syncLog);

        return $response['fulfillment'] ?? [];
    }

    public function updateTracking(int $fulfillmentId, array $trackingInfo): array
    {
        return $this->put("fulfillments/{$fulfillmentId}/update_tracking", [
            'fulfillment' => [
                'notify_customer' => $trackingInfo['notify_customer'] ?? true,
                'tracking_info' => [
                    'number' => $trackingInfo['tracking_number'] ?? null,
                    'url' => $trackingInfo['tracking_url'] ?? null,
                    'company' => $trackingInfo['tracking_company'] ?? null,
                ],
            ],
        ]);
    }
}
