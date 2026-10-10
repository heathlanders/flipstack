<?php

namespace App\Notifications\Inventory;

use App\Notifications\BaseNotification;

class MarketplaceInventorySyncFailedNotification extends BaseNotification
{
    public function __construct(
        protected string $productName,
        protected string $productId,
        protected string $marketplace,
        protected string $errorMessage,
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_HIGH;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_INVENTORY;
    }

    public function getTitle(): string
    {
        return ucfirst($this->marketplace).' Inventory Sync Failed';
    }

    public function getMessage(): string
    {
        return "Failed to sync inventory for \"{$this->productName}\" to {$this->marketplace}: {$this->errorMessage}";
    }

    public function getActionUrl(): ?string
    {
        return "/inventory/products/{$this->productId}";
    }

    public function getActionText(): string
    {
        return 'View Product';
    }

    protected function getData(): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'marketplace' => $this->marketplace,
        ];
    }
}
