<?php

namespace App\Notifications\Intake;

use App\Notifications\BaseNotification;

class IntakeItemFailedNotification extends BaseNotification
{
    public function __construct(
        protected string $queueItemId,
        protected ?string $title,
        protected string $errorMessage,
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_HIGH;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_INVENTORY;
    }

    public function getTitle(): string
    {
        return 'Intake Item Failed';
    }

    public function getMessage(): string
    {
        $label = $this->title ?: 'queue item';

        return "Processing failed for {$label}: {$this->errorMessage}";
    }

    public function getActionUrl(): ?string
    {
        return "/inventory/intake-queue/{$this->queueItemId}";
    }

    public function getActionText(): string
    {
        return 'View Item';
    }

    protected function getData(): array
    {
        return [
            'queue_item_id' => $this->queueItemId,
            'title' => $this->title,
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
