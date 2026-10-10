<?php

namespace App\Notifications\Communication;

use App\Models\Communication\MarketplaceMessage;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

class NewMessageNotification extends BaseNotification
{
    public function __construct(
        public MarketplaceMessage $marketplaceMessage
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_HIGH;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_CUSTOMER;
    }

    public function getTitle(): string
    {
        return sprintf(
            'New %s message from %s',
            ucfirst($this->marketplaceMessage->marketplace),
            $this->marketplaceMessage->sender_name ?: 'a buyer'
        );
    }

    public function getMessage(): string
    {
        $subject = $this->marketplaceMessage->subject;
        $body = $this->marketplaceMessage->body;

        if ($subject) {
            return mb_strlen($body) > 100
                ? $subject.' - '.mb_substr($body, 0, 100).'...'
                : $subject.' - '.$body;
        }

        return mb_strlen($body) > 150
            ? mb_substr($body, 0, 150).'...'
            : $body;
    }

    public function getActionUrl(): ?string
    {
        return route('messages.thread', $this->marketplaceMessage->id);
    }

    public function getActionText(): string
    {
        return 'View Message';
    }

    protected function getData(): array
    {
        return [
            'message_id' => $this->marketplaceMessage->id,
            'marketplace' => $this->marketplaceMessage->marketplace,
            'sender_name' => $this->marketplaceMessage->sender_name,
            'subject' => $this->marketplaceMessage->subject,
            'product_id' => $this->marketplaceMessage->product_id,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $msg = $this->marketplaceMessage;
        $marketplace = ucfirst($msg->marketplace);

        $mail = (new MailMessage)
            ->subject("New {$marketplace} message from {$msg->sender_name}")
            ->greeting("New buyer message on {$marketplace}")
            ->line("**From:** {$msg->sender_name}")
            ->line("**Subject:** {$msg->subject}");

        if ($msg->body) {
            $preview = mb_strlen($msg->body) > 300
                ? mb_substr($msg->body, 0, 300).'...'
                : $msg->body;
            $mail->line("**Message:** {$preview}");
        }

        return $mail
            ->action('Reply on FlipStack', $this->getActionUrl())
            ->line('Reply promptly to keep your response time low.');
    }
}
