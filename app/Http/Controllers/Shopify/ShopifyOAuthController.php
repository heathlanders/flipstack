<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Integration\IntegrationCredential;
use App\Services\Shopify\ShopifyConfigService;
use App\Services\Shopify\ShopifyOAuthService;
use App\Services\Shopify\ShopifyWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ShopifyOAuthController extends Controller
{
    public function install(Request $request, ShopifyOAuthService $oauthService)
    {
        $request->validate([
            'shop_domain' => 'required|string|regex:/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.myshopify\.com$/',
        ]);

        $user = $request->user();
        $shopDomain = $request->shop_domain;

        // Find existing credential for this specific shop domain, or create a new one
        $credential = IntegrationCredential::where('organization_id', $user->organization_id)
            ->where('platform', 'shopify')
            ->whereJsonContains('config->shop_domain', $shopDomain)
            ->first();

        if (! $credential) {
            $existingCount = IntegrationCredential::where('organization_id', $user->organization_id)
                ->where('platform', 'shopify')
                ->count();

            $credential = IntegrationCredential::create([
                'organization_id' => $user->organization_id,
                'team_id' => $user->currentTeam?->id,
                'platform' => 'shopify',
                'name' => $existingCount > 0 ? $shopDomain : 'Shopify Store',
                'environment' => app(ShopifyConfigService::class)->getEnvironment(),
                'is_active' => true,
                'config' => ['shop_domain' => $shopDomain],
            ]);
        }

        $authUrl = $oauthService->getAuthorizationUrl($shopDomain, $credential);

        return Inertia::location($authUrl);
    }

    public function callback(Request $request, ShopifyOAuthService $oauthService)
    {
        try {
            $state = decrypt($request->state);

            if (! isset($state['organization_id']) || ! isset($state['credential_id'])) {
                throw new \Exception('Invalid state parameter');
            }

            if (isset($state['timestamp']) && now()->timestamp - $state['timestamp'] > 600) {
                throw new \Exception('OAuth state expired');
            }

            $credential = IntegrationCredential::findOrFail($state['credential_id']);
            $shopDomain = $state['shop_domain'];

            if ($request->user()->organization_id !== $credential->organization_id) {
                abort(403, 'You do not have access to this organization');
            }

            if (! $request->has('code')) {
                throw new \Exception('No authorization code received');
            }

            // Exchange code for token
            $token = $oauthService->exchangeCodeForToken(
                $shopDomain,
                $request->code,
                $credential
            );

            // Fetch shop info and store in config
            $shopInfo = $oauthService->getShopInfo($shopDomain, $token->decrypted_access_token);

            $config = $credential->config ?? [];
            $config['shop_name'] = $shopInfo['name'] ?? $shopDomain;
            $config['shop_id'] = $shopInfo['id'] ?? null;
            $config['shop_domain'] = $shopDomain;

            $credential->update([
                'config' => $config,
                'is_active' => true,
                'last_verified_at' => now(),
            ]);

            // Fetch primary location
            try {
                $baseService = new \App\Services\Shopify\ShopifyInventoryService($credential);
                $locations = $baseService->getLocations();
                if (! empty($locations)) {
                    $config['primary_location_id'] = $locations[0]['id'];
                    $credential->update(['config' => $config]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch Shopify locations', ['error' => $e->getMessage()]);
            }

            // Register webhooks
            try {
                $webhookService = new ShopifyWebhookService($credential);
                $webhookService->registerWebhooks();
            } catch (\Exception $e) {
                Log::warning('Failed to register Shopify webhooks', ['error' => $e->getMessage()]);
            }

            return redirect()->route('marketplaces.shopify.index')
                ->with('success', 'Shopify store connected successfully');

        } catch (\Exception $e) {
            Log::error('Shopify OAuth callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('marketplaces.shopify.index')
                ->withErrors(['shopify' => 'Failed to connect to Shopify: '.$e->getMessage()]);
        }
    }

    public function disconnect(Request $request)
    {
        $user = $request->user();

        // Target a specific credential if provided, otherwise fall back to first active
        if ($request->has('credential_id')) {
            $credential = IntegrationCredential::where('id', $request->input('credential_id'))
                ->where('organization_id', $user->organization_id)
                ->where('platform', 'shopify')
                ->first();
        } else {
            $credential = IntegrationCredential::where('organization_id', $user->organization_id)
                ->where('platform', 'shopify')
                ->where('is_active', true)
                ->first();
        }

        if (! $credential) {
            return back()->withErrors(['shopify' => 'No active Shopify connection found']);
        }

        // Deactivate tokens
        $credential->oauthTokens()
            ->where('platform', 'shopify')
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $credential->update(['is_active' => false]);

        return back()->with('success', 'Shopify store disconnected');
    }
}
