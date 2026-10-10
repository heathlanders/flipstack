<?php

namespace App\Http\Controllers\Marketplaces;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceHubController extends Controller
{
    public function __construct(
        protected MarketplaceStatsService $statsService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        $stats = $this->statsService->getOverviewStats($organizationId);

        return Inertia::render('Marketplaces/Index', [
            'stats' => $stats,
        ]);
    }

    public function listings(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        $filters = $request->only(['search', 'platform']);
        $perPage = $request->input('per_page', 20);

        $listings = $this->statsService->getCrossplatformListings($organizationId, $filters, $perPage);

        return Inertia::render('Marketplaces/Listings', [
            'listings' => $listings,
            'filters' => $filters,
        ]);
    }

    protected function authorizeAdmin($user): void
    {
        $roles = $user->roles ? $user->roles->pluck('slug')->toArray() : [];
        $allowed = ['super_admin', 'organization_owner', 'admin'];

        if (! array_intersect($roles, $allowed)) {
            abort(403, 'You do not have permission to access this page.');
        }
    }
}
