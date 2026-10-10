<?php

namespace App\Services;

use App\Contracts\MarketplaceAdapter;
use App\Models\Integration\IntegrationCredential;
use Illuminate\Support\Facades\Log;

class MarketplaceRegistry
{
    /**
     * Get all available marketplace adapters for an organization.
     *
     * @return MarketplaceAdapter[]
     */
    public function getAdaptersForOrganization(int $organizationId): array
    {
        $credentials = IntegrationCredential::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get();

        $adapters = [];

        foreach ($credentials as $credential) {
            $adapter = $this->resolveAdapter($credential);

            if ($adapter && $adapter->isAvailable()) {
                $adapters[] = $adapter;
            }
        }

        return $adapters;
    }

    /**
     * Get a specific adapter for a platform and organization.
     *
     * If credentialId is provided, returns that specific credential's adapter.
     * Otherwise returns the first active credential for backward compatibility.
     */
    public function getAdapter(int $organizationId, string $platform, ?int $credentialId = null): ?MarketplaceAdapter
    {
        $config = config("marketplaces.{$platform}");

        if (! $config) {
            return null;
        }

        $query = IntegrationCredential::where('organization_id', $organizationId)
            ->where('platform', $config['credential_type'])
            ->where('is_active', true);

        if ($credentialId) {
            $query->where('id', $credentialId);
        }

        $credential = $query->first();

        if (! $credential) {
            return null;
        }

        return $this->resolveAdapter($credential);
    }

    /**
     * Get all adapters for a specific platform within an organization.
     *
     * @return MarketplaceAdapter[]
     */
    public function getAdapters(int $organizationId, string $platform): array
    {
        $config = config("marketplaces.{$platform}");

        if (! $config) {
            return [];
        }

        $credentials = IntegrationCredential::where('organization_id', $organizationId)
            ->where('platform', $config['credential_type'])
            ->where('is_active', true)
            ->get();

        $adapters = [];

        foreach ($credentials as $credential) {
            $adapter = $this->resolveAdapter($credential);

            if ($adapter && $adapter->isAvailable()) {
                $adapters[] = $adapter;
            }
        }

        return $adapters;
    }

    /**
     * Resolve an adapter from a credential using the marketplaces config.
     */
    public function resolveAdapter(IntegrationCredential $credential): ?MarketplaceAdapter
    {
        $config = config("marketplaces.{$credential->platform}");

        if (! $config || ! isset($config['adapter'])) {
            return null;
        }

        $adapterClass = $config['adapter'];

        if (! class_exists($adapterClass)) {
            Log::warning('MarketplaceRegistry: adapter class not found', [
                'platform' => $credential->platform,
                'class' => $adapterClass,
            ]);

            return null;
        }

        try {
            return new $adapterClass($credential);
        } catch (\Exception $e) {
            Log::error('MarketplaceRegistry: failed to instantiate adapter', [
                'platform' => $credential->platform,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get all registered marketplace platform keys.
     */
    public function getRegisteredPlatforms(): array
    {
        return array_keys(config('marketplaces', []));
    }
}
