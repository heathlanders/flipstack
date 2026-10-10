<?php

namespace App\Services\Shopify;

use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyOAuthService
{
    protected ShopifyConfigService $config;

    public function __construct()
    {
        $this->config = app(ShopifyConfigService::class);
    }

    public function getAuthorizationUrl(string $shopDomain, IntegrationCredential $credential): string
    {
        $params = http_build_query([
            'client_id' => $this->config->getApiKey(),
            'scope' => $this->config->getScopesString(),
            'redirect_uri' => $this->config->getRedirectUri(),
            'state' => encrypt([
                'organization_id' => $credential->organization_id,
                'credential_id' => $credential->id,
                'shop_domain' => $shopDomain,
                'timestamp' => now()->timestamp,
            ]),
        ]);

        return "https://{$shopDomain}/admin/oauth/authorize?{$params}";
    }

    public function exchangeCodeForToken(string $shopDomain, string $code, IntegrationCredential $credential, ?int $userId = null): OAuthToken
    {
        $response = Http::post("https://{$shopDomain}/admin/oauth/access_token", [
            'client_id' => $this->config->getApiKey(),
            'client_secret' => $this->config->getApiSecret(),
            'code' => $code,
        ]);

        if ($response->failed()) {
            throw new ShopifyApiException(
                'Failed to exchange code for token: '.$response->body(),
                $response->status()
            );
        }

        $data = $response->json();
        $accessToken = $data['access_token'];
        $scope = $data['scope'] ?? '';

        // Deactivate existing tokens for this shop
        OAuthToken::where('integration_credential_id', $credential->id)
            ->where('platform', 'shopify')
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Create new token (Shopify offline tokens don't expire)
        $token = OAuthToken::create([
            'organization_id' => $credential->organization_id,
            'team_id' => $credential->team_id,
            'user_id' => $userId,
            'integration_credential_id' => $credential->id,
            'platform' => 'shopify',
            'environment' => $this->config->getEnvironment(),
            'access_token' => $accessToken,
            'refresh_token' => null, // Shopify offline tokens don't need refresh
            'expires_at' => null, // Shopify offline tokens don't expire
            'scopes' => explode(',', $scope),
            'is_active' => true,
            'last_used_at' => now(),
        ]);

        Log::info('Shopify OAuth token created', [
            'credential_id' => $credential->id,
            'shop_domain' => $shopDomain,
        ]);

        return $token;
    }

    public function verifyWebhook(string $data, string $hmacHeader): bool
    {
        $secret = $this->config->getApiSecret();
        $calculatedHmac = base64_encode(hash_hmac('sha256', $data, $secret, true));

        return hash_equals($calculatedHmac, $hmacHeader);
    }

    public function verifyCredentials(IntegrationCredential $credential): bool
    {
        $config = $credential->config ?? [];
        $shopDomain = $config['shop_domain'] ?? '';
        $token = $credential->getActiveShopifyToken();

        if (! $token || ! $shopDomain) {
            return false;
        }

        try {
            $apiVersion = $this->config->getApiVersion();
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token->decrypted_access_token,
            ])->get("https://{$shopDomain}/admin/api/{$apiVersion}/shop.json");

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Shopify credential verification failed', [
                'credential_id' => $credential->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getShopInfo(string $shopDomain, string $accessToken): array
    {
        $apiVersion = $this->config->getApiVersion();

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
        ])->get("https://{$shopDomain}/admin/api/{$apiVersion}/shop.json");

        if ($response->failed()) {
            throw new ShopifyApiException(
                'Failed to get shop info',
                $response->status()
            );
        }

        return $response->json()['shop'] ?? [];
    }
}
