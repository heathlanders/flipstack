<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductBundle;
use App\Models\Catalog\ProductBundleItem;
use App\Services\Catalog\BundleService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BundleController extends Controller
{
    public function __construct(
        protected BundleService $bundleService
    ) {
        $this->middleware(['auth']);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $query = ProductBundle::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with(['product:id,name,sku,selling_price,available_quantity'])
            ->withCount('items');

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'ILIKE', "%{$search}%")->orWhere('sku', 'ILIKE', "%{$search}%"));
            });
        }

        $bundles = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $stats = $this->bundleService->getStats($organizationId);

        return Inertia::render('Inventory/Bundles/Index', [
            'bundles' => $bundles,
            'stats' => $stats,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $products = Product::where('organization_id', $organizationId)
            ->where('is_bundle', false)
            ->select('id', 'name', 'sku', 'cost_price', 'selling_price', 'available_quantity')
            ->orderBy('name')
            ->limit(500)
            ->get();

        return Inertia::render('Inventory/Bundles/Create', [
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'bundle_type' => 'required|string|in:fixed,pick_n',
            'pick_n_quantity' => 'nullable|integer|min:1',
            'components' => 'required|array|min:1',
            'components.*.product_id' => 'required|uuid|exists:products,id',
            'components.*.quantity' => 'required|integer|min:1',
            'components.*.is_required' => 'boolean',
        ]);

        $bundle = $this->bundleService->create($validated, $organizationId);

        return redirect()->route('inventory.bundles.show', $bundle)
            ->with('success', 'Bundle created.');
    }

    public function show(ProductBundle $bundle): Response
    {
        $bundle->load(['product:id,name,sku,selling_price,available_quantity,cost_price', 'items.componentProduct:id,name,sku,available_quantity,cost_price']);

        $availableQuantity = $bundle->calculateAvailableQuantity();

        return Inertia::render('Inventory/Bundles/Show', [
            'bundle' => $bundle,
            'availableQuantity' => $availableQuantity,
        ]);
    }

    public function update(Request $request, ProductBundle $bundle)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
        ]);

        $bundle->update($validated);

        return redirect()->back()->with('success', 'Bundle updated.');
    }

    public function addComponent(Request $request, ProductBundle $bundle)
    {
        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'is_required' => 'boolean',
        ]);

        $maxSort = $bundle->items()->max('sort_order') ?? 0;

        ProductBundleItem::create([
            'bundle_id' => $bundle->id,
            'component_product_id' => $validated['product_id'],
            'quantity' => $validated['quantity'],
            'sort_order' => $maxSort + 1,
            'is_required' => $validated['is_required'] ?? true,
        ]);

        $this->bundleService->updateBundleAvailability($bundle);

        return redirect()->back()->with('success', 'Component added.');
    }

    public function removeComponent(ProductBundle $bundle, ProductBundleItem $item)
    {
        $item->delete();
        $this->bundleService->updateBundleAvailability($bundle);

        return redirect()->back()->with('success', 'Component removed.');
    }

    public function destroy(ProductBundle $bundle)
    {
        $product = $bundle->product;
        $bundle->items()->delete();
        $bundle->delete();

        if ($product) {
            $product->update(['is_bundle' => false]);
        }

        return redirect()->route('inventory.bundles.index')
            ->with('success', 'Bundle deleted.');
    }
}
