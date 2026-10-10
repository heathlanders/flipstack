<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\Integration\IntegrationCredential;
use App\Services\EbayConfigService;
use App\Services\EbayOAuthService;
use App\Services\Shopify\ShopifyOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IntegrationController extends Controller
{
    /**
     * Connect to eBay - creates/finds credential and returns OAuth URL.
     *
     * Users don't need to provide any credentials - the app uses
     * app-level credentials from configuration.
     */
    public function connectEbay(Request $request, EbayConfigService $config)
    {
        $organization = $request->user()->organization;

        if (! $organization) {
            return response()->json(['error' => 'You must belong to an organization to connect eBay.'], 422);
        }

        $this->authorize('manageOrganization', $organization);

        // Check if app-level credentials are configured
        if (! $config->isConfigured()) {
            return response()->json(['error' => 'eBay integration is not configured. Please contact the administrator.'], 422);
        }

        $environment = $config->getEnvironment();

        // If reconnecting an existing credential, use it; otherwise reuse any pending
        // credential (OAuth never completed -- seller_username still null) or create new.
        if ($request->has('credential_id')) {
            $credential = IntegrationCredential::where('id', $request->input('credential_id'))
                ->where('organization_id', $organization->id)
                ->where('platform', 'ebay')
                ->firstOrFail();
        } else {
            $credential = IntegrationCredential::where('organization_id', $organization->id)
                ->where('platform', 'ebay')
                ->where('environment', $environment)
                ->whereNull('seller_username')
                ->latest()
                ->first();

            if (! $credential) {
                $existingCount = IntegrationCredential::where('organization_id', $organization->id)
                    ->where('platform', 'ebay')
                    ->where('environment', $environment)
                    ->count();

                $name = $request->input('name')
                    ?? ($existingCount > 0 ? 'eBay Account '.($existingCount + 1) : 'eBay '.ucfirst($environment));

                $credential = IntegrationCredential::create([
                    'organization_id' => $organization->id,
                    'team_id' => $request->user()->currentTeam?->id,
                    'platform' => 'ebay',
                    'environment' => $environment,
                    'name' => $name,
                    'client_id' => 'app_level_credentials',
                    'is_active' => true,
                ]);
            }
        }

        // Get the authorization URL
        $authUrl = $credential->getAuthorizationUrl();

        if (! $authUrl) {
            return response()->json(['error' => 'Failed to generate eBay authorization URL.'], 422);
        }

        return response()->json(['redirect_url' => $authUrl]);
    }

    public function store(Request $request)
    {
        Gate::authorize('update', $request->user()->currentTeam);

        $validated = $request->validate([
            'platform' => 'required|string|in:ebay,amazon,newegg,barcode_spider,gemini,easypost',
            'environment' => 'required|string|in:sandbox,production',
            'name' => 'nullable|string|max:255',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
        ]);

        $team = $request->user()->currentTeam;

        // Special handling for Gemini
        if ($validated['platform'] === 'gemini') {
            $validated['environment'] = 'production';
            $validated['client_id'] = 'gemini_api_key'; // Placeholder since client_id is required
        }

        // Special handling for Barcode Spider
        if ($validated['platform'] === 'barcode_spider') {
            $validated['environment'] = 'production';
        }

        // Special handling for EasyPost
        if ($validated['platform'] === 'easypost') {
            $validated['environment'] = 'production';
            $validated['client_id'] = 'easypost_api_key';
        }

        $organization = $request->user()->organization;

        // Single-instance platforms only allow one credential per org
        $singleInstancePlatforms = ['barcode_spider', 'gemini', 'easypost'];

        if (in_array($validated['platform'], $singleInstancePlatforms)) {
            $existing = IntegrationCredential::where(function ($q) use ($team, $organization) {
                $q->where('team_id', $team->id)
                    ->orWhere('organization_id', $organization?->id);
            })
                ->where('platform', $validated['platform'])
                ->where('environment', $validated['environment'])
                ->first();

            if ($existing) {
                return back()->withErrors([
                    'platform' => 'Credentials for this platform and environment already exist.',
                ]);
            }
        }

        $credential = IntegrationCredential::create([
            'team_id' => $team->id,
            'organization_id' => $organization?->id,
            'platform' => $validated['platform'],
            'environment' => $validated['environment'],
            'name' => $validated['name'] ?? ucfirst($validated['platform']).' '.ucfirst($validated['environment']),
            'client_id' => $validated['client_id'] ?? 'api_key_placeholder',
            'client_secret' => $validated['client_secret'],
            'is_active' => true,
        ]);

        // Verify eBay credentials
        if ($validated['platform'] === 'ebay') {
            $service = new EbayOAuthService($credential);
            $verified = $service->verifyCredentials();

            if (! $verified) {
                return back()->withErrors([
                    'client_id' => 'Failed to verify credentials.',
                ]);
            }
        }

        return back()->with('success', 'Integration added successfully');
    }

    public function update(Request $request, IntegrationCredential $credential)
    {
        Gate::authorize('update', $request->user()->currentTeam);

        if ($credential->team_id !== $request->user()->currentTeam->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'client_id' => 'sometimes|string',
            'client_secret' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $credential->update($validated);

        return back()->with('success', 'Integration updated successfully');
    }

    public function destroy(Request $request, IntegrationCredential $credential)
    {
        Gate::authorize('update', $request->user()->currentTeam);

        if ($credential->team_id !== $request->user()->currentTeam->id) {
            abort(403);
        }

        $credential->oauthTokens()->update(['is_active' => false]);
        $credential->delete();

        return back()->with('success', 'Integration removed successfully');
    }

    public function verifyCredentials(Request $request, IntegrationCredential $credential)
    {
        Gate::authorize('update', $request->user()->currentTeam);

        if ($credential->team_id !== $request->user()->currentTeam->id) {
            abort(403);
        }

        if ($credential->platform === 'ebay') {
            $service = new EbayOAuthService($credential);
            $verified = $service->verifyCredentials();

            if ($verified) {
                return back()->with('success', 'Credentials verified successfully');
            } else {
                return back()->withErrors(['verification' => 'Failed to verify credentials.']);
            }
        }

        if ($credential->platform === 'shopify') {
            $service = new ShopifyOAuthService;
            $verified = $service->verifyCredentials($credential);

            if ($verified) {
                $credential->update(['last_verified_at' => now()]);

                return back()->with('success', 'Shopify connection verified successfully');
            } else {
                return back()->withErrors(['verification' => 'Failed to verify Shopify connection. Check that your store is accessible and the access token is valid.']);
            }
        }

        if ($credential->platform === 'gemini') {
            // Test Gemini API
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'x-goog-api-key' => $credential->decrypted_client_secret,
                ])->get('https://generativelanguage.googleapis.com/v1beta/models');

                if ($response->successful()) {
                    return back()->with('success', 'Gemini API key verified successfully');
                } else {
                    return back()->withErrors(['verification' => 'Invalid Gemini API key']);
                }
            } catch (\Exception $e) {
                return back()->withErrors(['verification' => 'Failed to verify Gemini API key: '.$e->getMessage()]);
            }
        }

        if ($credential->platform === 'easypost') {
            try {
                $apiKey = $credential->decrypted_client_secret;
                $response = \Illuminate\Support\Facades\Http::withBasicAuth($apiKey, '')
                    ->get('https://api.easypost.com/v2/addresses');

                if ($response->successful() || $response->status() === 200) {
                    $credential->update(['last_verified_at' => now()]);

                    return back()->with('success', 'EasyPost API key verified successfully');
                } else {
                    return back()->withErrors(['verification' => 'Invalid EasyPost API key']);
                }
            } catch (\Exception $e) {
                return back()->withErrors(['verification' => 'Failed to verify EasyPost API key: '.$e->getMessage()]);
            }
        }

        return back()->with('success', 'Verification not needed for this platform');
    }
}
