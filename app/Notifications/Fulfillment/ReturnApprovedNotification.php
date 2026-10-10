<?php

namespace App\Notifications\Fulfillment;

use App\Notifications\BaseNotification;

class ReturnApprovedNotification extends BaseNotification
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
        return "Return {$this->rmaNumber} approved";
    }

    public function getMessage(): string
    {
        $order = $this->orderNumber ? " for order {$this->orderNumber}" : '';

        return "Return {$this->rmaNumber}{$order} has been approved.";
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
        ];
    }
}
