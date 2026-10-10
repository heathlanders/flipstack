<?php

namespace App\Notifications\Fulfillment;

use App\Notifications\BaseNotification;

class OrderSyncFailedNotification extends BaseNotification
{
    public function __construct(
        protected string $marketplace,
        protected string $errorMessage,
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
        return ucfirst($this->marketplace).' Order Sync Failed';
    }

    public function getMessage(): string
    {
        return "Failed to sync orders from {$this->marketplace}: {$this->errorMessage}";
    }

    public function getActionUrl(): ?string
    {
        return '/fulfillment/orders';
    }

    public function getActionText(): string
    {
        return 'View Orders';
    }

    protected function getData(): array
    {
        return [
            'marketplace' => $this->marketplace,
        ];
    }
}
