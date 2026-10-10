<?php

namespace App\Notifications\Procurement;

use App\Notifications\BaseNotification;

class PoCreatedNotification extends BaseNotification
{
    public function __construct(
        protected string $poId,
        protected string $poNumber,
        protected ?string $supplierName,
        protected float $totalAmount,
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_MEDIUM;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_FINANCIAL;
    }

    public function getTitle(): string
    {
        return "PO {$this->poNumber} created";
    }

    public function getMessage(): string
    {
        $supplier = $this->supplierName ? " for {$this->supplierName}" : '';

        return "Purchase order {$this->poNumber}{$supplier} created. Total: \$".number_format($this->totalAmount, 2);
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
            'supplier_name' => $this->supplierName,
            'total_amount' => $this->totalAmount,
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
