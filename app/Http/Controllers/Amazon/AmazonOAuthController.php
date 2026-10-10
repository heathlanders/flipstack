<?php

namespace App\Http\Controllers\Amazon;

use App\Http\Controllers\Controller;
use App\Jobs\Amazon\RegisterAmazonNotifications;
use App\Models\Integration\IntegrationCredential;
use App\Services\Amazon\AmazonConfigService;
use App\Services\Amazon\AmazonOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AmazonOAuthController extends Controller
{
    public function install(Request $request, AmazonOAuthService $oauthService, AmazonConfigService $config)
    {
        if (! $config->isConfigured()) {
            return redirect()->route('marketplaces.amazon.index')
                ->withErrors(['amazon' => 'Amazon SP-API is not configured. Set AMAZON_LWA_CLIENT_ID and AMAZON_LWA_CLIENT_SECRET.']);
        }

        $user = $request->user();

        $credential = IntegrationCredential::where('organization_id', $user->organization_id)
            ->where('platform', 'amazon')
            ->where('is_active', true)
            ->first();

        if (! $credential) {
            $credential = IntegrationCredential::create([
                'organization_id' => $user->organization_id,
                'team_id' => $user->currentTeam?->id,
                'platform' => 'amazon',
                'name' => 'Amazon Seller',
                'environment' => $config->getEnvironment(),
                'is_active' => true,
                'config' => [
                    'amazon_marketplace_id' => $config->getMarketplaceId(),
                ],
            ]);
        }

        return redirect()->away($oauthService->getAuthorizationUrl($credential));
    }

    public function callback(Request $request, AmazonOAuthService $oauthService)
    {
        try {
            if (! $request->filled('state')) {
                throw new \Exception('Missing state parameter');
            }

            $state = decrypt($request->state);

            if (! isset($state['organization_id'], $state['credential_id'])) {
                throw new \Exception('Invalid state parameter');
            }

            if (isset($state['timestamp']) && now()->timestamp - $state['timestamp'] > 600) {
                throw new \Exception('OAuth state expired');
            }

            $credential = IntegrationCredential::findOrFail($state['credential_id']);

            if ($request->user()->organization_id !== $credential->organization_id) {
                abort(403, 'You do not have access to this organization');
            }

            // Amazon returns the auth code as `spapi_oauth_code`, plus `selling_partner_id`
            $code = $request->input('spapi_oauth_code') ?: $request->input('code');
            $sellingPartnerId = $request->input('selling_partner_id');

            if (! $code) {
                throw new \Exception('No authorization code received from Amazon');
            }

            $token = $oauthService->exchangeCodeForToken(
                $code,
                $credential,
                $request->user()?->id
            );

            $config = $credential->config ?? [];

            try {
                $info = $oauthService->getSellerInfo($token->decrypted_access_token);
                $config['amazon_seller_id'] = $info['seller_id'] ?? $sellingPartnerId;
                $config['amazon_marketplace_id'] = $info['marketplace_id'] ?? $config['amazon_marketplace_id'] ?? null;
                $config['amazon_marketplace_name'] = $info['marketplace_name'] ?? null;
                $config['amazon_country_code'] = $info['country_code'] ?? null;
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch Amazon seller info on callback', [
                    'credential_id' => $credential->id,
                    'error' => $e->getMessage(),
                ]);
                $config['amazon_seller_id'] = $sellingPartnerId;
            }

            $credential->update([
                'config' => $config,
                'is_active' => true,
                'last_verified_at' => now(),
            ]);

            RegisterAmazonNotifications::dispatch($credential->id);

            return redirect()->route('marketplaces.amazon.index')
                ->with('success', 'Amazon seller account connected successfully');

        } catch (\Throwable $e) {
            Log::error('Amazon OAuth callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('marketplaces.amazon.index')
                ->withErrors(['amazon' => 'Failed to connect to Amazon: '.$e->getMessage()]);
        }
    }

    public function disconnect(Request $request)
    {
        $user = $request->user();

        $credential = $request->has('credential_id')
            ? IntegrationCredential::where('id', $request->input('credential_id'))
                ->where('organization_id', $user->organization_id)
                ->where('platform', 'amazon')
                ->first()
            : IntegrationCredential::where('organization_id', $user->organization_id)
                ->where('platform', 'amazon')
                ->where('is_active', true)
                ->first();

        if (! $credential) {
            return back()->withErrors(['amazon' => 'No active Amazon connection found']);
        }

        $credential->oauthTokens()
            ->where('platform', 'amazon')
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $credential->update(['is_active' => false]);

        return back()->with('success', 'Amazon seller account disconnected');
    }
}
