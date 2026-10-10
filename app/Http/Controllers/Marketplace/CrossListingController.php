<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Services\Marketplace\CrossListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrossListingController extends Controller
{
    public function __construct(
        protected CrossListingService $crossListingService
    ) {
        $this->middleware(['auth']);
    }

    /**
     * Preview a cross-listing transformation.
     */
    public function preview(Request $request): Response
    {
        $request->validate([
            'product_id' => 'required|string|exists:products,id',
            'source' => 'required|string|in:ebay,shopify',
            'target' => 'required|string|in:ebay,shopify',
        ]);

        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        // Verify product belongs to organization
        $product = Product::where('id', $request->product_id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $previewData = $this->crossListingService->preview(
            $request->product_id,
            $request->source,
            $request->target
        );

        // Get available credentials for the target marketplace
        $credentials = IntegrationCredential::where('organization_id', $organizationId)
            ->where('platform', $request->target)
            ->where('is_active', true)
            ->get()
            ->map(fn ($cred) => [
                'id' => $cred->id,
                'name' => $cred->name,
                'platform' => $cred->platform,
                'seller_username' => $cred->seller_username,
                'environment' => $cred->environment,
            ]);

        return Inertia::render('Marketplace/CrossList/Preview', [
            'sourceData' => $previewData['sourceData'],
            'targetData' => $previewData['targetData'],
            'product' => $previewData['product'],
            'sourceMarketplace' => $request->source,
            'targetMarketplace' => $request->target,
            'credentials' => $credentials,
        ]);
    }

    /**
     * Execute a cross-listing operation.
     */
    public function execute(Request $request)
    {
        $request->validate([
            'listing_data' => 'required|array',
            'listing_data.product_id' => 'required|string|exists:products,id',
            'listing_data.title' => 'required|string|max:255',
            'listing_data.price' => 'required|numeric|min:0.01',
            'target' => 'required|string|in:ebay,shopify',
            'credential_id' => 'required|integer|exists:integration_credentials,id',
        ]);

        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        // Verify product belongs to organization
        Product::where('id', $request->input('listing_data.product_id'))
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Verify credential belongs to organization
        IntegrationCredential::where('id', $request->credential_id)
            ->where('organization_id', $organizationId)
            ->where('platform', $request->target)
            ->where('is_active', true)
            ->firstOrFail();

        $listingData = $request->listing_data;

        // Apply price if overridden
        if ($request->target === 'ebay') {
            $listingData['listed_price'] = $listingData['price'];
        }

        $this->crossListingService->execute(
            $listingData,
            $request->target,
            $request->credential_id
        );

        $targetLabel = ucfirst($request->target);

        return redirect()
            ->route('marketplaces.cross-list.preview', [
                'product_id' => $listingData['product_id'],
                'source' => $request->input('source', $request->target === 'ebay' ? 'shopify' : 'ebay'),
                'target' => $request->target,
            ])
            ->with('success', "Listing has been queued for creation on {$targetLabel}.");
    }

    /**
     * API endpoint returning products with existing listings for search/selection.
     */
    public function products(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $search = $request->input('search', '');
        $marketplace = $request->input('marketplace');
        $perPage = min($request->input('per_page', 20), 50);

        $query = Product::where('organization_id', $organizationId)
            ->where('status', 'active');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%")
                    ->orWhere('barcode', 'ilike', "%{$search}%");
            });
        }

        // Filter to products that have listings on the specified marketplace
        if ($marketplace === 'ebay') {
            $query->whereHas('ebayListings', function ($q) {
                $q->whereNotIn('status', ['deleted']);
            });
        } elseif ($marketplace === 'shopify') {
            $query->whereHas('shopifyListings', function ($q) {
                $q->whereNotIn('status', ['deleted']);
            });
        } else {
            // By default show products with any listing
            $query->where(function ($q) {
                $q->whereHas('ebayListings', function ($sub) {
                    $sub->whereNotIn('status', ['deleted']);
                })->orWhereHas('shopifyListings', function ($sub) {
                    $sub->whereNotIn('status', ['deleted']);
                });
            });
        }

        $products = $query
            ->select(['id', 'name', 'sku', 'selling_price', 'cost_price', 'featured_image', 'condition', 'available_quantity'])
            ->withCount([
                'ebayListings as ebay_listing_count' => function ($q) {
                    $q->whereNotIn('status', ['deleted']);
                },
                'shopifyListings as shopify_listing_count' => function ($q) {
                    $q->whereNotIn('status', ['deleted']);
                },
            ])
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($products);
    }
}
