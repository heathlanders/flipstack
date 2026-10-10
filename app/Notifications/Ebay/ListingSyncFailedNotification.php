<?php

namespace App\Notifications\Ebay;

use App\Notifications\BaseNotification;

class ListingSyncFailedNotification extends BaseNotification
{
    public function __construct(
        protected string $productId,
        protected string $productName,
        protected string $errorMessage,
        protected ?string $batchId = null,
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
        return 'eBay Listing Sync Failed';
    }

    public function getMessage(): string
    {
        return "Could not sync \"{$this->productName}\" to eBay: {$this->errorMessage}";
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
            'batch_id' => $this->batchId,
        ];
    }

    public function toMail($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject($this->getTitle())
            ->line($this->getMessage())
            ->action($this->getActionText(), url($this->getActionUrl()))
            ->line('Verify the product data and retry the sync.');
    }
}
