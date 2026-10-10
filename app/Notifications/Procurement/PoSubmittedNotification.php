<?php

namespace App\Notifications\Procurement;

use App\Notifications\BaseNotification;

class PoSubmittedNotification extends BaseNotification
{
    public function __construct(
        protected string $poId,
        protected string $poNumber,
        protected ?string $supplierName,
        protected float $totalAmount,
        protected ?string $approverName,
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
        return "PO {$this->poNumber} submitted";
    }

    public function getMessage(): string
    {
        $approver = $this->approverName ? " (approved by {$this->approverName})" : '';
        $supplier = $this->supplierName ? " to {$this->supplierName}" : '';

        return "Purchase order {$this->poNumber} submitted{$supplier}{$approver}.";
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
            'approver_name' => $this->approverName,
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
