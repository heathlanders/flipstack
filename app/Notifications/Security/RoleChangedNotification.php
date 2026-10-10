<?php

namespace App\Notifications\Security;

use App\Notifications\BaseNotification;

class RoleChangedNotification extends BaseNotification
{
    public function __construct(
        protected string $targetUserName,
        protected string $action,
        protected string $roleName,
        protected ?string $actorName,
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
        return 'Role change: '.$this->targetUserName;
    }

    public function getMessage(): string
    {
        $actor = $this->actorName ? " by {$this->actorName}" : '';
        $verb = $this->action === 'assigned' ? 'granted' : 'removed';

        return "{$this->targetUserName} was {$verb} the \"{$this->roleName}\" role{$actor}.";
    }

    public function getActionUrl(): ?string
    {
        return '/users';
    }

    public function getActionText(): string
    {
        return 'View Users';
    }

    protected function getData(): array
    {
        return [
            'target_user_name' => $this->targetUserName,
            'action' => $this->action,
            'role_name' => $this->roleName,
            'actor_name' => $this->actorName,
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
