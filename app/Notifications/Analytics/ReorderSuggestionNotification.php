<?php

namespace App\Notifications\Analytics;

use App\Notifications\BaseNotification;

class ReorderSuggestionNotification extends BaseNotification
{
    public function __construct(
        protected int $suggestionCount,
        protected int $highPriorityCount,
    ) {}

    public function getPriority(): string
    {
        return $this->highPriorityCount > 0 ? self::PRIORITY_MEDIUM : self::PRIORITY_LOW;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_INVENTORY;
    }

    public function getTitle(): string
    {
        return "{$this->suggestionCount} reorder suggestions";
    }

    public function getMessage(): string
    {
        $high = $this->highPriorityCount > 0
            ? " ({$this->highPriorityCount} high priority)"
            : '';

        return "Demand forecast generated {$this->suggestionCount} reorder suggestions{$high}. Review before next PO run.";
    }

    public function getActionUrl(): ?string
    {
        return '/analytics/forecast';
    }

    public function getActionText(): string
    {
        return 'Review Suggestions';
    }

    protected function getData(): array
    {
        return [
            'suggestion_count' => $this->suggestionCount,
            'high_priority_count' => $this->highPriorityCount,
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
