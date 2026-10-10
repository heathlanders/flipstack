<?php

namespace App\Http\Controllers\Ebay;

use App\Http\Controllers\Controller;
use App\Jobs\Ebay\SyncPromotedListingStats;
use App\Models\Ebay\EbayListing;
use App\Models\Ebay\EbayPromotedCampaign;
use App\Models\Ebay\EbayPromotedListing;
use App\Models\Integration\IntegrationCredential;
use App\Services\Ebay\EbayPromotedListingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromotedListingsController extends Controller
{
    public function __construct(
        protected EbayPromotedListingsService $service
    ) {
        $this->middleware(['auth']);
    }

    public function dashboard(Request $request): Response
    {
        $organizationId = $this->getOrganizationId($request);

        $stats = $this->service->getDashboardStats($organizationId);

        $recentCampaigns = EbayPromotedCampaign::where('organization_id', $organizationId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('Ebay/PromotedListings/Dashboard', [
            'stats' => $stats,
            'recentCampaigns' => $recentCampaigns,
        ]);
    }

    public function campaigns(Request $request): Response
    {
        $organizationId = $this->getOrganizationId($request);

        $campaigns = EbayPromotedCampaign::where('organization_id', $organizationId)
            ->orderByDesc('created_at')
            ->paginate(20);

        $credentials = IntegrationCredential::where('organization_id', $organizationId)
            ->where('platform', 'ebay')
            ->where('is_active', true)
            ->get(['id', 'name']);

        return Inertia::render('Ebay/PromotedListings/Campaigns', [
            'campaigns' => $campaigns,
            'credentials' => $credentials,
        ]);
    }

    public function show(Request $request, EbayPromotedCampaign $campaign): Response
    {
        $organizationId = $this->getOrganizationId($request);

        if ($campaign->organization_id != $organizationId) {
            abort(403);
        }

        $campaign->load('integrationCredential');

        $listings = EbayPromotedListing::where('campaign_id', $campaign->id)
            ->with(['ebayListing', 'product'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $availableListings = EbayListing::whereHas('product', function ($q) use ($organizationId) {
            $q->where('organization_id', $organizationId);
        })
            ->where('status', 'ACTIVE')
            ->get(['id', 'ebay_listing_id', 'ebay_sku', 'listed_price']);

        return Inertia::render('Ebay/PromotedListings/CampaignDetail', [
            'campaign' => $campaign,
            'listings' => $listings,
            'availableListings' => $availableListings,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organizationId = $this->getOrganizationId($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'campaign_type' => 'required|in:standard,advanced',
            'integration_credential_id' => 'required|exists:integration_credentials,id',
            'budget_daily' => 'nullable|numeric|min:0',
            'budget_total' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $this->service->createCampaign($validated, $organizationId);

        return redirect()->route('marketplaces.ebay.promoted.campaigns')
            ->with('success', 'Campaign created successfully.');
    }

    public function pause(Request $request, EbayPromotedCampaign $campaign): RedirectResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if ($campaign->organization_id != $organizationId) {
            abort(403);
        }

        $this->service->pauseCampaign($campaign);

        return back()->with('success', 'Campaign paused.');
    }

    public function resume(Request $request, EbayPromotedCampaign $campaign): RedirectResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if ($campaign->organization_id != $organizationId) {
            abort(403);
        }

        $this->service->resumeCampaign($campaign);

        return back()->with('success', 'Campaign resumed.');
    }

    public function end(Request $request, EbayPromotedCampaign $campaign): RedirectResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if ($campaign->organization_id != $organizationId) {
            abort(403);
        }

        $this->service->endCampaign($campaign);

        return back()->with('success', 'Campaign ended.');
    }

    public function addListing(Request $request, EbayPromotedCampaign $campaign): RedirectResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if ($campaign->organization_id != $organizationId) {
            abort(403);
        }

        $validated = $request->validate([
            'ebay_listing_id' => 'required|exists:ebay_listings,id',
            'ad_rate_percentage' => 'required|numeric|min:0|max:100',
        ]);

        $this->service->addListingToCampaign(
            $campaign,
            (int) $validated['ebay_listing_id'],
            (float) $validated['ad_rate_percentage']
        );

        return back()->with('success', 'Listing added to campaign.');
    }

    public function removeListing(Request $request, EbayPromotedListing $listing): RedirectResponse
    {
        $campaign = $listing->campaign;
        $organizationId = $this->getOrganizationId($request);

        if ($campaign->organization_id != $organizationId) {
            abort(403);
        }

        $this->service->removeListingFromCampaign($listing);

        return back()->with('success', 'Listing removed from campaign.');
    }

    public function syncStats(Request $request, EbayPromotedCampaign $campaign): JsonResponse
    {
        $organizationId = $this->getOrganizationId($request);

        if ($campaign->organization_id != $organizationId) {
            abort(403);
        }

        SyncPromotedListingStats::dispatch($campaign->id);

        return response()->json([
            'success' => true,
            'message' => 'Stats sync job dispatched.',
        ]);
    }

    protected function getOrganizationId(Request $request): string
    {
        $user = $request->user();

        return $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;
    }
}
