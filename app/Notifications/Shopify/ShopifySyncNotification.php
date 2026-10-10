<?php

namespace App\Notifications\Shopify;

use App\Notifications\BaseNotification;

class ShopifySyncNotification extends BaseNotification
{
    public function __construct(
        protected string $action,
        protected bool $success,
        protected string $detail,
        protected ?string $productName = null,
        protected ?string $productId = null,
    ) {}

    public function getPriority(): string
    {
        return $this->success ? self::PRIORITY_MEDIUM : self::PRIORITY_HIGH;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_PLATFORM;
    }

    public function getTitle(): string
    {
        $label = match ($this->action) {
            'product_sync' => 'Shopify Product Sync',
            'inventory_sync' => 'Shopify Inventory Sync',
            'import' => 'Shopify Import',
            default => 'Shopify Sync',
        };

        return $this->success ? "{$label} Completed" : "{$label} Failed";
    }

    public function getMessage(): string
    {
        return $this->detail;
    }

    public function getActionUrl(): ?string
    {
        if ($this->productId) {
            return "/inventory/products/{$this->productId}";
        }

        return '/marketplace/shopify';
    }

    public function getActionText(): string
    {
        return $this->productId ? 'View Product' : 'View Shopify';
    }

    protected function getData(): array
    {
        return array_filter([
            'action' => $this->action,
            'product_name' => $this->productName,
            'product_id' => $this->productId,
        ]);
    }
}
