<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Integration\IntegrationCredential;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MainDashboardController extends OwnerDashboardController
{
    public function show(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        return Inertia::render('Dashboard/Main', [
            'dashboardType' => 'main',
            'user' => [
                'name' => $user->name,
                'role' => $user->getPrimaryRole(),
                'permissions' => $user->getEffectivePermissions(),
            ],
            'metrics' => $this->getOwnerMetrics($user),
            'recentActivity' => $this->getRecentActivity($user, 'owner'),
            'alerts' => $this->getAlerts($user),
            'quickActions' => $user->getQuickActions(),
            'platformHealth' => $this->getPlatformHealth($organization),
            'financialSummary' => $this->getFinancialSummary($organization),
            'marketplaceConnections' => $this->getMarketplaceConnections($organization),
        ]);
    }

    protected function getMarketplaceConnections($organization): array
    {
        $credentials = IntegrationCredential::where('organization_id', $organization->id)
            ->whereIn('platform', ['ebay', 'shopify', 'amazon'])
            ->get();

        $connections = [];
        foreach (['ebay', 'shopify', 'amazon'] as $platform) {
            $credential = $credentials->firstWhere('platform', $platform);
            $connections[] = [
                'platform' => $platform,
                'connected' => $credential && $credential->is_active,
                'last_sync' => $credential?->last_full_sync_at,
                'status' => $credential && $credential->is_active ? 'connected' : 'disconnected',
            ];
        }

        return $connections;
    }
}
