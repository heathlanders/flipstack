<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductIntakeQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Changed from IntakeQueueItem
use Inertia\Inertia;

class OwnerDashboardController extends BaseDashboardController
{
    public function show(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        return Inertia::render('Dashboard/Owner', [
            'dashboardType' => 'owner',
            'user' => [
                'name' => $user->name,
                'role' => $user->getPrimaryRole(),
                'permissions' => $user->getEffectivePermissions(),
            ],
            'metrics' => $this->getOwnerMetrics($user),
            'recentActivity' => $this->getRecentActivity($user, 'owner'),
            'alerts' => $this->getAlerts($user),
            'quickActions' => $user->getQuickActions(),
            'teamPerformance' => $this->getTeamPerformance($organization),
            'platformHealth' => $this->getPlatformHealth($organization),
            'financialSummary' => $this->getFinancialSummary($organization),
        ]);
    }

    protected function getOwnerMetrics($user)
    {
        $organization = $user->organization;
        $startOfWeek = now()->startOfWeek();

        // Total products
        $totalProducts = Product::where('organization_id', $organization->id)->count();

        // Products added this week
        $productsThisWeek = Product::where('organization_id', $organization->id)
            ->where('created_at', '>=', $startOfWeek)
            ->count();

        $productsLastWeek = Product::where('organization_id', $organization->id)
            ->whereBetween('created_at', [
                $startOfWeek->copy()->subWeek(),
                $startOfWeek,
            ])
            ->count();

        // Inventory value calculation using stock_quantity
        $inventoryValue = (float) (Product::where('organization_id', $organization->id)
            ->whereNotNull('cost_price')
            ->whereNotNull('stock_quantity')
            ->sum(DB::raw('stock_quantity * cost_price')) ?? 0);

        // Low stock count
        $lowStockCount = Product::where('organization_id', $organization->id)
            ->where('manage_stock', true)
            ->where('stock_quantity', '<=', 5)
            ->count();

        // Pending intake items - using correct model and column names
        $pendingIntake = ProductIntakeQueue::whereHas('team', function ($query) use ($organization) {
            $query->where('organization_id', $organization->id);
        })
            ->where('processing_status', 'pending') // Using processing_status instead of status
            ->count();

        return [
            'inventory' => [
                'total_products' => $totalProducts,
                'inventory_value' => $inventoryValue,
                'products_this_week' => $productsThisWeek,
                'products_last_week' => $productsLastWeek,
                'trend' => $productsLastWeek > 0 ?
                    $this->calculatePercentageChange($productsThisWeek, $productsLastWeek) : 0,
            ],
            'alerts' => [
                'low_stock_count' => $lowStockCount,
                'pending_intake' => $pendingIntake,
            ],
        ];
    }

    protected function getTeamPerformance($organization)
    {
        // Simplified team performance to avoid relationship issues
        $teams = $organization->teams()
            ->with('users')
            ->get()
            ->map(function ($team) {
                // Get products created by team members
                $teamProducts = Product::where('organization_id', $team->organization_id)
                    ->whereIn('created_by', $team->users->pluck('id'))
                    ->count();

                return [
                    'name' => $team->name ?: 'Team '.$team->id,
                    'department' => $team->department ?: 'General',
                    'member_count' => $team->users->count(),
                    'productivity_score' => min(100, $teamProducts * 10),
                    'recent_activity' => $teamProducts,
                    'trend' => 'neutral',
                ];
            });

        return $teams->take(5);
    }

    protected function getPlatformHealth($organization)
    {
        // Platform integration health status
        return [
            'ebay' => [
                'status' => 'connected',
                'last_sync' => now()->subHours(2),
                'active_listings' => Product::where('organization_id', $organization->id)
                    ->where('status', 'active')
                    ->count(),
                'sync_errors' => 0,
                'rate_limit_status' => 'normal',
            ],
            'amazon' => [
                'status' => 'disconnected',
                'last_sync' => null,
                'active_listings' => 0,
                'sync_errors' => 0,
                'rate_limit_status' => 'normal',
            ],
            'mercari' => [
                'status' => 'disconnected',
                'last_sync' => null,
                'active_listings' => 0,
                'sync_errors' => 0,
                'rate_limit_status' => 'normal',
            ],
        ];
    }

    protected function getFinancialSummary($organization)
    {
        // Calculate financial metrics using stock_quantity
        $totalCost = (float) (Product::where('organization_id', $organization->id)
            ->whereNotNull('cost_price')
            ->whereNotNull('stock_quantity')
            ->sum(DB::raw('stock_quantity * cost_price')) ?? 0);

        $estimatedValue = (float) (Product::where('organization_id', $organization->id)
            ->whereNotNull('selling_price')
            ->whereNotNull('stock_quantity')
            ->sum(DB::raw('stock_quantity * selling_price')) ?? 0);

        $potentialProfit = $estimatedValue - $totalCost;
        $profitMargin = $estimatedValue > 0 ? ($potentialProfit / $estimatedValue) * 100 : 0;

        return [
            'total_invested' => $totalCost,
            'estimated_value' => $estimatedValue,
            'potential_profit' => $potentialProfit,
            'profit_margin' => $profitMargin,
            'formatted' => [
                'total_invested' => $this->formatCurrency($totalCost),
                'estimated_value' => $this->formatCurrency($estimatedValue),
                'potential_profit' => $this->formatCurrency($potentialProfit),
            ],
        ];
    }
}
