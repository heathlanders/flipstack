<?php

namespace App\Notifications\Fulfillment;

use App\Notifications\BaseNotification;

class RefundProcessedNotification extends BaseNotification
{
    public function __construct(
        protected string $returnId,
        protected string $rmaNumber,
        protected ?string $orderNumber,
        protected ?float $refundAmount,
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
        return "Refund processed for {$this->rmaNumber}";
    }

    public function getMessage(): string
    {
        $amount = $this->refundAmount !== null ? ' $'.number_format($this->refundAmount, 2) : '';
        $order = $this->orderNumber ? " ({$this->orderNumber})" : '';

        return "Refund{$amount} processed on {$this->rmaNumber}{$order}.";
    }

    public function getActionUrl(): ?string
    {
        return "/fulfillment/returns/{$this->returnId}";
    }

    public function getActionText(): string
    {
        return 'View Return';
    }

    protected function getData(): array
    {
        return [
            'return_id' => $this->returnId,
            'rma_number' => $this->rmaNumber,
            'order_number' => $this->orderNumber,
            'refund_amount' => $this->refundAmount,
        ];
    }
}
