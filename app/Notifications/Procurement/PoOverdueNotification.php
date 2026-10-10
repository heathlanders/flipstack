<?php

namespace App\Notifications\Procurement;

use App\Notifications\BaseNotification;

class PoOverdueNotification extends BaseNotification
{
    public function __construct(
        protected string $poId,
        protected string $poNumber,
        protected ?string $supplierName,
        protected string $expectedDeliveryDate,
        protected int $daysLate,
    ) {}

    public function getPriority(): string
    {
        return $this->daysLate >= 7 ? self::PRIORITY_HIGH : self::PRIORITY_MEDIUM;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_FINANCIAL;
    }

    public function getTitle(): string
    {
        return "PO {$this->poNumber} overdue ({$this->daysLate}d late)";
    }

    public function getMessage(): string
    {
        $supplier = $this->supplierName ? " from {$this->supplierName}" : '';

        return "Purchase order {$this->poNumber}{$supplier} was expected on {$this->expectedDeliveryDate} ({$this->daysLate} days ago).";
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
            'expected_delivery_date' => $this->expectedDeliveryDate,
            'days_late' => $this->daysLate,
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
