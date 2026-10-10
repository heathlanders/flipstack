<?php

namespace App\Http\Controllers\Marketplaces;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Inventory\Supplier;
use App\Services\SkuResolutionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SkuResolutionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $orgId = $user->organization_id;

        $query = Product::where('organization_id', $orgId)
            ->whereHas('ebayListing')
            ->whereDoesntHave('supplierProducts')
            ->with(['ebayListing:id,product_id,ebay_listing_id,ebay_sku']);

        // Filter by SKU prefix
        if ($prefix = $request->input('prefix')) {
            $query->where('sku', 'like', $prefix.'%');
        }

        // Search by SKU or name
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        $products = $query->orderBy('sku')
            ->paginate(50)
            ->withQueryString();

        // Get prefix breakdown for filter sidebar
        $prefixCounts = Product::where('organization_id', $orgId)
            ->whereHas('ebayListing')
            ->whereDoesntHave('supplierProducts')
            ->selectRaw("SPLIT_PART(sku, ' ', 1) as prefix, COUNT(*) as count")
            ->groupByRaw("SPLIT_PART(sku, ' ', 1)")
            ->orderByDesc('count')
            ->limit(30)
            ->pluck('count', 'prefix');

        // Get existing suppliers for autocomplete
        $suppliers = Supplier::where('organization_id', $orgId)
            ->orderBy('code')
            ->pluck('code');

        // Total unresolved count
        $totalUnresolved = Product::where('organization_id', $orgId)
            ->whereHas('ebayListing')
            ->whereDoesntHave('supplierProducts')
            ->count();

        return Inertia::render('Marketplaces/Ebay/SkuResolution', [
            'products' => $products,
            'prefixCounts' => $prefixCounts,
            'suppliers' => $suppliers,
            'totalUnresolved' => $totalUnresolved,
            'filters' => [
                'prefix' => $prefix,
                'search' => $search,
            ],
        ]);
    }

    public function resolve(Request $request, Product $product)
    {
        $user = $request->user();

        if ($product->organization_id !== $user->organization_id) {
            abort(403);
        }

        $validated = $request->validate([
            'owner' => 'nullable|string|max:100',
            'internal_sku' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
        ]);

        $service = new SkuResolutionService;
        $service->resolve($product, $validated);

        return back()->with('success', 'SKU resolved successfully.');
    }

    public function bulkResolve(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'prefix' => 'required|string|max:50',
            'owner' => 'required|string|max:100',
        ]);

        $service = new SkuResolutionService;
        $count = $service->bulkResolveByPrefix(
            $user->organization_id,
            $validated['prefix'],
            $validated['owner']
        );

        return back()->with('success', "{$count} products resolved with owner '{$validated['owner']}'.");
    }
}
