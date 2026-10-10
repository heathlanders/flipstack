<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Services\Inventory\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        $query = Product::forOrganization($organizationId)
            ->with('category:id,name')
            ->select(['id', 'name', 'sku', 'cost_price', 'selling_price', 'msrp', 'status', 'category_id']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->orderBy('name')->paginate(50)->withQueryString();

        return Inertia::render('Inventory/Pricing/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'status', 'category_id']),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'string',
            'rules' => 'required|array',
            'rules.strategy' => 'required|string|in:fixed,percentage,cost_plus_fixed,cost_plus_percentage',
            'rules.value' => 'required|numeric',
            'rules.operation' => 'nullable|string|in:increase,decrease',
            'rules.price_field' => 'nullable|string|in:selling_price,cost_price,msrp',
            'rules.rounding' => 'nullable|string|in:nearest_99,nearest_95,nearest_dollar,up_dollar',
            'rules.min_price' => 'nullable|numeric|min:0',
            'rules.max_price' => 'nullable|numeric|min:0',
        ]);

        $previews = $this->pricingService->preview(
            $validated['product_ids'],
            $validated['rules']
        );

        return response()->json([
            'success' => true,
            'previews' => $previews,
            'count' => count($previews),
        ]);
    }

    public function apply(Request $request)
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.update')) {
            abort(403, 'You do not have permission to update prices.');
        }

        $validated = $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'string',
            'rules' => 'required|array',
            'rules.strategy' => 'required|string|in:fixed,percentage,cost_plus_fixed,cost_plus_percentage',
            'rules.value' => 'required|numeric',
            'rules.operation' => 'nullable|string|in:increase,decrease',
            'rules.price_field' => 'nullable|string|in:selling_price,cost_price,msrp',
            'rules.rounding' => 'nullable|string|in:nearest_99,nearest_95,nearest_dollar,up_dollar',
            'rules.min_price' => 'nullable|numeric|min:0',
            'rules.max_price' => 'nullable|numeric|min:0',
        ]);

        $result = $this->pricingService->apply(
            $validated['product_ids'],
            $validated['rules'],
            $user
        );

        return back()->with('success', "Prices updated: {$result['updated']} products changed, {$result['skipped']} skipped.");
    }
}
