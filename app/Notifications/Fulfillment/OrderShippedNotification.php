<?php

namespace App\Notifications\Fulfillment;

use App\Notifications\BaseNotification;

class OrderShippedNotification extends BaseNotification
{
    public function __construct(
        protected string $orderId,
        protected string $orderNumber,
        protected ?string $marketplace,
        protected ?string $trackingNumber,
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_LOW;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_ORDER;
    }

    public function getTitle(): string
    {
        return "Order {$this->orderNumber} shipped";
    }

    public function getMessage(): string
    {
        $tracking = $this->trackingNumber ? " (tracking: {$this->trackingNumber})" : '';

        return "Order {$this->orderNumber} marked shipped{$tracking}.";
    }

    public function getActionUrl(): ?string
    {
        return "/fulfillment/orders/{$this->orderId}";
    }

    public function getActionText(): string
    {
        return 'View Order';
    }

    protected function getData(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'marketplace' => $this->marketplace,
            'tracking_number' => $this->trackingNumber,
        ];
    }
}
