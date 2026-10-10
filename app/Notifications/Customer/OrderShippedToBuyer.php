<?php

namespace App\Notifications\Customer;

use Illuminate\Notifications\Messages\MailMessage;

class OrderShippedToBuyer extends BaseBuyerNotification
{
    public function __construct(
        protected int $organizationId,
        protected string $orderId,
        protected string $orderNumber,
        protected ?string $marketplace,
        protected ?string $trackingNumber,
        protected ?string $trackingUrl,
        protected ?string $carrier,
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
            ->subject("Your order {$this->orderNumber} has shipped")
            ->greeting('Good news!')
            ->line("Your order {$this->orderNumber} from {$orgName} is on its way.");

        if ($this->trackingNumber) {
            $line = "Tracking: {$this->trackingNumber}";
            if ($this->carrier) {
                $line = "{$this->carrier} – {$line}";
            }
            $msg->line($line);
        }

        if ($this->trackingUrl) {
            $msg->action('Track Shipment', $this->trackingUrl);
        }

        return $msg->line('Thank you for your purchase.');
    }
}
