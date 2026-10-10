<?php

namespace App\Notifications\Fulfillment;

use App\Notifications\BaseNotification;

class TrackingUploadFailedNotification extends BaseNotification
{
    public function __construct(
        protected string $marketplace,
        protected string $orderNumber,
        protected string $errorMessage,
        protected ?int $orderId = null,
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
        return ucfirst($this->marketplace).' Tracking Upload Failed';
    }

    public function getMessage(): string
    {
        return "Failed to upload tracking for order {$this->orderNumber} to {$this->marketplace}: {$this->errorMessage}";
    }

    public function getActionUrl(): ?string
    {
        if ($this->orderId) {
            return "/fulfillment/orders/{$this->orderId}";
        }

        return '/fulfillment/orders';
    }

    public function getActionText(): string
    {
        return 'View Order';
    }

    protected function getData(): array
    {
        return [
            'marketplace' => $this->marketplace,
            'order_number' => $this->orderNumber,
            'order_id' => $this->orderId,
        ];
    }
}
