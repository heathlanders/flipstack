<?php

namespace App\Services\Shopify;

class ShopifyOrderService extends ShopifyBaseService
{
    public function getOrders(array $params = []): array
    {
        return $this->paginateAll('orders', 'orders', $params);
    }

    public function getOrder(int $orderId): array
    {
        $response = $this->get("orders/{$orderId}");

        return $response['order'] ?? [];
    }

    public function getOrderCount(array $params = []): int
    {
        $response = $this->get('orders/count', $params);

        return $response['count'] ?? 0;
    }

    public function closeOrder(int $orderId): array
    {
        $response = $this->post("orders/{$orderId}/close");

        return $response['order'] ?? [];
    }

    public function cancelOrder(int $orderId, array $params = []): array
    {
        $response = $this->post("orders/{$orderId}/cancel", $params);

        return $response['order'] ?? [];
    }

    public function createRefund(int $orderId, array $refundData): array
    {
        $response = $this->post("orders/{$orderId}/refunds", [
            'refund' => $refundData,
        ]);

        return $response['refund'] ?? [];
    }

    public function calculateRefund(int $orderId, array $params = []): array
    {
        $response = $this->post("orders/{$orderId}/refunds/calculate", $params);

        return $response['refund'] ?? [];
    }

    public function getTransactions(int $orderId): array
    {
        $response = $this->get("orders/{$orderId}/transactions");

        return $response['transactions'] ?? [];
    }
}
