<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Services\Analytics\BusinessHealthScoreService;
use App\Services\Analytics\CommunicationAnalyticsService;
use App\Services\Analytics\CustomerAnalyticsService;
use App\Services\Analytics\ErrorAttributionService;
use App\Services\Analytics\ExecutiveAnalyticsService;
use App\Services\Analytics\FulfillmentAnalyticsService;
use App\Services\Analytics\LaborCostService;
use App\Services\Analytics\LiveOpsAnalyticsService;
use App\Services\Analytics\MarketingAnalyticsService;
use App\Services\Analytics\MarketplaceHealthService;
use App\Services\Analytics\PricingAnalyticsService;
use App\Services\Analytics\ProcurementAnalyticsService;
use App\Services\Analytics\UserProductivityService;
use App\Services\Analytics\UserTargetService;
use App\Services\Analytics\WorkforceAnalyticsService;
use App\Services\Analytics\WorkforceCompensationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsHubController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    protected function getOrganizationId(Request $request): string
    {
        $user = $request->user();

        return $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;
    }

    protected function getDateFilters(Request $request): array
    {
        return [
            'start_date' => $request->get('start_date', now()->subDays(29)->toDateString()),
            'end_date' => $request->get('end_date', now()->toDateString()),
            'compare_start_date' => $request->get('compare_start_date'),
            'compare_end_date' => $request->get('compare_end_date'),
        ];
    }

    protected function configureService(object $service, string $orgId, array $filters): object
    {
        $service->forOrganization($orgId)->dateRange($filters['start_date'], $filters['end_date']);

        if ($filters['compare_start_date'] && $filters['compare_end_date']) {
            $service->compareTo($filters['compare_start_date'], $filters['compare_end_date']);
        }

        return $service;
    }

    public function index(Request $request, BusinessHealthScoreService $healthService): Response
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $this->getDateFilters($request);

        $healthService->forOrganization($orgId)->dateRange($filters['start_date'], $filters['end_date']);

        return Inertia::render('Analytics/Hub/Index', [
            'healthScore' => $healthService->calculate(),
            'filters' => $filters,
        ]);
    }

    public function executive(Request $request, ExecutiveAnalyticsService $executiveService, BusinessHealthScoreService $healthService): Response
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $this->getDateFilters($request);

        $this->configureService($executiveService, $orgId, $filters);
        $healthService->forOrganization($orgId)->dateRange($filters['start_date'], $filters['end_date']);

        return Inertia::render('Analytics/Hub/Executive', [
            'overview' => $executiveService->getOverview(),
            'revenueByMarketplace' => $executiveService->getRevenueByMarketplace(),
            'revenueByCategory' => $executiveService->getRevenueByCategory(),
            'revenueTrend' => $executiveService->getRevenueTrend(),
            'topProducts' => $executiveService->getTopProducts(),
            'healthScore' => $healthService->calculate(),
            'healthHistory' => $healthService->getHistorical(30),
            'filters' => $filters,
        ]);
    }

    public function fulfillment(Request $request, FulfillmentAnalyticsService $service): Response
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $this->getDateFilters($request);

        $this->configureService($service, $orgId, $filters);

        return Inertia::render('Analytics/Hub/Fulfillment', [
            'overview' => $service->getOverview(),
            'orderToShipTime' => $service->getOrderToShipTime(),
            'onTimeRate' => $service->getOnTimeDeliveryRate(),
            'pickAccuracy' => $service->getPickAccuracy(),
            'backlogAge' => $service->getBacklogAge(),
            'returnMetrics' => $service->getReturnMetrics(),
            'carrierPerformance' => $service->getCarrierPerformance(),
            'timeInStage' => $service->getTimeInStage(),
            'processingTrend' => $service->getProcessingTrend(),
            'filters' => $filters,
        ]);
    }

    public function compensation(Request $request, WorkforceCompensationService $service): Response
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $this->getDateFilters($request);
        $authUser = $request->user();

        $isManager = $authUser->hasRole(['super_admin', 'organization_owner', 'admin', 'manager']);
        if (! $isManager) {
            abort(403, 'Only managers can view compensation analytics.');
        }

        $this->configureService($service, $orgId, $filters);

        return Inertia::render('Analytics/Hub/Compensation', [
            'overview' => $service->getOverview(),
            'userSummary' => $service->getUserSettlementSummary(),
            'commissionAttribution' => $service->getCommissionAttribution(),
            'tierBreakdown' => $service->getTierBonusBreakdown(),
            'filters' => $filters,
        ]);
    }

    public function userDetail(Request $request, User $user, WorkforceAnalyticsService $service, UserTargetService $targetService, LaborCostService $costService): Response
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $this->getDateFilters($request);

        if ((string) $user->organization_id !== (string) $orgId) {
            abort(404);
        }

        $authUser = $request->user();
        $isManager = $authUser->hasRole(['super_admin', 'organization_owner', 'admin', 'manager']);
        if (! $isManager && (int) $authUser->id !== (int) $user->id) {
            abort(403, 'You can only view your own analytics.');
        }

        $this->configureService($service, $orgId, $filters);
        $targetService->forOrganization($orgId)->dateRange($filters['start_date'], $filters['end_date']);
        $costService->forOrganization($orgId)->dateRange($filters['start_date'], $filters['end_date']);

        return Inertia::render('Analytics/Hub/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->profile_photo_url,
            ],
            'kpis' => $service->getUserAggregatedKPIs((int) $user->id),
            'productivityTrend' => $service->getProductivityTrend((int) $user->id),
            'timeline' => $service->getUserActivityTimeline((int) $user->id, 100),
            'heatmap' => $service->getUserActivityHeatmap((int) $user->id),
            'concurrentUsers' => $isManager ? $service->getConcurrentUsersByHour() : [],
            'ranking' => $service->getUserPercentileRanking((int) $user->id, $request->get('rank_metric', 'items_picked')),
            'teamDeltas' => $service->getTeamVsIndividualDelta((int) $user->id),
            'errorTrend' => $service->getUserErrorTrend((int) $user->id),
            'attainment' => $targetService->getAttainmentByUser((int) $user->id),
            'laborCost' => $costService->getCostForUser((int) $user->id),
            'sessions' => $service->getUserSessionStats((int) $user->id),
            'sessionAggregates' => $service->getUserSessionAggregates((int) $user->id),
            'rankMetric' => $request->get('rank_metric', 'items_picked'),
            'isManager' => $isManager,
            'filters' => $filters,
        ]);
    }

    public function workforce(Request $request, WorkforceAnalyticsService $service, ErrorAttributionService $attribution, LaborCostService $costService, UserProductivityService $productivity): Response
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $this->getDateFilters($request);
        $user = $request->user();

        $this->configureService($service, $orgId, $filters);
        $attribution->forOrganization($orgId)->dateRange($filters['start_date'], $filters['end_date']);
        $costService->forOrganization($orgId)->dateRange($filters['start_date'], $filters['end_date']);
        $productivity->forOrganization((int) $orgId)->dateRange($filters['start_date'], $filters['end_date']);

        $isManager = $user->hasRole(['super_admin', 'organization_owner', 'admin', 'manager']);
        $userId = $request->get('user_id');

        return Inertia::render('Analytics/Hub/Workforce', [
            'teamOverview' => $service->getTeamOverview(),
            'leaderboard' => $isManager ? $service->getLeaderboard('items_picked', 15) : [],
            'individualPerformance' => $service->getIndividualPerformance($isManager ? $userId : $user->id),
            'productivityTrend' => $service->getProductivityTrend($isManager ? $userId : $user->id),
            'errorRates' => $isManager ? $service->getErrorRates() : [],
            'teamComparison' => $isManager ? $service->getTeamComparison() : [],
            'weekComparison' => $service->getPeriodComparison('week'),
            'monthComparison' => $service->getPeriodComparison('month'),
            'attributionOverview' => $isManager ? $attribution->getOverview() : null,
            'returnsByPacker' => $isManager ? $attribution->getReturnRateByPacker() : [],
            'cancellationByHandler' => $isManager ? $attribution->getCancellationRateByHandler() : [],
            'refundsByLister' => $isManager ? $attribution->getRefundRateByLister() : [],
            'laborCostOverview' => $isManager ? $costService->getOverview() : null,
            'laborCostByUser' => $isManager ? $costService->getCostByUser() : [],
            'teamMembers' => $isManager ? $service->getAllOrgUsersWithStats() : [],
            'auditProductivity' => $isManager ? [
                'per_user_totals' => $productivity->perUserTotals(),
                'leaderboards' => [
                    'products_listed' => $productivity->leaderboard('products_listed_count', 10),
                    'pos_created' => $productivity->leaderboard('pos_created_count', 10),
                    'messages_answered' => $productivity->leaderboard('messages_answered_count', 10),
                    'listings_published_ebay' => $productivity->leaderboard('listings_published_ebay', 10),
                ],
                'trends' => [
                    'products_listed' => $productivity->dailyTotalsFor('products_listed_count'),
                    'messages_answered' => $productivity->dailyTotalsFor('messages_answered_count'),
                ],
            ] : null,
            'isManager' => $isManager,
            'selectedUserId' => $userId,
            'filters' => $filters,
        ]);
    }

    public function liveOps(Request $request, LiveOpsAnalyticsService $service): Response
    {
        $orgId = $this->getOrganizationId($request);
        $user = $request->user();
        $isManager = $user->hasRole(['super_admin', 'organization_owner', 'admin', 'manager']);

        $service->forOrganization($orgId);

        return Inertia::render('Analytics/Hub/Live', [
            'todaySummary' => $service->getTodaySummary(),
            'ordersInFlight' => $service->getOrdersInFlight(),
            'hourlyTrend' => $service->getHourlyTrend(),
            'activeUsers' => $isManager ? $service->getActiveUsers() : [],
            'perUserToday' => $isManager ? $service->getPerUserToday() : [],
            'recentActivity' => $isManager ? $service->getRecentActivity(50) : [],
            'isManager' => $isManager,
            'serverTime' => now()->toIso8601String(),
        ]);
    }

    public function procurement(Request $request, ProcurementAnalyticsService $service): Response
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $this->getDateFilters($request);

        $this->configureService($service, $orgId, $filters);

        return Inertia::render('Analytics/Hub/Procurement', [
            'overview' => $service->getOverview(),
            'supplierLeadTime' => $service->getSupplierLeadTime(),
            'supplierOnTimeRate' => $service->getSupplierOnTimeRate(),
            'receivingAccuracy' => $service->getReceivingAccuracy(),
            'costTrends' => $service->getCostTrends(),
            'spendBySupplier' => $service->getSpendBySupplier(),
            'supplierRanking' => $service->getSupplierRanking(),
            'filters' => $filters,
        ]);
    }

    public function marketplace(Request $request, MarketplaceHealthService $service): Response
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $this->getDateFilters($request);

        $this->configureService($service, $orgId, $filters);

        return Inertia::render('Analytics/Hub/Marketplace', [
            'overview' => $service->getOverview(),
            'syncHealth' => $service->getSyncHealth(),
            'listingHealth' => $service->getListingHealth(),
            'responseTimeTrend' => $service->getResponseTimeTrend(),
            'errorBreakdown' => $service->getErrorBreakdown(),
            'feeAnalysis' => $service->getFeeAnalysis(),
            'crossMarketplace' => $service->getCrossMarketplaceComparison(),
            'filters' => $filters,
        ]);
    }

    public function customer(Request $request, CustomerAnalyticsService $service): Response
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $this->getDateFilters($request);

        $this->configureService($service, $orgId, $filters);

        return Inertia::render('Analytics/Hub/Customers', [
            'overview' => $service->getOverview(),
            'ltvDistribution' => $service->getLTVDistribution(),
            'repeatRate' => $service->getRepeatPurchaseRate(),
            'aovTrend' => $service->getAOVTrend(),
            'acquisitionByMarketplace' => $service->getAcquisitionByMarketplace(),
            'topCustomers' => $service->getTopCustomers(),
            'segments' => $service->getCustomerSegments(),
            'filters' => $filters,
        ]);
    }

    public function communication(Request $request, CommunicationAnalyticsService $service): Response
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $this->getDateFilters($request);

        $this->configureService($service, $orgId, $filters);

        return Inertia::render('Analytics/Hub/Communication', [
            'overview' => $service->getOverview(),
            'responseTime' => $service->getResponseTime(),
            'slaCompliance' => $service->getSLACompliance(),
            'volumeTrend' => $service->getMessageVolumeTrend(),
            'unreadBacklog' => $service->getUnreadBacklog(),
            'byMarketplace' => $service->getByMarketplace(),
            'filters' => $filters,
        ]);
    }

    public function marketing(Request $request, MarketingAnalyticsService $service): Response
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $this->getDateFilters($request);

        $this->configureService($service, $orgId, $filters);

        return Inertia::render('Analytics/Hub/Marketing', [
            'overview' => $service->getOverview(),
            'campaignPerformance' => $service->getCampaignPerformance(),
            'deliverability' => $service->getDeliverability(),
            'engagementTrend' => $service->getEngagementTrend(),
            'filters' => $filters,
        ]);
    }

    public function pricing(Request $request, PricingAnalyticsService $service): Response
    {
        $orgId = $this->getOrganizationId($request);
        $filters = $this->getDateFilters($request);

        $this->configureService($service, $orgId, $filters);

        return Inertia::render('Analytics/Hub/Pricing', [
            'overview' => $service->getOverview(),
            'repricingFrequency' => $service->getRepricingFrequency(),
            'marginImpact' => $service->getMarginImpact(),
            'competitivePosition' => $service->getCompetitivePosition(),
            'rulePerformance' => $service->getRulePerformance(),
            'filters' => $filters,
        ]);
    }
}
