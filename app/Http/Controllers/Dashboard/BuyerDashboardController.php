<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Catalog\Product;
use App\Models\Inventory\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BuyerDashboardController extends BaseDashboardController
{
    public function show(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        return Inertia::render('Dashboard/Buyer', [
            'dashboardType' => 'buyer',
            'user' => [
                'name' => $user->name,
                'role' => $user->getPrimaryRole(),
                'permissions' => $user->getEffectivePermissions(),
            ],
            'metrics' => $this->getBuyerMetrics($user),
            'recentActivity' => $this->getRecentActivity($user, 'buyer'),
            'alerts' => $this->getAlerts($user),
            'quickActions' => $user->getQuickActions(),
            'roiAnalysis' => $this->getROIAnalysis($organization),
            'supplierPerformance' => $this->getSupplierPerformance($organization),
            'marketInsights' => $this->getMarketInsights($organization),
            'budgetTracking' => $this->getBudgetTracking($organization),
        ]);
    }

    protected function getBuyerMetrics($user)
    {
        $organization = $user->organization;
        $startOfMonth = now()->startOfMonth();

        // Products purchased this month
        $purchasesThisMonth = Product::where('organization_id', $organization->id)
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $purchasesLastMonth = Product::where('organization_id', $organization->id)
            ->whereBetween('created_at', [
                $startOfMonth->copy()->subMonth(),
                $startOfMonth,
            ])
            ->count();

        // Average cost per item
        $avgCostPerItem = Product::where('organization_id', $organization->id)
            ->where('created_at', '>=', $startOfMonth)
            ->avg('cost_price') ?? 0;

        // Total invested this month
        $monthlyInvestment = Product::where('organization_id', $organization->id)
            ->where('created_at', '>=', $startOfMonth)
            ->sum(DB::raw('quantity * cost_price')) ?? 0;

        // Potential profit calculation
        $potentialProfit = Product::where('organization_id', $organization->id)
            ->where('created_at', '>=', $startOfMonth)
            ->sum(DB::raw('quantity * (selling_price - cost_price)')) ?? 0;

        $avgROI = $monthlyInvestment > 0 ? ($potentialProfit / $monthlyInvestment) * 100 : 0;

        // Items needing research (no selling price set)
        $needsResearch = Product::where('organization_id', $organization->id)
            ->where(function ($query) {
                $query->whereNull('selling_price')
                    ->orWhere('selling_price', 0);
            })
            ->count();

        return [
            'purchasing' => [
                'this_month' => $purchasesThisMonth,
                'last_month' => $purchasesLastMonth,
                'trend' => $purchasesLastMonth > 0 ?
                    $this->calculatePercentageChange($purchasesThisMonth, $purchasesLastMonth) : 0,
                'avg_cost' => (float) $avgCostPerItem,
            ],
            'investment' => [
                'monthly_total' => (float) $monthlyInvestment,
                'avg_roi' => (float) $avgROI,
                'potential_profit' => (float) $potentialProfit,
            ],
            'research' => [
                'needs_pricing' => $needsResearch,
                'avg_cost_formatted' => $this->formatCurrency($avgCostPerItem),
            ],
        ];
    }

    protected function getROIAnalysis($organization)
    {
        // Calculate ROI by category
        $roiByCategory = Product::where('organization_id', $organization->id)
            ->whereNotNull('selling_price')
            ->where('selling_price', '>', 0)
            ->select(
                'category_id',
                DB::raw('AVG((selling_price - cost_price) / cost_price * 100) as avg_roi'),
                DB::raw('COUNT(*) as product_count'),
                DB::raw('SUM(quantity * cost_price) as total_invested'),
                DB::raw('SUM(quantity * (selling_price - cost_price)) as total_profit')
            )
            ->with('category:id,name')
            ->groupBy('category_id')
            ->orderByDesc('avg_roi')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category?->name ?? 'Uncategorized',
                    'avg_roi' => round($item->avg_roi, 1),
                    'product_count' => $item->product_count,
                    'total_invested' => $item->total_invested,
                    'total_profit' => $item->total_profit,
                    'formatted_invested' => $this->formatCurrency($item->total_invested),
                    'formatted_profit' => $this->formatCurrency($item->total_profit),
                ];
            });

        return $roiByCategory;
    }

    protected function getSupplierPerformance($organization)
    {
        // Get supplier metrics via supplier_products pivot
        $suppliers = Supplier::where('organization_id', $organization->id)
            ->withCount('products')
            ->get()
            ->map(function ($supplier) {
                $productIds = $supplier->products()->pluck('products.id');

                $totalInvested = Product::whereIn('id', $productIds)
                    ->sum(DB::raw('quantity * cost_price')) ?? 0;
                $avgCost = Product::whereIn('id', $productIds)
                    ->avg('cost_price') ?? 0;

                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'product_count' => $supplier->products_count,
                    'avg_cost' => $avgCost,
                    'total_invested' => $totalInvested,
                    'rating' => $supplier->rating ?? 0,
                    'formatted_invested' => $this->formatCurrency($totalInvested),
                    'formatted_avg_cost' => $this->formatCurrency($avgCost),
                ];
            })
            ->sortByDesc('total_invested')
            ->take(5);

        return $suppliers->values();
    }

    protected function getMarketInsights($organization)
    {
        $insights = [];

        // Most profitable category
        $topCategory = Product::where('organization_id', $organization->id)
            ->whereNotNull('selling_price')
            ->where('selling_price', '>', 0)
            ->select(
                'category_id',
                DB::raw('AVG((selling_price - cost_price) / cost_price * 100) as avg_roi')
            )
            ->with('category:id,name')
            ->groupBy('category_id')
            ->orderByDesc('avg_roi')
            ->first();

        if ($topCategory && $topCategory->category) {
            $insights[] = [
                'type' => 'opportunity',
                'title' => 'Top Performing Category',
                'message' => "{$topCategory->category->name} has the highest ROI at ".round($topCategory->avg_roi, 1).'%',
                'action' => 'Focus sourcing efforts here',
            ];
        }

        return $insights;
    }

    protected function getBudgetTracking($organization)
    {
        $startOfMonth = now()->startOfMonth();

        // This month's spending
        $monthlySpent = (float) (Product::where('organization_id', $organization->id)
            ->where('created_at', '>=', $startOfMonth)
            ->sum(DB::raw('quantity * cost_price')) ?? 0);

        // Placeholder budget limits (could be stored in organization settings)
        $monthlyBudget = 5000.0;

        return [
            'monthly' => [
                'spent' => $monthlySpent,
                'budget' => $monthlyBudget,
                'remaining' => $monthlyBudget - $monthlySpent,
                'percentage_used' => $monthlyBudget > 0 ? round(($monthlySpent / $monthlyBudget) * 100, 1) : 0,
                'formatted_spent' => $this->formatCurrency($monthlySpent),
                'formatted_remaining' => $this->formatCurrency($monthlyBudget - $monthlySpent),
            ],
        ];
    }
}
