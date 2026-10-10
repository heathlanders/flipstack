<?php

namespace App\Notifications\Pricing;

use App\Notifications\BaseNotification;

class RepricingRuleErrorNotification extends BaseNotification
{
    public function __construct(
        protected string $ruleId,
        protected ?string $ruleName,
        protected string $errorMessage,
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_HIGH;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_FINANCIAL;
    }

    public function getTitle(): string
    {
        return 'Repricing Rule Error';
    }

    public function getMessage(): string
    {
        $label = $this->ruleName ?: "rule {$this->ruleId}";

        return "Repricing {$label} failed: {$this->errorMessage}";
    }

    public function getActionUrl(): ?string
    {
        return "/pricing/repricing/rules/{$this->ruleId}";
    }

    public function getActionText(): string
    {
        return 'View Rule';
    }

    protected function getData(): array
    {
        return [
            'rule_id' => $this->ruleId,
            'rule_name' => $this->ruleName,
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
