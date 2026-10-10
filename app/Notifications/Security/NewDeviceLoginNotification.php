<?php

namespace App\Notifications\Security;

use App\Notifications\BaseNotification;

class NewDeviceLoginNotification extends BaseNotification
{
    public function __construct(
        protected string $userId,
        protected string $userName,
        protected string $ipAddress,
        protected ?string $userAgent,
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_MEDIUM;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_SYSTEM;
    }

    public function getTitle(): string
    {
        return "New device login: {$this->userName}";
    }

    public function getMessage(): string
    {
        $ua = $this->userAgent ? " ({$this->userAgent})" : '';

        return "{$this->userName} signed in from a new device at {$this->ipAddress}{$ua}.";
    }

    public function getActionUrl(): ?string
    {
        return '/profile';
    }

    public function getActionText(): string
    {
        return 'Review Sessions';
    }

    protected function getData(): array
    {
        return [
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
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
