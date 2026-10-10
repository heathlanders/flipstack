<?php

namespace App\Services\Amazon;

use App\Models\Amazon\AmazonSyncLog;

class AmazonOrderService extends AmazonBaseService
{
    public function getOrders(string $createdAfter, array $orderStatuses = ['Unshipped', 'PartiallyShipped']): array
    {
        $syncLog = $this->createSyncLog(AmazonSyncLog::OP_GET_ORDERS);

        $params = [
            'MarketplaceIds' => $this->marketplaceId,
            'CreatedAfter' => $createdAfter,
            'OrderStatuses' => implode(',', $orderStatuses),
        ];

        $response = $this->get('/orders/v0/orders', $params, $syncLog);

        return $response['payload']['Orders'] ?? [];
    }

    public function getOrderItems(string $orderId): array
    {
        $syncLog = $this->createSyncLog(AmazonSyncLog::OP_GET_ORDERS, [
            'batch_id' => $orderId,
        ]);

        $response = $this->get("/orders/v0/orders/{$orderId}/orderItems", [], $syncLog);

        return $response['payload']['OrderItems'] ?? [];
    }

    public function acknowledgeOrder(string $orderId): array
    {
        return $this->post("/orders/v0/orders/{$orderId}/acknowledgement", [
            'acknowledgementStatus' => 'SUCCESS',
        ]);
    }

    public function authorizeReturn(string $orderId, array $returnData): array
    {
        return $this->post("/orders/v0/orders/{$orderId}/return/authorize", $returnData);
    }

    /**
     * Pull seller-facing financial events for an order. Real fees (Referral,
     * FBA, closing, etc.) only become available 5 min - 24h after the order
     * is placed via this endpoint, NOT via /orders/v0/orders.
     *
     * @return array shape: ['ShipmentEventList' => [...], 'RefundEventList' => [...], ...]
     */
    public function getOrderFinancialEvents(string $orderId): array
    {
        $syncLog = $this->createSyncLog(AmazonSyncLog::OP_GET_ORDERS, [
            'batch_id' => $orderId,
        ]);

        $response = $this->get("/finances/v0/orders/{$orderId}/financialEvents", [], $syncLog);

        return $response['payload']['FinancialEvents'] ?? [];
    }
}
