<?php

namespace App\Notifications\Communication;

use App\Notifications\BaseNotification;

class MessageSyncFailedNotification extends BaseNotification
{
    public function __construct(
        protected string $marketplace,
        protected string $errorMessage,
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
        return ucfirst($this->marketplace).' Message Sync Failed';
    }

    public function getMessage(): string
    {
        return "Could not sync buyer messages from {$this->marketplace}: {$this->errorMessage}";
    }

    public function getActionUrl(): ?string
    {
        return '/communication/messages';
    }

    public function getActionText(): string
    {
        return 'View Inbox';
    }

    protected function getData(): array
    {
        return [
            'marketplace' => $this->marketplace,
            'error' => $this->errorMessage,
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
