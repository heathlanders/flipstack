<?php

namespace App\Http\Middleware;

use App\Models\Integration\IntegrationCredential;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'permissions' => fn () => $this->getUserPermissions($request),
                'roles' => fn () => $this->getUserRoles($request),
                'is_supplier' => fn () => $request->user()?->is_supplier ?? false,
            ],
            'marketplaces' => fn () => $this->getMarketplaceConnections($request),
            'csrf_token' => csrf_token(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Get the current user's effective permissions.
     */
    protected function getUserPermissions(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        // Check if user has method to get permissions
        if (method_exists($user, 'getEffectivePermissions')) {
            return $user->getEffectivePermissions();
        }

        return [];
    }

    /**
     * Get the current user's roles.
     */
    protected function getUserRoles(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        // Get role slugs for easy checking
        if (method_exists($user, 'roles')) {
            return $user->roles()
                ->wherePivot('organization_id', $user->organization_id ?? $user->current_team_id)
                ->pluck('slug')
                ->toArray();
        }

        return [];
    }

    /**
     * Get marketplace integration connections for the user's team/organization.
     */
    protected function getMarketplaceConnections(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        $organizationId = $user->organization_id;

        if (! $organizationId) {
            return [];
        }

        // Marketplace platforms (not utility APIs like barcode_spider, gemini)
        $marketplacePlatforms = ['ebay', 'amazon', 'mercari', 'poshmark', 'facebook'];

        $marketplaces = [];

        // Check for DB-stored credentials (marketplace platforms only)
        $credentials = IntegrationCredential::where('organization_id', $organizationId)
            ->whereIn('platform', $marketplacePlatforms)
            ->with(['oauthTokens' => function ($query) {
                $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            }])
            ->get();

        $foundPlatforms = [];

        foreach ($credentials as $credential) {
            $activeToken = $credential->oauthTokens->first();
            $hasActiveToken = $activeToken !== null;
            $isConnected = $credential->is_active && $hasActiveToken;
            $foundPlatforms[] = $credential->platform;

            $marketplaces[] = [
                'name' => ucfirst($credential->platform),
                'platform' => $credential->platform,
                'status' => $isConnected ? 'connected' : 'disconnected',
                'hasErrors' => ! $isConnected,
                'seller_username' => $credential->seller_username,
                'account_username' => $credential->seller_username ?? ($activeToken?->metadata ?? [])['username'] ?? null,
            ];
        }

        // Check for env-configured eBay (if not already found in DB)
        if (! in_array('ebay', $foundPlatforms)) {
            $hasEbayConfig = config('services.ebay.production.client_id')
                || env('EBAY_PRODUCTION_CLIENT_ID');

            if ($hasEbayConfig) {
                $marketplaces[] = [
                    'name' => 'eBay',
                    'platform' => 'ebay',
                    'status' => 'disconnected',
                    'hasErrors' => true,
                ];
            }
        }

        return $marketplaces;
    }
}
