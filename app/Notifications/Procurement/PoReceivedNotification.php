<?php

namespace App\Notifications\Procurement;

use App\Notifications\BaseNotification;

class PoReceivedNotification extends BaseNotification
{
    public function __construct(
        protected string $poId,
        protected string $poNumber,
        protected bool $fullyReceived,
        protected int $itemsReceived,
    ) {}

    public function getPriority(): string
    {
        return $this->fullyReceived ? self::PRIORITY_MEDIUM : self::PRIORITY_LOW;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_INVENTORY;
    }

    public function getTitle(): string
    {
        return $this->fullyReceived
            ? "PO {$this->poNumber} fully received"
            : "PO {$this->poNumber} partially received";
    }

    public function getMessage(): string
    {
        return $this->fullyReceived
            ? "Purchase order {$this->poNumber} has been fully received ({$this->itemsReceived} items)."
            : "Purchase order {$this->poNumber} partially received: {$this->itemsReceived} items.";
    }

    public function getActionUrl(): ?string
    {
        return "/procurement/purchase-orders/{$this->poId}";
    }

    public function getActionText(): string
    {
        return 'View PO';
    }

    protected function getData(): array
    {
        return [
            'po_id' => $this->poId,
            'po_number' => $this->poNumber,
            'fully_received' => $this->fullyReceived,
            'items_received' => $this->itemsReceived,
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
