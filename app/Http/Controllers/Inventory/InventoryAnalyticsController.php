<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnalyticsDashboardResource;
use App\Services\Analytics\ABCAnalysisService;
use App\Services\Analytics\InventoryAnalyticsService;
use App\Services\Analytics\TurnoverAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class InventoryAnalyticsController extends Controller
{
    public function __construct(
        protected InventoryAnalyticsService $analyticsService,
        protected ABCAnalysisService $abcService,
        protected TurnoverAnalysisService $turnoverService,
    ) {}

    /**
     * Get organization ID from authenticated user
     */
    protected function getOrganizationId(): int
    {
        $user = auth()->user();

        return $user->organization_id ?? $user->current_organization_id ?? 1;
    }

    /**
     * Parse date range from request
     */
    protected function parseDateRange(Request $request): array
    {
        $startDate = $request->has('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : null;

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : null;

        // Default to last 30 days if no dates provided
        if (! $startDate && ! $endDate) {
            $endDate = now()->endOfDay();
            $startDate = now()->subDays(30)->startOfDay();
        }

        return [$startDate, $endDate];
    }

    /**
     * Display the analytics dashboard
     */
    public function dashboard(Request $request): Response
    {
        // Check permission
        if (! auth()->user()->hasPermission('analytics.view')) {
            abort(403, 'You do not have permission to view analytics.');
        }

        $organizationId = $this->getOrganizationId();
        [$startDate, $endDate] = $this->parseDateRange($request);

        $dashboardData = $this->analyticsService
            ->forOrganization($organizationId)
            ->dateRange($startDate, $endDate)
            ->getDashboardKPIs();

        return Inertia::render('Inventory/Analytics/Dashboard', [
            'dashboardData' => new AnalyticsDashboardResource($dashboardData),
            'dateRange' => [
                'start_date' => $startDate?->toDateString(),
                'end_date' => $endDate?->toDateString(),
            ],
        ]);
    }

    /**
     * Get dashboard KPIs via API
     */
    public function getKPIs(Request $request)
    {
        if (! auth()->user()->hasPermission('analytics.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $organizationId = $this->getOrganizationId();
        [$startDate, $endDate] = $this->parseDateRange($request);

        $data = $this->analyticsService
            ->forOrganization($organizationId)
            ->dateRange($startDate, $endDate)
            ->getDashboardKPIs();

        return response()->json(new AnalyticsDashboardResource($data));
    }

    /**
     * Get inventory value metrics
     */
    public function getInventoryValue(Request $request)
    {
        if (! auth()->user()->hasPermission('analytics.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $organizationId = $this->getOrganizationId();
        [$startDate, $endDate] = $this->parseDateRange($request);

        $data = $this->analyticsService
            ->forOrganization($organizationId)
            ->dateRange($startDate, $endDate)
            ->getTotalInventoryValue();

        return response()->json($data);
    }

    /**
     * Get stock health metrics
     */
    public function getStockHealth(Request $request)
    {
        if (! auth()->user()->hasPermission('analytics.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $organizationId = $this->getOrganizationId();

        $data = $this->analyticsService
            ->forOrganization($organizationId)
            ->getStockHealthMetrics();

        return response()->json($data);
    }

    /**
     * Get movement trends
     */
    public function getMovementTrends(Request $request)
    {
        if (! auth()->user()->hasPermission('analytics.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $organizationId = $this->getOrganizationId();
        $interval = $request->input('interval', 'day');
        $periods = (int) $request->input('periods', 30);

        $data = $this->analyticsService
            ->forOrganization($organizationId)
            ->getMovementTrends($interval, $periods);

        return response()->json($data);
    }

    /**
     * Get top performers
     */
    public function getTopPerformers(Request $request)
    {
        if (! auth()->user()->hasPermission('analytics.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $organizationId = $this->getOrganizationId();
        $limit = (int) $request->input('limit', 10);
        $metric = $request->input('metric', 'total_sales');

        $data = $this->analyticsService
            ->forOrganization($organizationId)
            ->getTopPerformers($limit, $metric);

        return response()->json($data);
    }

    /**
     * Get location performance
     */
    public function getLocationPerformance(Request $request)
    {
        if (! auth()->user()->hasPermission('analytics.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $organizationId = $this->getOrganizationId();

        $data = $this->analyticsService
            ->forOrganization($organizationId)
            ->getLocationPerformance();

        return response()->json($data);
    }

    /**
     * Display ABC Analysis page
     */
    public function abcAnalysis(Request $request): Response
    {
        if (! auth()->user()->hasPermission('analytics.view')) {
            abort(403, 'You do not have permission to view analytics.');
        }

        $organizationId = $this->getOrganizationId();
        $metric = $request->input('metric', 'revenue');

        $analysisData = $this->abcService
            ->forOrganization($organizationId)
            ->analyze($metric);

        return Inertia::render('Inventory/Analytics/ABCAnalysis', [
            'analysisData' => $analysisData,
            'currentMetric' => $metric,
        ]);
    }

    /**
     * Get ABC Analysis data via API
     */
    public function getABCAnalysis(Request $request)
    {
        if (! auth()->user()->hasPermission('analytics.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $organizationId = $this->getOrganizationId();
        $metric = $request->input('metric', 'revenue');
        $aThreshold = (float) $request->input('a_threshold', 80);
        $bThreshold = (float) $request->input('b_threshold', 95);

        $data = $this->abcService
            ->forOrganization($organizationId)
            ->setThresholds($aThreshold, $bThreshold)
            ->analyze($metric);

        return response()->json($data);
    }

    /**
     * Display Turnover Report page
     */
    public function turnoverReport(Request $request): Response
    {
        if (! auth()->user()->hasPermission('analytics.view')) {
            abort(403, 'You do not have permission to view analytics.');
        }

        $organizationId = $this->getOrganizationId();
        $period = (int) $request->input('period', 365);

        $turnoverData = $this->turnoverService
            ->forOrganization($organizationId)
            ->setPeriod($period)
            ->analyze();

        return Inertia::render('Inventory/Analytics/TurnoverReport', [
            'turnoverData' => $turnoverData,
            'currentPeriod' => $period,
        ]);
    }

    /**
     * Get Turnover Analysis data via API
     */
    public function getTurnoverAnalysis(Request $request)
    {
        if (! auth()->user()->hasPermission('analytics.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $organizationId = $this->getOrganizationId();
        $period = (int) $request->input('period', 365);

        $data = $this->turnoverService
            ->forOrganization($organizationId)
            ->setPeriod($period)
            ->analyze();

        return response()->json($data);
    }

    /**
     * Get turnover trend over time
     */
    public function getTurnoverTrend(Request $request)
    {
        if (! auth()->user()->hasPermission('analytics.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $organizationId = $this->getOrganizationId();
        $periods = (int) $request->input('periods', 6);
        $interval = $request->input('interval', 'month');

        $data = $this->turnoverService
            ->forOrganization($organizationId)
            ->getTurnoverTrend($periods, $interval);

        return response()->json($data);
    }

    /**
     * Display Valuation Report page
     */
    public function valuationReport(Request $request): Response
    {
        if (! auth()->user()->hasPermission('reports.view')) {
            abort(403, 'You do not have permission to view reports.');
        }

        $organizationId = $this->getOrganizationId();
        [$startDate, $endDate] = $this->parseDateRange($request);

        $valuationData = [
            'inventory_value' => $this->analyticsService
                ->forOrganization($organizationId)
                ->getTotalInventoryValue(),
            'category_distribution' => $this->analyticsService
                ->forOrganization($organizationId)
                ->getCategoryDistribution(),
            'brand_distribution' => $this->analyticsService
                ->forOrganization($organizationId)
                ->getBrandDistribution(),
            'condition_distribution' => $this->analyticsService
                ->forOrganization($organizationId)
                ->getConditionDistribution(),
            'aging_analysis' => $this->analyticsService
                ->forOrganization($organizationId)
                ->getAgingAnalysis(),
            'location_performance' => $this->analyticsService
                ->forOrganization($organizationId)
                ->getLocationPerformance(),
        ];

        return Inertia::render('Inventory/Analytics/ValuationReport', [
            'valuationData' => $valuationData,
            'dateRange' => [
                'start_date' => $startDate?->toDateString(),
                'end_date' => $endDate?->toDateString(),
            ],
        ]);
    }

    /**
     * Export analytics data
     */
    public function export(Request $request)
    {
        if (! auth()->user()->hasPermission('analytics.export')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $organizationId = $this->getOrganizationId();
        $type = $request->input('type', 'dashboard');
        $format = $request->input('format', 'csv');

        $data = match ($type) {
            'abc' => $this->abcService->forOrganization($organizationId)->analyze(),
            'turnover' => $this->turnoverService->forOrganization($organizationId)->analyze(),
            default => $this->analyticsService->forOrganization($organizationId)->getDashboardKPIs(),
        };

        if ($format === 'json') {
            return response()->json($data)
                ->header('Content-Disposition', "attachment; filename={$type}_export.json");
        }

        // CSV export
        $csv = $this->convertToCsv($data, $type);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename={$type}_export.csv");
    }

    /**
     * Convert data to CSV format
     */
    protected function convertToCsv(array $data, string $type): string
    {
        $output = fopen('php://temp', 'r+');

        if ($type === 'abc' && isset($data['products'])) {
            // ABC Analysis export
            fputcsv($output, ['SKU', 'Name', 'Category', 'Brand', 'Value', '% of Total', 'Cumulative %', 'ABC Class']);
            foreach ($data['products'] as $product) {
                fputcsv($output, [
                    $product['sku'],
                    $product['name'],
                    $product['category'],
                    $product['brand'],
                    $product['metric_value'],
                    $product['percentage_of_total'],
                    $product['cumulative_percentage'],
                    $product['abc_class'],
                ]);
            }
        } elseif ($type === 'turnover' && isset($data['products'])) {
            // Turnover export
            fputcsv($output, ['SKU', 'Name', 'Category', 'Stock Qty', 'Units Sold', 'Turnover Ratio', 'DSI', 'Revenue', 'Profit']);
            foreach ($data['products'] as $product) {
                fputcsv($output, [
                    $product['sku'],
                    $product['name'],
                    $product['category'],
                    $product['stock_quantity'],
                    $product['units_sold'],
                    $product['annualized_turnover'],
                    $product['days_sales_inventory'],
                    $product['revenue'],
                    $product['profit'],
                ]);
            }
        } else {
            // Generic KPI export
            fputcsv($output, ['Metric', 'Value']);
            $this->flattenArrayToCsv($output, $data);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Recursively flatten array for CSV
     */
    protected function flattenArrayToCsv($handle, array $data, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && ! isset($value[0])) {
                $this->flattenArrayToCsv($handle, $value, $fullKey);
            } elseif (! is_array($value)) {
                fputcsv($handle, [$fullKey, $value]);
            }
        }
    }

    /**
     * Clear analytics cache
     */
    public function clearCache(Request $request)
    {
        if (! auth()->user()->hasPermission('analytics.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $organizationId = $this->getOrganizationId();

        $this->analyticsService->forOrganization($organizationId)->clearCache();
        $this->abcService->forOrganization($organizationId)->clearCache();
        $this->turnoverService->forOrganization($organizationId)->clearCache();

        return response()->json(['message' => 'Cache cleared successfully']);
    }
}
