<?php

namespace App\Notifications\Customer;

use Illuminate\Notifications\Messages\MailMessage;

class ReturnApprovedToBuyer extends BaseBuyerNotification
{
    public function __construct(
        protected int $organizationId,
        protected string $rmaNumber,
        protected ?string $orderNumber,
        protected ?string $marketplace,
        protected ?string $instructions,
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

        $msg = (new MailMessage)
            ->subject("Your return is approved – RMA {$this->rmaNumber}")
            ->greeting('Hello,')
            ->line("{$orgName} has approved your return request.");

        if ($this->orderNumber) {
            $msg->line("Order: {$this->orderNumber}");
        }
        $msg->line("RMA reference: {$this->rmaNumber}");

        if ($this->instructions) {
            $msg->line($this->instructions);
        }

        return $msg->line('Please ship the item back at your earliest convenience.');
    }
}
