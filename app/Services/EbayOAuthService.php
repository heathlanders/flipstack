<?php

namespace App\Services;

use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayOAuthService
{
    private string $baseUrl;

    private string $apiUrl;

    private EbayConfigService $config;

    public function __construct(
        private IntegrationCredential $credential
    ) {
        // Use centralized eBay configuration
        $this->config = app(EbayConfigService::class);

        // OAuth endpoints from config
        $this->baseUrl = $this->config->isSandbox()
            ? 'https://api.sandbox.ebay.com/identity/v1/oauth2'
            : 'https://api.ebay.com/identity/v1/oauth2';

        // API endpoints from config
        $this->apiUrl = $this->config->getApiBaseUrl();
    }

    /**
     * Get the client ID for OAuth requests.
     * Uses app-level credentials from EbayConfigService.
     */
    private function getClientId(): string
    {
        return $this->config->getClientId();
    }

    /**
     * Get the client secret for OAuth requests.
     * Uses app-level credentials from EbayConfigService.
     */
    private function getClientSecret(): string
    {
        return $this->config->getClientSecret();
    }

    /**
     * Get the RuName (redirect URI) for OAuth requests.
     * Uses app-level credentials from EbayConfigService.
     */
    private function getRuName(): string
    {
        return $this->config->getRuName();
    }

    /**
     * Get the current environment.
     */
    private function getEnvironment(): string
    {
        return $this->config->getEnvironment();
    }

    /**
     * Exchange authorization code for access token.
     * Uses app-level credentials from EbayConfigService.
     */
    public function exchangeCodeForToken(string $code, ?int $userId = null): OAuthToken
    {
        $environment = $this->getEnvironment();

        $response = Http::withBasicAuth(
            $this->getClientId(),
            $this->getClientSecret()
        )->asForm()->post($this->baseUrl.'/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getRuName(),
        ]);

        if ($response->failed()) {
            Log::error('eBay OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to exchange authorization code for token');
        }

        $data = $response->json();

        // Get eBay user info if possible
        $metadata = $this->getUserInfo($data['access_token']);

        // Update credential with seller info from Identity API
        if (! empty($metadata['username'])) {
            $updates = ['seller_username' => $metadata['username']];
            if (! empty($metadata['registrationMarketplaceId'])) {
                $updates['marketplace_id'] = $metadata['registrationMarketplaceId'];
            }
            $this->credential->update($updates);
        }

        // Deactivate any existing tokens for this specific credential/user
        OAuthToken::where('integration_credential_id', $this->credential->id)
            ->where('user_id', $userId)
            ->update(['is_active' => false]);

        // Create new token
        $token = OAuthToken::create([
            'organization_id' => $this->credential->organization_id,
            'team_id' => $this->credential->team_id,
            'user_id' => $userId,
            'integration_credential_id' => $this->credential->id,
            'platform' => 'ebay',
            'environment' => $environment,
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($data['expires_in']),
            'scopes' => isset($data['scope']) ? explode(' ', $data['scope']) : null,
            'metadata' => $metadata,
            'is_active' => true,
        ]);

        Log::info('eBay OAuth token created', [
            'organization_id' => $this->credential->organization_id,
            'user_id' => $userId,
            'environment' => $environment,
            'expires_at' => $token->expires_at,
        ]);

        return $token;
    }

    /**
     * Refresh an access token.
     * Uses app-level credentials from EbayConfigService.
     */
    public function refreshToken(OAuthToken $token): OAuthToken
    {
        if (empty($token->decrypted_refresh_token)) {
            throw new \Exception('No refresh token available');
        }

        $response = Http::withBasicAuth(
            $this->getClientId(),
            $this->getClientSecret()
        )->asForm()->post($this->baseUrl.'/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->decrypted_refresh_token,
            'scope' => implode(' ', $token->scopes ?? []),
        ]);

        if ($response->failed()) {
            Log::error('eBay OAuth token refresh failed', [
                'token_id' => $token->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // If refresh fails, deactivate the token
            $token->deactivate();

            throw new \Exception('Failed to refresh access token');
        }

        $data = $response->json();

        // Update token
        $token->update([
            'access_token' => $data['access_token'],
            'expires_at' => now()->addSeconds($data['expires_in']),
            'last_refreshed_at' => now(),
        ]);

        Log::info('eBay OAuth token refreshed', [
            'token_id' => $token->id,
            'expires_at' => $token->expires_at,
        ]);

        return $token->fresh();
    }

    /**
     * Get eBay user information
     */
    private function getUserInfo(string $accessToken): array
    {
        try {
            // The Identity API uses apiz.ebay.com, not api.ebay.com
            $identityUrl = $this->config->isSandbox()
                ? 'https://apiz.sandbox.ebay.com/commerce/identity/v1/user/'
                : 'https://apiz.ebay.com/commerce/identity/v1/user/';

            $response = Http::withToken($accessToken)
                ->get($identityUrl);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('eBay Identity API returned non-success', [
                'status' => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to fetch eBay user info', [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Verify credentials by attempting to get an application token.
     * Uses app-level credentials from EbayConfigService.
     */
    public function verifyCredentials(): bool
    {
        try {
            $response = Http::withBasicAuth(
                $this->getClientId(),
                $this->getClientSecret()
            )->asForm()->post($this->baseUrl.'/token', [
                'grant_type' => 'client_credentials',
                'scope' => 'https://api.ebay.com/oauth/api_scope',
            ]);

            if ($response->successful()) {
                $this->credential->update([
                    'last_verified_at' => now(),
                ]);

                return true;
            }

            Log::error('eBay credentials verification failed', [
                'credential_id' => $this->credential->id,
                'status' => $response->status(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('eBay credentials verification error', [
                'credential_id' => $this->credential->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Make an authenticated API request.
     */
    public function apiRequest(string $method, string $endpoint, array $data = [], ?OAuthToken $token = null): array
    {
        $environment = $this->getEnvironment();

        if (! $token) {
            $token = OAuthToken::getActiveToken(
                $this->credential->organization_id,
                'ebay',
                $environment
            );

            if (! $token) {
                throw new \Exception('No active eBay token available');
            }
        }

        // Refresh if needed
        if ($token->needsRefresh()) {
            $token = $this->refreshToken($token);
        }

        $token->markAsUsed();

        $url = $this->apiUrl.$endpoint;

        $headers = [
            'Content-Language' => 'en-US',
            'Content-Type' => 'application/json',
        ];

        $response = Http::withToken($token->decrypted_access_token)
            ->withHeaders($headers)
            ->$method($url, $data);

        if ($response->failed()) {
            Log::error('eBay API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('eBay API request failed: '.$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Revoke a token.
     * Uses app-level credentials from EbayConfigService.
     */
    public function revokeToken(OAuthToken $token): bool
    {
        try {
            $response = Http::withBasicAuth(
                $this->getClientId(),
                $this->getClientSecret()
            )->asForm()->post($this->baseUrl.'/revoke', [
                'token' => $token->decrypted_access_token,
            ]);

            $token->deactivate();

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to revoke eBay token', [
                'token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
