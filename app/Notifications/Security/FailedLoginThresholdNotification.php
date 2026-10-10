<?php

namespace App\Notifications\Security;

use App\Notifications\BaseNotification;

class FailedLoginThresholdNotification extends BaseNotification
{
    public function __construct(
        protected string $email,
        protected string $ipAddress,
        protected int $attemptCount,
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_HIGH;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_SYSTEM;
    }

    public function getTitle(): string
    {
        return 'Repeated failed logins blocked';
    }

    public function getMessage(): string
    {
        return "{$this->attemptCount} failed login attempts for {$this->email} from {$this->ipAddress}. Account temporarily locked.";
    }

    public function getActionUrl(): ?string
    {
        return '/users';
    }

    public function getActionText(): string
    {
        return 'Review Activity';
    }

    protected function getData(): array
    {
        return [
            'email' => $this->email,
            'ip_address' => $this->ipAddress,
            'attempt_count' => $this->attemptCount,
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
