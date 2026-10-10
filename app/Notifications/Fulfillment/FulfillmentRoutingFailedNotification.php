<?php

namespace App\Notifications\Fulfillment;

use App\Notifications\BaseNotification;

class FulfillmentRoutingFailedNotification extends BaseNotification
{
    public function __construct(
        protected string $orderId,
        protected string $orderNumber,
        protected string $reason,
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_HIGH;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_ORDER;
    }

    public function getTitle(): string
    {
        return "Routing failed: {$this->orderNumber}";
    }

    public function getMessage(): string
    {
        return "Could not auto-route order {$this->orderNumber}: {$this->reason}. Manual location assignment required.";
    }

    public function getActionUrl(): ?string
    {
        return "/fulfillment/orders/{$this->orderId}";
    }

    public function getActionText(): string
    {
        return 'Assign Location';
    }

    protected function getData(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'reason' => $this->reason,
        ];
    }
}
