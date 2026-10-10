<?php

namespace App\Http\Controllers\Marketplaces;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\MarketplaceSyncRule;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceSyncRuleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $organizationId = $user->organization_id;

        $rules = MarketplaceSyncRule::forOrganization($organizationId)
            ->ordered()
            ->get();

        $categories = Category::where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $brands = Brand::where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $orgUsers = User::where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Marketplaces/SyncRules', [
            'rules' => $rules,
            'categories' => $categories,
            'brands' => $brands,
            'orgUsers' => $orgUsers,
            'conditionOptions' => Product::getConditionOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'marketplace' => 'required|in:ebay,shopify,*',
            'action' => 'required|in:include,exclude',
            'priority' => 'required|integer|min:0',
            'conditions' => 'required|array',
            'conditions.*.field' => 'required|string|in:category_id,condition,selling_price,brand_id,created_by',
            'conditions.*.operator' => 'required|string|in:in,not_in,between,greater_than,less_than',
            'conditions.*.value' => 'required',
            'is_active' => 'boolean',
        ]);

        $rule = MarketplaceSyncRule::create([
            'organization_id' => $user->organization_id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'marketplace' => $validated['marketplace'],
            'action' => $validated['action'],
            'priority' => $validated['priority'],
            'conditions' => $validated['conditions'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    public function update(Request $request, MarketplaceSyncRule $rule): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAdmin($user);
        $this->authorizeRule($user, $rule);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'marketplace' => 'required|in:ebay,shopify,*',
            'action' => 'required|in:include,exclude',
            'priority' => 'required|integer|min:0',
            'conditions' => 'required|array',
            'conditions.*.field' => 'required|string|in:category_id,condition,selling_price,brand_id,created_by',
            'conditions.*.operator' => 'required|string|in:in,not_in,between,greater_than,less_than',
            'conditions.*.value' => 'required',
            'is_active' => 'boolean',
        ]);

        $rule->update($validated);

        return response()->json(['success' => true, 'rule' => $rule->fresh()]);
    }

    public function destroy(Request $request, MarketplaceSyncRule $rule): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAdmin($user);
        $this->authorizeRule($user, $rule);

        $rule->delete();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $validated = $request->validate([
            'rules' => 'required|array',
            'rules.*.id' => 'required|integer',
            'rules.*.priority' => 'required|integer|min:0',
        ]);

        $organizationId = $user->organization_id;

        foreach ($validated['rules'] as $item) {
            MarketplaceSyncRule::where('id', $item['id'])
                ->where('organization_id', $organizationId)
                ->update(['priority' => $item['priority']]);
        }

        return response()->json(['success' => true]);
    }

    private function authorizeRule($user, MarketplaceSyncRule $rule): void
    {
        if ($rule->organization_id !== $user->organization_id) {
            abort(403, 'You do not have permission to modify this rule.');
        }
    }

    private function authorizeAdmin($user): void
    {
        $roles = $user->roles ? $user->roles->pluck('slug')->toArray() : [];
        $allowed = ['super_admin', 'organization_owner', 'admin'];

        if (! array_intersect($roles, $allowed)) {
            abort(403, 'You do not have permission to access this page.');
        }
    }
}
