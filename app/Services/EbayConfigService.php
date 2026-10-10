<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Centralized service for eBay app-level configuration.
 *
 * This service provides eBay OAuth credentials from the application's
 * environment configuration, so users don't need to enter their own
 * client IDs and secrets. They just click "Connect to eBay".
 */
class EbayConfigService
{
    /**
     * Get the current eBay environment (sandbox or production).
     *
     * Automatically determined based on APP_ENV unless overridden
     * with EBAY_USE_SANDBOX.
     */
    public function getEnvironment(): string
    {
        return config('services.ebay.use_sandbox') ? 'sandbox' : 'production';
    }

    /**
     * Check if using sandbox environment.
     */
    public function isSandbox(): bool
    {
        return $this->getEnvironment() === 'sandbox';
    }

    /**
     * Get the eBay Client ID (App ID) for the current environment.
     */
    public function getClientId(): ?string
    {
        $env = $this->getEnvironment();

        return config("services.ebay.{$env}.client_id");
    }

    /**
     * Get the eBay Client Secret (Cert ID) for the current environment.
     */
    public function getClientSecret(): ?string
    {
        $env = $this->getEnvironment();

        return config("services.ebay.{$env}.client_secret");
    }

    /**
     * Get the eBay RuName (Redirect URL Name) for the current environment.
     */
    public function getRuName(): ?string
    {
        $env = $this->getEnvironment();

        return config("services.ebay.{$env}.ru_name");
    }

    /**
     * Get the eBay OAuth authorization base URL for the current environment.
     */
    public function getAuthBaseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://auth.sandbox.ebay.com/oauth2/authorize'
            : 'https://auth.ebay.com/oauth2/authorize';
    }

    /**
     * Get the eBay OAuth token endpoint URL for the current environment.
     */
    public function getOAuthTokenUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api.sandbox.ebay.com/identity/v1/oauth2/token'
            : 'https://api.ebay.com/identity/v1/oauth2/token';
    }

    /**
     * Get the eBay API base URL for the current environment.
     */
    public function getApiBaseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api.sandbox.ebay.com'
            : 'https://api.ebay.com';
    }

    /**
     * Check if app-level eBay credentials are configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->getClientId())
            && ! empty($this->getClientSecret())
            && ! empty($this->getRuName());
    }

    /**
     * Get the standard OAuth scopes for eBay seller integration.
     */
    public function getOAuthScopes(): array
    {
        return [
            'https://api.ebay.com/oauth/api_scope/sell.inventory',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
            'https://api.ebay.com/oauth/api_scope/sell.account',
            'https://api.ebay.com/oauth/api_scope/sell.marketing',
            'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.finances',
            'https://api.ebay.com/oauth/api_scope/sell.payment.dispute',
            'https://api.ebay.com/oauth/api_scope/commerce.identity.readonly',
        ];
    }

    /**
     * Get OAuth scopes as a space-separated string.
     */
    public function getOAuthScopesString(): string
    {
        return implode(' ', $this->getOAuthScopes());
    }

    /**
     * Log current configuration status (for debugging).
     */
    public function logConfigStatus(): void
    {
        Log::info('eBay Configuration Status', [
            'environment' => $this->getEnvironment(),
            'configured' => $this->isConfigured(),
            'has_client_id' => ! empty($this->getClientId()),
            'has_client_secret' => ! empty($this->getClientSecret()),
            'has_ru_name' => ! empty($this->getRuName()),
        ]);
    }
}
