<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\ProfitLossService;
use App\Services\Marketplace\FeeTrackingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfitLossController extends Controller
{
    public function __construct(
        protected ProfitLossService $profitLossService,
        protected FeeTrackingService $feeTrackingService
    ) {
        $this->middleware(['auth']);
    }

    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $service = $this->profitLossService
            ->forOrganization($organizationId)
            ->dateRange($startDate, $endDate);

        $summary = $service->getSummary();
        $byMarketplace = $service->getByMarketplace();
        $dailyTrend = $service->getDailyTrend();
        $topProducts = $service->getTopProducts(10);
        $feeSummary = $this->feeTrackingService->getFeeSummary($organizationId, $startDate, $endDate);

        return Inertia::render('Analytics/ProfitLoss/Dashboard', [
            'summary' => $summary,
            'byMarketplace' => $byMarketplace,
            'dailyTrend' => $dailyTrend,
            'topProducts' => $topProducts,
            'feeSummary' => $feeSummary,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }
}
