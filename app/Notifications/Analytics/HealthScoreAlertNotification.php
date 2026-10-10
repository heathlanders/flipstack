<?php

namespace App\Notifications\Analytics;

use App\Notifications\BaseNotification;

class HealthScoreAlertNotification extends BaseNotification
{
    public function __construct(
        protected string $dimension,
        protected float $score,
        protected string $level,
        protected string $alertMessage,
    ) {}

    public function getPriority(): string
    {
        return $this->level === 'critical' ? self::PRIORITY_HIGH : self::PRIORITY_MEDIUM;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_FINANCIAL;
    }

    public function getTitle(): string
    {
        return ucfirst($this->dimension).' health '.($this->level === 'critical' ? 'critical' : 'warning');
    }

    public function getMessage(): string
    {
        return $this->alertMessage;
    }

    public function getActionUrl(): ?string
    {
        return '/analytics/health';
    }

    public function getActionText(): string
    {
        return 'View Dashboard';
    }

    protected function getData(): array
    {
        return [
            'dimension' => $this->dimension,
            'score' => $this->score,
            'level' => $this->level,
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
