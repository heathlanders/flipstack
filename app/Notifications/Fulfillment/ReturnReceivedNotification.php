<?php

namespace App\Notifications\Fulfillment;

use App\Notifications\BaseNotification;

class ReturnReceivedNotification extends BaseNotification
{
    public function __construct(
        protected string $returnId,
        protected string $rmaNumber,
        protected ?string $orderNumber,
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_MEDIUM;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_ORDER;
    }

    public function getTitle(): string
    {
        return "Return {$this->rmaNumber} received";
    }

    public function getMessage(): string
    {
        $order = $this->orderNumber ? " for order {$this->orderNumber}" : '';

        return "Return package {$this->rmaNumber}{$order} arrived. Inspect to proceed.";
    }

    public function getActionUrl(): ?string
    {
        return "/fulfillment/returns/{$this->returnId}";
    }

    public function getActionText(): string
    {
        return 'Inspect Return';
    }

    protected function getData(): array
    {
        return [
            'return_id' => $this->returnId,
            'rma_number' => $this->rmaNumber,
            'order_number' => $this->orderNumber,
        ];
    }
}
