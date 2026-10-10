<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Marketplace\ScheduledListing;
use App\Services\Marketplace\ListingSchedulerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduledListingController extends Controller
{
    public function __construct(
        protected ListingSchedulerService $schedulerService
    ) {
        $this->middleware(['auth']);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $listings = ScheduledListing::where('organization_id', $organizationId)
            ->with(['product', 'integrationCredential', 'creator'])
            ->orderByDesc('scheduled_at')
            ->paginate(25);

        $stats = [
            'pending' => ScheduledListing::where('organization_id', $organizationId)
                ->where('status', ScheduledListing::STATUS_PENDING)->count(),
            'published' => ScheduledListing::where('organization_id', $organizationId)
                ->where('status', ScheduledListing::STATUS_PUBLISHED)->count(),
            'failed' => ScheduledListing::where('organization_id', $organizationId)
                ->where('status', ScheduledListing::STATUS_FAILED)->count(),
        ];

        return Inertia::render('Marketplace/ScheduledListings/Index', [
            'listings' => $listings,
            'stats' => $stats,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $products = Product::where('organization_id', $organizationId)
            ->select('id', 'name', 'sku')
            ->orderBy('name')
            ->get();

        $credentials = IntegrationCredential::where('organization_id', $organizationId)
            ->select('id', 'platform', 'label')
            ->where('is_active', true)
            ->orderBy('platform')
            ->get();

        return Inertia::render('Marketplace/ScheduledListings/Create', [
            'products' => $products,
            'credentials' => $credentials,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'marketplace' => 'required|string|in:ebay,shopify,amazon',
            'integration_credential_id' => 'required|integer|exists:integration_credentials,id',
            'scheduled_at' => 'required|date|after:now',
            'listing_data' => 'required|json',
        ]);

        $validated['listing_data'] = json_decode($validated['listing_data'], true);
        $validated['created_by'] = $user->id;

        $this->schedulerService->schedule($validated, $organizationId);

        return redirect()->route('marketplaces.scheduled-listings.index')
            ->with('success', 'Listing has been scheduled.');
    }

    public function cancel(ScheduledListing $scheduledListing)
    {
        $this->schedulerService->cancel($scheduledListing);

        return redirect()->back()->with('success', 'Scheduled listing has been cancelled.');
    }

    public function destroy(ScheduledListing $scheduledListing)
    {
        $scheduledListing->delete();

        return redirect()->back()->with('success', 'Scheduled listing has been deleted.');
    }
}
