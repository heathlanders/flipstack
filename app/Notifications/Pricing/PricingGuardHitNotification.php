<?php

namespace App\Notifications\Pricing;

use App\Notifications\BaseNotification;

class PricingGuardHitNotification extends BaseNotification
{
    public function __construct(
        protected string $ruleId,
        protected ?string $ruleName,
        protected string $productId,
        protected string $productName,
        protected string $guardType,
        protected float $proposedPrice,
        protected float $finalPrice,
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_LOW;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_FINANCIAL;
    }

    public function getTitle(): string
    {
        return ucfirst($this->guardType).' guard hit: '.$this->productName;
    }

    public function getMessage(): string
    {
        return "Repricing for {$this->productName} clamped by {$this->guardType} guard (proposed \${$this->proposedPrice} → applied \${$this->finalPrice}).";
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
            'product_id' => $this->productId,
            'guard_type' => $this->guardType,
            'proposed_price' => $this->proposedPrice,
            'final_price' => $this->finalPrice,
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
