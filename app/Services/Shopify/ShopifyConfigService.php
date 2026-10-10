<?php

namespace App\Services\Shopify;

use Illuminate\Support\Facades\Log;

class ShopifyConfigService
{
    public function getEnvironment(): string
    {
        return config('services.shopify.use_sandbox') ? 'sandbox' : 'production';
    }

    public function isSandbox(): bool
    {
        return $this->getEnvironment() === 'sandbox';
    }

    public function getApiKey(): ?string
    {
        $env = $this->getEnvironment();

        return config("services.shopify.{$env}.api_key")
            ?: config('services.shopify.api_key');
    }

    public function getApiSecret(): ?string
    {
        $env = $this->getEnvironment();

        return config("services.shopify.{$env}.api_secret")
            ?: config('services.shopify.api_secret');
    }

    public function getApiVersion(): string
    {
        return config('services.shopify.api_version', '2024-10');
    }

    public function getScopes(): array
    {
        return explode(',', config('services.shopify.scopes', 'read_products,write_products,read_inventory,write_inventory,read_orders,read_locations,write_fulfillments'));
    }

    public function getScopesString(): string
    {
        return implode(',', $this->getScopes());
    }

    public function getRedirectUri(): string
    {
        return config('services.shopify.redirect_uri', url('/shopify/auth/callback'));
    }

    public function isConfigured(): bool
    {
        return ! empty($this->getApiKey()) && ! empty($this->getApiSecret());
    }

    public function logConfigStatus(): void
    {
        Log::info('Shopify Configuration Status', [
            'environment' => $this->getEnvironment(),
            'configured' => $this->isConfigured(),
            'has_api_key' => ! empty($this->getApiKey()),
            'has_api_secret' => ! empty($this->getApiSecret()),
            'api_version' => $this->getApiVersion(),
        ]);
    }
}
