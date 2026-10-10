<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Jobs\Analytics\GenerateDemandForecasts;
use App\Jobs\Analytics\GenerateReorderSuggestions;
use App\Models\Analytics\ReorderSuggestion;
use App\Services\Analytics\DemandForecastingService;
use App\Services\Analytics\ReorderSuggestionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ForecastController extends Controller
{
    public function __construct(
        protected DemandForecastingService $forecastingService,
        protected ReorderSuggestionService $reorderService
    ) {
        $this->middleware(['auth']);
    }

    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $dashboardData = $this->forecastingService->getDashboardData($organizationId);

        return Inertia::render('Analytics/Forecasting/Dashboard', [
            'stats' => $dashboardData,
        ]);
    }

    public function suggestions(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $query = ReorderSuggestion::where('organization_id', $organizationId)
            ->with(['product:id,name,sku', 'supplier:id,name'])
            ->byPriority()
            ->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $suggestions = $query->paginate(25)->withQueryString();

        return Inertia::render('Analytics/Forecasting/ReorderSuggestions', [
            'suggestions' => $suggestions,
            'filters' => $request->only(['status', 'priority']),
        ]);
    }

    public function accept(ReorderSuggestion $suggestion)
    {
        $this->reorderService->accept($suggestion);

        return redirect()->back()->with('success', 'Suggestion accepted.');
    }

    public function dismiss(ReorderSuggestion $suggestion)
    {
        $this->reorderService->dismiss($suggestion);

        return redirect()->back()->with('success', 'Suggestion dismissed.');
    }

    public function generate(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        GenerateDemandForecasts::dispatch($organizationId);
        GenerateReorderSuggestions::dispatch($organizationId);

        return redirect()->back()->with('success', 'Forecast generation has been queued.');
    }
}
