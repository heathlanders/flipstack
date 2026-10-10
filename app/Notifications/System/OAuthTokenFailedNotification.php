<?php

namespace App\Notifications\System;

use App\Notifications\BaseNotification;

class OAuthTokenFailedNotification extends BaseNotification
{
    public function __construct(
        protected string $provider,
        protected string $errorMessage,
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_CRITICAL;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_SYSTEM;
    }

    public function getTitle(): string
    {
        return ucfirst($this->provider).' Authentication Failed';
    }

    public function getMessage(): string
    {
        return "Failed to refresh {$this->provider} OAuth token: {$this->errorMessage}. Marketplace sync will be disrupted until re-authenticated.";
    }

    public function getActionUrl(): ?string
    {
        return match ($this->provider) {
            'ebay' => '/marketplace/ebay',
            'shopify' => '/marketplace/shopify',
            default => '/settings',
        };
    }

    public function getActionText(): string
    {
        return 'Re-authenticate';
    }

    protected function getData(): array
    {
        return [
            'provider' => $this->provider,
        ];
    }
}
