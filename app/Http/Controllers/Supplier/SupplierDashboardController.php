<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Resources\Supplier\PerformanceMetricsResource;
use App\Http\Resources\Supplier\SettlementResource;
use App\Http\Resources\Supplier\SupplierDashboardResource;
use App\Models\Inventory\Supplier;
use App\Models\Supplier\Settlement;
use App\Models\Supplier\SupplierNotification;
use App\Models\Supplier\SupplierPerformanceMetric;
use App\Services\Supplier\CommissionCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SupplierDashboardController extends Controller
{
    public function __construct(
        protected CommissionCalculatorService $commissionService
    ) {}

    /**
     * Display the supplier dashboard
     */
    public function index(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        if (! $supplier) {
            return redirect()->route('dashboard')
                ->with('error', 'Supplier account not found.');
        }

        // Load relationships
        $supplier->load([
            'currentTier',
            'commissionStructure',
            'organization',
        ]);

        // Get recent settlements
        $recentSettlements = Settlement::forSupplier($supplier->id)
            ->with(['items'])
            ->latest('settlement_period_end')
            ->take(5)
            ->get();

        // Get performance metrics
        $latestMetrics = SupplierPerformanceMetric::getLatestForSupplier($supplier->id);

        // Get pending settlement amount
        $pendingAmount = Settlement::forSupplier($supplier->id)
            ->whereIn('status', ['approved', 'pending_payment', 'processing'])
            ->sum('net_amount');

        // Get unread notifications
        $unreadNotifications = SupplierNotification::forSupplier($supplier->id)
            ->unread()
            ->latest()
            ->take(10)
            ->get();

        // Calculate YTD earnings
        $ytdSummary = $this->commissionService->getCommissionSummary($supplier, 'year');

        // Get monthly trend data
        $monthlyTrend = $this->getMonthlyTrend($supplier);

        return Inertia::render('Supplier/Dashboard', [
            'supplier' => new SupplierDashboardResource($supplier),
            'recentSettlements' => SettlementResource::collection($recentSettlements),
            'latestMetrics' => $latestMetrics ? new PerformanceMetricsResource($latestMetrics) : null,
            'pendingAmount' => round($pendingAmount, 2),
            'unreadNotifications' => $unreadNotifications,
            'ytdSummary' => $ytdSummary,
            'monthlyTrend' => $monthlyTrend,
            'stats' => $this->getDashboardStats($supplier),
        ]);
    }

    /**
     * Get supplier overview data (API endpoint)
     */
    public function overview(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        return response()->json([
            'supplier' => new SupplierDashboardResource($supplier),
            'tier' => [
                'name' => $supplier->currentTier?->tier_name,
                'code' => $supplier->currentTier?->tier_code,
                'benefits' => $supplier->currentTier?->benefits ?? [],
                'next_evaluation' => $supplier->next_tier_evaluation,
            ],
            'commission' => [
                'structure' => $supplier->commissionStructure?->name,
                'type' => $supplier->commissionStructure?->type->label(),
                'base_rate' => $supplier->commissionStructure?->base_percentage,
            ],
            'settlement' => [
                'schedule' => $supplier->settlement_schedule,
                'next_date' => $supplier->next_settlement_date,
                'pending_amount' => $supplier->pending_settlement_amount,
            ],
        ]);
    }

    /**
     * Get supplier earnings summary
     */
    public function earnings(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();
        $period = $request->input('period', 'month');

        $summary = $this->commissionService->getCommissionSummary($supplier, $period);

        // Get breakdown by category
        $categoryBreakdown = $this->getCategoryBreakdown($supplier, $period);

        // Get comparison with previous period
        $comparison = $this->getPeriodComparison($supplier, $period);

        return response()->json([
            'summary' => $summary,
            'category_breakdown' => $categoryBreakdown,
            'comparison' => $comparison,
            'period' => $period,
        ]);
    }

    /**
     * Get tier progress information
     */
    public function tierProgress(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $currentTier = $supplier->currentTier;
        $latestMetrics = SupplierPerformanceMetric::getLatestForSupplier($supplier->id);

        if (! $currentTier || ! $latestMetrics) {
            return response()->json([
                'current_tier' => null,
                'score' => null,
                'progress' => null,
            ]);
        }

        $nextTier = $currentTier->getNextTier();
        $previousTier = $currentTier->getPreviousTier();

        $progress = [
            'current_score' => $latestMetrics->overall_performance_score,
            'current_tier' => [
                'name' => $currentTier->tier_name,
                'code' => $currentTier->tier_code,
                'min_score' => $currentTier->min_performance_score,
                'max_score' => $currentTier->max_performance_score,
            ],
            'next_tier' => $nextTier ? [
                'name' => $nextTier->tier_name,
                'code' => $nextTier->tier_code,
                'min_score' => $nextTier->min_performance_score,
                'points_needed' => max(0, $nextTier->min_performance_score - $latestMetrics->overall_performance_score),
            ] : null,
            'demotion_risk' => $previousTier && $latestMetrics->overall_performance_score < $currentTier->min_performance_score,
            'points_above_demotion' => $previousTier
                ? $latestMetrics->overall_performance_score - $currentTier->min_performance_score
                : null,
        ];

        // Calculate progress percentage within current tier
        $tierRange = $currentTier->max_performance_score - $currentTier->min_performance_score;
        $scoreInTier = $latestMetrics->overall_performance_score - $currentTier->min_performance_score;
        $progress['tier_progress_percentage'] = $tierRange > 0
            ? min(100, max(0, round(($scoreInTier / $tierRange) * 100)))
            : 100;

        return response()->json($progress);
    }

    /**
     * Get quick stats for dashboard widgets
     */
    public function quickStats(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        return response()->json($this->getDashboardStats($supplier));
    }

    /**
     * Get recent activity feed
     */
    public function activityFeed(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();
        $limit = $request->input('limit', 20);

        // Combine notifications, settlements, and tier changes
        $notifications = SupplierNotification::forSupplier($supplier->id)
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn ($n) => [
                'type' => 'notification',
                'id' => $n->id,
                'title' => $n->title,
                'message' => $n->short_message ?? $n->message,
                'category' => $n->category->value,
                'priority' => $n->priority->value,
                'is_read' => $n->is_read,
                'created_at' => $n->created_at,
                'action_url' => $n->action_url,
            ]);

        return response()->json([
            'activities' => $notifications->sortByDesc('created_at')->values(),
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationRead(Request $request, $notificationId)
    {
        $supplier = $this->getAuthenticatedSupplier();

        $notification = SupplierNotification::where('id', $notificationId)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsRead(Request $request)
    {
        $supplier = $this->getAuthenticatedSupplier();

        SupplierNotification::markAllAsRead($supplier->id);

        return response()->json(['success' => true]);
    }

    /**
     * Get commission preview for potential sale
     */
    public function commissionPreview(Request $request)
    {
        $request->validate([
            'price' => 'required|numeric|min:0',
            'category_id' => 'nullable|uuid|exists:categories,id',
        ]);

        $supplier = $this->getAuthenticatedSupplier();

        $preview = $this->commissionService->previewCommission(
            $supplier->organization_id,
            $supplier->id,
            $request->input('category_id'),
            (float) $request->input('price')
        );

        return response()->json($preview);
    }

    /**
     * Get tier comparison showing potential earnings at each tier
     */
    public function tierComparison(Request $request)
    {
        $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $supplier = $this->getAuthenticatedSupplier();

        $comparison = $this->commissionService->calculateTierComparison(
            $supplier,
            $request->input('price')
        );

        return response()->json($comparison);
    }

    // ============================================
    // Protected Helper Methods
    // ============================================

    /**
     * Get the authenticated supplier
     */
    protected function getAuthenticatedSupplier(): ?Supplier
    {
        $user = Auth::user();

        // Check if user has a linked supplier account
        if ($user->supplier_id) {
            return Supplier::with(['currentTier', 'commissionStructure'])
                ->find($user->supplier_id);
        }

        // Check if user is a supplier themselves
        return Supplier::where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->first();
    }

    /**
     * Get dashboard statistics
     */
    protected function getDashboardStats(Supplier $supplier): array
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        // This month's stats
        $thisMonth = Settlement::forSupplier($supplier->id)
            ->where('settlement_period_start', '>=', $startOfMonth)
            ->selectRaw('
                COUNT(*) as settlement_count,
                SUM(net_sales_amount) as total_sales,
                SUM(net_amount) as total_payout
            ')
            ->first();

        // Last month's stats for comparison
        $lastMonth = Settlement::forSupplier($supplier->id)
            ->whereBetween('settlement_period_start', [$startOfLastMonth, $endOfLastMonth])
            ->selectRaw('
                COUNT(*) as settlement_count,
                SUM(net_sales_amount) as total_sales,
                SUM(net_amount) as total_payout
            ')
            ->first();

        // Products stats
        $productsListed = $supplier->products()
            ->where('status', 'active')
            ->count();

        $productsSold = $supplier->products()
            ->where('status', 'sold')
            ->whereMonth('sold_at', $now->month)
            ->count();

        // Calculate percentage changes
        $salesChange = $lastMonth->total_sales > 0
            ? round((($thisMonth->total_sales - $lastMonth->total_sales) / $lastMonth->total_sales) * 100, 1)
            : 0;

        $payoutChange = $lastMonth->total_payout > 0
            ? round((($thisMonth->total_payout - $lastMonth->total_payout) / $lastMonth->total_payout) * 100, 1)
            : 0;

        return [
            'this_month' => [
                'sales' => round($thisMonth->total_sales ?? 0, 2),
                'payout' => round($thisMonth->total_payout ?? 0, 2),
                'settlements' => $thisMonth->settlement_count ?? 0,
            ],
            'changes' => [
                'sales' => $salesChange,
                'payout' => $payoutChange,
            ],
            'products' => [
                'listed' => $productsListed,
                'sold_this_month' => $productsSold,
            ],
            'performance_score' => $supplier->cached_performance_score,
            'tier' => $supplier->currentTier?->tier_name,
            'next_settlement' => $supplier->next_settlement_date,
            'pending_amount' => round($supplier->pending_settlement_amount ?? 0, 2),
        ];
    }

    /**
     * Get monthly trend data for charts
     */
    protected function getMonthlyTrend(Supplier $supplier, int $months = 6): array
    {
        $trend = [];
        $now = now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $monthData = Settlement::forSupplier($supplier->id)
                ->whereBetween('settlement_period_start', [$startOfMonth, $endOfMonth])
                ->selectRaw('
                    SUM(net_sales_amount) as sales,
                    SUM(net_amount) as payout,
                    SUM(commission_amount) as commission
                ')
                ->first();

            $trend[] = [
                'month' => $date->format('M Y'),
                'month_short' => $date->format('M'),
                'sales' => round($monthData->sales ?? 0, 2),
                'payout' => round($monthData->payout ?? 0, 2),
                'commission' => round($monthData->commission ?? 0, 2),
            ];
        }

        return $trend;
    }

    /**
     * Get earnings breakdown by category
     */
    protected function getCategoryBreakdown(Supplier $supplier, string $period): array
    {
        $query = \DB::table('settlement_items as si')
            ->join('settlements as s', 'si.settlement_id', '=', 's.id')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->where('s.supplier_id', $supplier->id);

        // Apply period filter
        $this->applyPeriodFilter($query, $period, 'si.sale_date');

        $breakdown = $query->groupBy('c.id', 'c.name')
            ->selectRaw('
                c.name as category,
                COUNT(*) as item_count,
                SUM(si.sale_price) as total_sales,
                SUM(si.supplier_payout) as total_payout
            ')
            ->orderByDesc('total_sales')
            ->get();

        return $breakdown->map(fn ($row) => [
            'category' => $row->category ?? 'Uncategorized',
            'items' => $row->item_count,
            'sales' => round($row->total_sales, 2),
            'payout' => round($row->total_payout, 2),
        ])->toArray();
    }

    /**
     * Get comparison with previous period
     */
    protected function getPeriodComparison(Supplier $supplier, string $period): array
    {
        $currentStart = now();
        $previousStart = now();

        switch ($period) {
            case 'month':
                $currentStart = now()->startOfMonth();
                $previousStart = now()->subMonth()->startOfMonth();
                break;
            case 'quarter':
                $currentStart = now()->startOfQuarter();
                $previousStart = now()->subQuarter()->startOfQuarter();
                break;
            case 'year':
                $currentStart = now()->startOfYear();
                $previousStart = now()->subYear()->startOfYear();
                break;
        }

        $current = $this->commissionService->getCommissionSummary($supplier, $period);

        // Get previous period manually
        $previous = \DB::table('settlement_items as si')
            ->join('settlements as s', 'si.settlement_id', '=', 's.id')
            ->where('s.supplier_id', $supplier->id)
            ->where('si.sale_date', '>=', $previousStart)
            ->where('si.sale_date', '<', $currentStart)
            ->selectRaw('
                SUM(sale_price) as total_sales,
                SUM(supplier_payout) as total_payout
            ')
            ->first();

        $salesChange = ($previous->total_sales ?? 0) > 0
            ? round((($current['total_sales'] - $previous->total_sales) / $previous->total_sales) * 100, 1)
            : 0;

        $payoutChange = ($previous->total_payout ?? 0) > 0
            ? round((($current['total_payout'] - $previous->total_payout) / $previous->total_payout) * 100, 1)
            : 0;

        return [
            'current' => [
                'sales' => $current['total_sales'],
                'payout' => $current['total_payout'],
            ],
            'previous' => [
                'sales' => round($previous->total_sales ?? 0, 2),
                'payout' => round($previous->total_payout ?? 0, 2),
            ],
            'change' => [
                'sales_percent' => $salesChange,
                'payout_percent' => $payoutChange,
            ],
        ];
    }

    /**
     * Apply period filter to query
     */
    protected function applyPeriodFilter($query, string $period, string $column): void
    {
        switch ($period) {
            case 'month':
                $query->where($column, '>=', now()->startOfMonth());
                break;
            case 'quarter':
                $query->where($column, '>=', now()->startOfQuarter());
                break;
            case 'year':
                $query->where($column, '>=', now()->startOfYear());
                break;
        }
    }
}
