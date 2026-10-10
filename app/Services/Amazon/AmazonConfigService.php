<?php

namespace App\Services\Amazon;

use Illuminate\Support\Facades\Log;

class AmazonConfigService
{
    public function getLwaClientId(): ?string
    {
        return config('services.amazon.lwa_client_id');
    }

    public function getLwaClientSecret(): ?string
    {
        return config('services.amazon.lwa_client_secret');
    }

    public function isSandbox(): bool
    {
        return (bool) config('services.amazon.use_sandbox', false);
    }

    public function getEnvironment(): string
    {
        return $this->isSandbox() ? 'sandbox' : 'production';
    }

    public function getSpApiEndpoint(): string
    {
        $key = "services.amazon.{$this->getEnvironment()}.sp_api_endpoint";
        $default = $this->isSandbox()
            ? 'https://sandbox.sellingpartnerapi-na.amazon.com'
            : 'https://sellingpartnerapi-na.amazon.com';

        return config($key, $default);
    }

    public function getMarketplaceId(): string
    {
        return config("services.amazon.{$this->getEnvironment()}.marketplace_id", 'ATVPDKIKX0DER');
    }

    public function getRegion(): string
    {
        return config('services.amazon.region', 'us-east-1');
    }

    public function getRedirectUri(): string
    {
        return config('services.amazon.redirect_uri') ?: url('/amazon/auth/callback');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->getLwaClientId()) && ! empty($this->getLwaClientSecret());
    }

    public function logConfigStatus(): void
    {
        Log::info('Amazon Configuration Status', [
            'configured' => $this->isConfigured(),
            'environment' => $this->getEnvironment(),
            'has_client_id' => ! empty($this->getLwaClientId()),
            'has_client_secret' => ! empty($this->getLwaClientSecret()),
            'endpoint' => $this->getSpApiEndpoint(),
            'marketplace_id' => $this->getMarketplaceId(),
        ]);
    }
}
