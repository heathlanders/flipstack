<?php

namespace App\Notifications\Analytics;

use App\Notifications\BaseNotification;

class StockoutWarningNotification extends BaseNotification
{
    public function __construct(
        protected string $productId,
        protected string $productName,
        protected int $currentStock,
        protected string $stockoutDate,
        protected int $daysUntilStockout,
    ) {}

    public function getPriority(): string
    {
        return $this->daysUntilStockout <= 3 ? self::PRIORITY_HIGH : self::PRIORITY_MEDIUM;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_INVENTORY;
    }

    public function getTitle(): string
    {
        return "Stockout in {$this->daysUntilStockout}d: {$this->productName}";
    }

    public function getMessage(): string
    {
        return "{$this->productName} forecast to run out by {$this->stockoutDate} (currently {$this->currentStock} on hand).";
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
            'current_stock' => $this->currentStock,
            'stockout_date' => $this->stockoutDate,
            'days_until_stockout' => $this->daysUntilStockout,
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
