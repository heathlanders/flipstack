<?php

namespace App\Notifications\Shopify;

use App\Notifications\BaseNotification;

class ProductSyncFailedNotification extends BaseNotification
{
    public function __construct(
        protected string $productId,
        protected string $productName,
        protected string $errorMessage,
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_HIGH;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_PLATFORM;
    }

    public function getTitle(): string
    {
        return 'Shopify Product Sync Failed';
    }

    public function getMessage(): string
    {
        return "Could not sync \"{$this->productName}\" to Shopify: {$this->errorMessage}";
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
            'error' => $this->errorMessage,
        ];
    }

    public function toMail($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject($this->getTitle())
            ->line($this->getMessage())
            ->action($this->getActionText(), url($this->getActionUrl()));
    }
}
