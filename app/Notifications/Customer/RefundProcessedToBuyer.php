<?php

namespace App\Notifications\Customer;

use Illuminate\Notifications\Messages\MailMessage;

class RefundProcessedToBuyer extends BaseBuyerNotification
{
    public function __construct(
        protected int $organizationId,
        protected string $rmaNumber,
        protected ?string $orderNumber,
        protected ?float $refundAmount,
        protected ?string $marketplace,
    ) {}

    public function getOrganizationId(): int
    {
        return $this->organizationId;
    }

    public function getMarketplace(): ?string
    {
        return $this->marketplace;
    }

    public function toMail($notifiable): MailMessage
    {
        $org = $this->organization();
        $orgName = $org?->name ?? 'Your seller';
        $amount = $this->refundAmount !== null ? '$'.number_format($this->refundAmount, 2) : 'Your refund';

        $msg = (new MailMessage)
            ->subject("Refund processed: {$amount}")
            ->greeting('Hello,')
            ->line("{$orgName} has processed your refund.");

        if ($this->orderNumber) {
            $msg->line("Order: {$this->orderNumber}");
        }
        $msg->line("Return reference: {$this->rmaNumber}");

        if ($this->refundAmount !== null) {
            $msg->line("Refund amount: \${$this->refundAmount}");
        }

        return $msg->line('It may take 3-10 business days to appear on your original payment method.');
    }
}
