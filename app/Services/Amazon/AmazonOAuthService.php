<?php

namespace App\Services\Amazon;

use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmazonOAuthService
{
    protected AmazonConfigService $config;

    public function __construct()
    {
        $this->config = app(AmazonConfigService::class);
    }

    public function getAuthorizationUrl(IntegrationCredential $credential): string
    {
        $params = http_build_query([
            'application_id' => $this->config->getLwaClientId(),
            'redirect_uri' => $this->config->getRedirectUri(),
            'state' => encrypt([
                'organization_id' => $credential->organization_id,
                'credential_id' => $credential->id,
                'timestamp' => now()->timestamp,
            ]),
        ]);

        return "https://sellercentral.amazon.com/apps/authorize/consent?{$params}";
    }

    public function exchangeCodeForToken(string $code, IntegrationCredential $credential, ?int $userId = null): OAuthToken
    {
        $response = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->config->getLwaClientId(),
            'client_secret' => $this->config->getLwaClientSecret(),
            'redirect_uri' => $this->config->getRedirectUri(),
        ]);

        if ($response->failed()) {
            throw new AmazonApiException(
                'Failed to exchange code for token: '.$response->body(),
                $response->status()
            );
        }

        $data = $response->json();

        // Deactivate existing tokens
        OAuthToken::where('integration_credential_id', $credential->id)
            ->where('platform', 'amazon')
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $token = OAuthToken::create([
            'organization_id' => $credential->organization_id,
            'team_id' => $credential->team_id,
            'user_id' => $userId,
            'integration_credential_id' => $credential->id,
            'platform' => 'amazon',
            'environment' => 'production',
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            'scopes' => [],
            'is_active' => true,
            'last_used_at' => now(),
        ]);

        Log::info('Amazon OAuth token created', [
            'credential_id' => $credential->id,
        ]);

        return $token;
    }

    public function refreshToken(OAuthToken $token): OAuthToken
    {
        $response = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->decrypted_refresh_token,
            'client_id' => $this->config->getLwaClientId(),
            'client_secret' => $this->config->getLwaClientSecret(),
        ]);

        if ($response->failed()) {
            Log::error('Amazon token refresh failed', [
                'token_id' => $token->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new AmazonApiException(
                'Failed to refresh Amazon token: '.$response->body(),
                $response->status()
            );
        }

        $data = $response->json();

        $token->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
            'expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            'last_used_at' => now(),
        ]);

        Log::info('Amazon OAuth token refreshed', [
            'token_id' => $token->id,
            'credential_id' => $token->integration_credential_id,
        ]);

        return $token->fresh();
    }

    public function verifyCredentials(IntegrationCredential $credential): bool
    {
        $token = $credential->getActiveAmazonToken();

        if (! $token) {
            return false;
        }

        try {
            $config = app(AmazonConfigService::class);
            $endpoint = $config->getSpApiEndpoint();

            $response = Http::withHeaders([
                'x-amz-access-token' => $token->decrypted_access_token,
            ])->get("{$endpoint}/sellers/v1/marketplaceParticipations");

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Amazon credential verification failed', [
                'credential_id' => $credential->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getSellerInfo(string $accessToken): array
    {
        $config = app(AmazonConfigService::class);
        $endpoint = $config->getSpApiEndpoint();

        $response = Http::withHeaders([
            'x-amz-access-token' => $accessToken,
        ])->get("{$endpoint}/sellers/v1/marketplaceParticipations");

        if ($response->failed()) {
            throw new AmazonApiException(
                'Failed to get seller info',
                $response->status()
            );
        }

        $participations = $response->json()['payload'] ?? [];

        // Find the participation for our marketplace
        $marketplaceId = $config->getMarketplaceId();
        foreach ($participations as $participation) {
            $marketplace = $participation['marketplace'] ?? [];
            if (($marketplace['id'] ?? '') === $marketplaceId) {
                return [
                    'marketplace_id' => $marketplace['id'],
                    'marketplace_name' => $marketplace['name'] ?? 'Amazon',
                    'country_code' => $marketplace['countryCode'] ?? 'US',
                    'seller_id' => $participation['participation']['sellerId'] ?? null,
                    'is_participating' => $participation['participation']['isParticipating'] ?? false,
                ];
            }
        }

        // Return first if marketplace not found
        $first = $participations[0] ?? [];
        $marketplace = $first['marketplace'] ?? [];

        return [
            'marketplace_id' => $marketplace['id'] ?? $marketplaceId,
            'marketplace_name' => $marketplace['name'] ?? 'Amazon',
            'country_code' => $marketplace['countryCode'] ?? 'US',
            'seller_id' => $first['participation']['sellerId'] ?? null,
            'is_participating' => $first['participation']['isParticipating'] ?? false,
        ];
    }
}
