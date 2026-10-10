<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\BulkProductRequest;
use App\Http\Requests\Inventory\FilterPresetRequest;
use App\Http\Requests\Inventory\ProductFilterRequest;
use App\Http\Resources\ProductResource;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\InventoryLocation;
use App\Models\System\SavedSearch;
use App\Services\Inventory\ProductDeletionService;
use App\Services\Inventory\ProductExportService;
use App\Services\Inventory\ProductService;
use App\Services\MarketplaceSyncRuleEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService,
        protected ProductExportService $exportService,
        protected ProductDeletionService $deletionService,
        protected MarketplaceSyncRuleEngine $ruleEngine = new MarketplaceSyncRuleEngine
    ) {}

    /**
     * Display a listing of products with filters.
     */
    public function index(ProductFilterRequest $request): Response
    {
        $user = Auth::user();
        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        $showArchived = $request->input('status') === 'archived';

        // Build base query with organization scope
        // When viewing archived products, use onlyTrashed() to show soft-deleted records
        $query = Product::forOrganization($organizationId)
            ->with(['category', 'brand', 'images', 'creator']);

        if ($showArchived) {
            $query->onlyTrashed();
        }

        // Apply filters
        $query = $this->applyFilters($query, $request);

        // Apply search
        $query = $this->applySearch($query, $request);

        // Apply sorting
        $query = $this->applySorting($query, $request);

        // Paginate results
        $perPage = $request->input('per_page', 20);
        $products = $query->paginate($perPage)->withQueryString();

        // Get filter options
        $filterOptions = $this->getFilterOptions($organizationId);

        // Get saved searches for this user
        $savedSearches = SavedSearch::where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->where('type', 'products')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        // Get product stats
        $stats = $this->getProductStats($organizationId);

        // Build permissions array
        $canDelete = $user->hasPermission('inventory.delete');
        $permissions = [
            'can_create' => $user->hasPermission('inventory.create'),
            'can_update' => $user->hasPermission('inventory.update'),
            'can_delete' => $canDelete,
            'can_restore' => $canDelete,
            'can_force_delete' => $canDelete,
            'can_export' => $user->hasPermission('inventory.export'),
            'can_import' => $user->hasPermission('inventory.import'),
            'can_manage_categories' => $user->hasPermission('inventory.manage_categories'),
            'can_view_all_locations' => $user->hasPermission('inventory.view'),
        ];

        return Inertia::render('Inventory/Products/Index', [
            'products' => ProductResource::collection($products),
            'filters' => $request->validated(),
            'filterOptions' => $filterOptions,
            'savedSearches' => $savedSearches,
            'permissions' => $permissions,
            'stats' => $stats,
            'viewMode' => $request->input('view_mode', 'grid'),
            'showArchived' => $showArchived,
        ]);
    }

    /**
     * Get enhanced filter options for the API.
     */
    public function getFilterOptionsApi(Request $request): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        $filterOptions = $this->getFilterOptions($organizationId, true);

        return response()->json([
            'filterOptions' => $filterOptions,
            'permissions' => [
                'can_view_all_locations' => $user->hasPermission('inventory.view'),
                'can_manage_categories' => $user->hasPermission('inventory.manage_categories'),
            ],
        ]);
    }

    /**
     * Get quick statistics for the filter bar.
     */
    public function getQuickStats(Request $request): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        $stats = DB::table('products')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active"),
                DB::raw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft"),
            ])
            ->first();

        $archivedCount = DB::table('products')
            ->where('organization_id', $organizationId)
            ->whereNotNull('deleted_at')
            ->count();

        $stockStats = DB::table('products')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->select([
                DB::raw('SUM(CASE WHEN stock_quantity > low_stock_threshold THEN 1 ELSE 0 END) as in_stock'),
                DB::raw('SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= low_stock_threshold THEN 1 ELSE 0 END) as low_stock'),
                DB::raw('SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock'),
                DB::raw('SUM(CASE WHEN stock_quantity > (low_stock_threshold * 3) THEN 1 ELSE 0 END) as overstocked'),
            ])
            ->first();

        return response()->json([
            'stats' => [
                'total' => (int) $stats->total,
                'byStatus' => [
                    'active' => (int) $stats->active,
                    'draft' => (int) $stats->draft,
                    'archived' => $archivedCount,
                ],
                'byStock' => [
                    'in_stock' => (int) $stockStats->in_stock,
                    'low_stock' => (int) $stockStats->low_stock,
                    'out_of_stock' => (int) $stockStats->out_of_stock,
                    'overstocked' => (int) $stockStats->overstocked,
                ],
            ],
        ]);
    }

    /**
     * Get search suggestions for autocomplete.
     */
    public function searchSuggestions(Request $request): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        $query = $request->input('q', '');
        $scope = $request->input('scope', 'all');

        if (strlen($query) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $productsQuery = Product::forOrganization($organizationId)
            ->with(['category:id,name', 'images'])
            ->select(['id', 'name', 'sku', 'barcode', 'selling_price', 'category_id'])
            ->limit(10);

        // Apply scope-specific search
        switch ($scope) {
            case 'name':
                $productsQuery->where('name', 'ilike', "%{$query}%");
                break;
            case 'sku':
                $productsQuery->where('sku', 'ilike', "%{$query}%");
                break;
            case 'barcode':
                $productsQuery->where('barcode', 'ilike', "%{$query}%");
                break;
            case 'description':
                $productsQuery->where('description', 'ilike', "%{$query}%");
                break;
            default:
                // Full-text search using PostgreSQL
                $productsQuery->where(function ($q) use ($query) {
                    $q->whereRaw("to_tsvector('english', name || ' ' || COALESCE(sku, '') || ' ' || COALESCE(description, '')) @@ plainto_tsquery('english', ?)", [$query])
                        ->orWhere('name', 'ilike', "%{$query}%")
                        ->orWhere('sku', 'ilike', "%{$query}%")
                        ->orWhere('barcode', 'ilike', "%{$query}%");
                });
        }

        $products = $productsQuery->get();

        $suggestions = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => number_format($product->selling_price, 2),
                'category' => $product->category?->name,
                'image' => $product->images->first()?->url,
            ];
        });

        return response()->json(['suggestions' => $suggestions]);
    }

    /**
     * List user's filter presets.
     */
    public function getFilterPresets(Request $request): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        $presets = SavedSearch::where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->where('type', 'products')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json(['presets' => $presets]);
    }

    /**
     * Store a new filter preset.
     */
    public function storeFilterPreset(FilterPresetRequest $request): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        // If setting as default, remove default from others
        if ($request->boolean('is_default')) {
            SavedSearch::where('user_id', $user->id)
                ->where('organization_id', $organizationId)
                ->where('type', 'products')
                ->update(['is_default' => false]);
        }

        $preset = SavedSearch::create([
            'user_id' => $user->id,
            'organization_id' => $organizationId,
            'type' => 'products',
            'name' => $request->input('name'),
            'filters' => $request->input('filters'),
            'is_default' => $request->boolean('is_default'),
        ]);

        return response()->json([
            'message' => 'Filter preset saved successfully',
            'preset' => $preset,
        ], 201);
    }

    /**
     * Delete a filter preset.
     */
    public function deleteFilterPreset(SavedSearch $preset): JsonResponse
    {
        $user = Auth::user();

        // Verify ownership
        if ($preset->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $preset->delete();

        return response()->json(['message' => 'Filter preset deleted successfully']);
    }

    /**
     * Set a filter preset as default.
     */
    public function setDefaultPreset(SavedSearch $preset): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        // Verify ownership
        if ($preset->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Remove default from all other presets
        SavedSearch::where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->where('type', 'products')
            ->update(['is_default' => false]);

        // Set this one as default
        $preset->update(['is_default' => true]);

        return response()->json([
            'message' => 'Default preset updated',
            'preset' => $preset->fresh(),
        ]);
    }

    /**
     * Apply filters to the query.
     */
    protected function applyFilters($query, ProductFilterRequest $request)
    {
        // Category filter (single or multiple)
        if ($request->filled('category_id')) {
            $query->byCategory($request->input('category_id'));
        }

        if ($request->filled('category_ids')) {
            $categoryIds = is_array($request->input('category_ids'))
                ? $request->input('category_ids')
                : explode(',', $request->input('category_ids'));
            $query->whereIn('category_id', $categoryIds);
        }

        // Brand filter (single or multiple)
        if ($request->filled('brand_id')) {
            $query->byBrand($request->input('brand_id'));
        }

        if ($request->filled('brand_ids')) {
            $brandIds = is_array($request->input('brand_ids'))
                ? $request->input('brand_ids')
                : explode(',', $request->input('brand_ids'));
            $query->whereIn('brand_id', $brandIds);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Stock status filter (multiple)
        if ($request->filled('stock_status')) {
            $stockStatuses = is_array($request->input('stock_status'))
                ? $request->input('stock_status')
                : explode(',', $request->input('stock_status'));

            $query->where(function ($q) use ($stockStatuses) {
                foreach ($stockStatuses as $status) {
                    $q->orWhere(function ($sq) use ($status) {
                        switch ($status) {
                            case 'in_stock':
                                $sq->whereColumn('stock_quantity', '>', 'low_stock_threshold');
                                break;
                            case 'low_stock':
                                $sq->where('stock_quantity', '>', 0)
                                    ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
                                break;
                            case 'out_of_stock':
                                $sq->where('stock_quantity', 0);
                                break;
                            case 'overstocked':
                                $sq->whereRaw('stock_quantity > (low_stock_threshold * 3)');
                                break;
                        }
                    });
                }
            });
        }

        // Condition filter (multiple)
        if ($request->filled('condition')) {
            $conditions = is_array($request->input('condition'))
                ? $request->input('condition')
                : explode(',', $request->input('condition'));
            $query->whereIn('condition', $conditions);
        }

        // Price range filter
        if ($request->filled('min_price')) {
            $query->where('selling_price', '>=', $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('selling_price', '<=', $request->input('max_price'));
        }

        // Location filter (multiple)
        if ($request->filled('location_ids')) {
            $locationIds = is_array($request->input('location_ids'))
                ? $request->input('location_ids')
                : explode(',', $request->input('location_ids'));
            $query->whereIn('location_id', $locationIds);
        }

        // Date range filter
        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->input('created_from'));
        }

        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->input('created_to'));
        }

        return $query;
    }

    /**
     * Apply search to the query.
     */
    protected function applySearch($query, ProductFilterRequest $request)
    {
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->search($searchTerm);
        }

        return $query;
    }

    /**
     * Apply sorting to the query.
     */
    protected function applySorting($query, ProductFilterRequest $request)
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSorts = ['name', 'sku', 'selling_price', 'stock_quantity', 'created_at', 'updated_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query;
    }

    /**
     * Get filter options for dropdowns.
     */
    protected function getFilterOptions(int $organizationId, bool $extended = false): array
    {
        // Categories with hierarchy and product counts
        $categories = Category::where('organization_id', $organizationId)
            ->withCount(['products' => function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'slug']);

        // Brands with product counts
        $brands = Brand::where('organization_id', $organizationId)
            ->withCount(['products' => function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        // Locations
        $locations = InventoryLocation::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        // Conditions from Product model
        $conditions = Product::getConditionOptions();

        // Price range (exclude archived products)
        $priceRange = DB::table('products')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->select([
                DB::raw('MIN(selling_price) as min'),
                DB::raw('MAX(selling_price) as max'),
            ])
            ->first();

        $options = [
            'categories' => $categories,
            'brands' => $brands,
            'locations' => $locations,
            'conditions' => $conditions,
            'priceRange' => [
                'min' => (float) ($priceRange->min ?? 0),
                'max' => (float) ($priceRange->max ?? 1000),
            ],
            'statuses' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
                ['value' => 'archived', 'label' => 'Archived'],
            ],
        ];

        if ($extended) {
            // Add additional data for extended API response
            $options['categoryTree'] = $this->buildCategoryTree($categories);
        }

        return $options;
    }

    /**
     * Build category tree structure.
     */
    protected function buildCategoryTree($categories): array
    {
        $tree = [];
        $lookup = [];

        foreach ($categories as $category) {
            $lookup[$category->id] = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'products_count' => $category->products_count,
                'children' => [],
            ];
        }

        foreach ($lookup as $id => &$node) {
            if ($node['parent_id'] && isset($lookup[$node['parent_id']])) {
                $lookup[$node['parent_id']]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }

        return $tree;
    }

    /**
     * Get product statistics.
     */
    protected function getProductStats(int $organizationId): array
    {
        $stats = DB::table('products')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active"),
                DB::raw('SUM(stock_quantity) as total_stock'),
                DB::raw('SUM(stock_quantity * cost_price) as total_cost_value'),
                DB::raw('SUM(stock_quantity * selling_price) as total_retail_value'),
                DB::raw('SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= low_stock_threshold THEN 1 ELSE 0 END) as low_stock_count'),
                DB::raw('SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_count'),
            ])
            ->first();

        $archivedCount = DB::table('products')
            ->where('organization_id', $organizationId)
            ->whereNotNull('deleted_at')
            ->count();

        return [
            'total_products' => (int) $stats->total,
            'active_products' => (int) $stats->active,
            'archived_count' => $archivedCount,
            'total_stock' => (int) $stats->total_stock,
            'total_cost_value' => number_format((float) $stats->total_cost_value, 2),
            'total_retail_value' => number_format((float) $stats->total_retail_value, 2),
            'low_stock_count' => (int) $stats->low_stock_count,
            'out_of_stock_count' => (int) $stats->out_of_stock_count,
        ];
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): Response
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.create')) {
            abort(403, 'You do not have permission to create products.');
        }

        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        return Inertia::render('Inventory/Products/Create', [
            'filterOptions' => $this->getFilterOptions($organizationId),
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.create')) {
            abort(403, 'You do not have permission to create products.');
        }

        $product = $this->productService->create($request->all(), $user);

        return redirect()
            ->route('inventory.products.show', $product)
            ->with('success', 'Product created successfully.');
    }

    /**
     * Display the specified product (including archived).
     */
    public function show(string $productId): Response
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.view')) {
            abort(403, 'You do not have permission to view products.');
        }

        // Use withTrashed so archived products are still viewable
        $product = Product::withTrashed()->findOrFail($productId);

        $product->load([
            'category',
            'brand',
            'images',
            'creator',
            'lastLabelPrintedBy:id,name',
            'ebayListings.integrationCredential',
            'shopifyListings.integrationCredential',
            'inventoryLevels.location',
            'inventoryLevels.bin.zone',
            'inventoryMovements' => fn ($query) => $query->with('location')->latest()->limit(10),
            'supplierProducts.supplier:id,code,name,supplier_status,meta_data',
            'partedFromProduct:id,name,sku',
            'partOutWorkOrder:id,work_order_number',
            'partedOutComponents:id,name,sku,cost_price,selling_price,available_quantity,parted_from_product_id',
        ]);

        $canDelete = $user->hasPermission('inventory.delete');

        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        try {
            $marketplaceSyncStatus = $this->ruleEngine->getDetailedSyncStatus($product, ['ebay', 'shopify']);
        } catch (\Throwable $e) {
            $marketplaceSyncStatus = [];
        }

        try {
            $credentials = IntegrationCredential::where('organization_id', $organizationId)
                ->where('is_active', true)
                ->get(['id', 'platform', 'seller_username', 'auto_sync_enabled'])
                ->map(fn ($cred) => [
                    'id' => $cred->id,
                    'platform' => $cred->platform,
                    'label' => ucfirst($cred->platform).($cred->seller_username ? " ({$cred->seller_username})" : ''),
                    'auto_sync_enabled' => $cred->auto_sync_enabled,
                ]);
        } catch (\Throwable $e) {
            $credentials = collect();
        }

        return Inertia::render('Inventory/Products/Show', [
            'product' => (new ProductResource($product))->resolve(),
            'permissions' => [
                'can_edit' => $user->hasPermission('inventory.update') && ! $product->trashed(),
                'can_delete' => $canDelete && ! $product->trashed(),
                'can_restore' => $canDelete && $product->trashed(),
                'can_force_delete' => $canDelete && $product->trashed(),
                'can_manage_marketplace' => $user->hasPermission('inventory.update'),
            ],
            'isArchived' => $product->trashed(),
            'marketplaceSyncStatus' => $marketplaceSyncStatus,
            'credentials' => $credentials,
        ]);
    }

    /**
     * Adjust stock for a product and push to marketplaces.
     */
    public function adjustStock(Request $request, Product $product)
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.update')) {
            abort(403, 'You do not have permission to adjust stock.');
        }

        $request->validate([
            'quantity' => 'required|integer|not_in:0',
            'reason' => 'required|string|max:255',
        ]);

        $quantity = (int) $request->input('quantity');
        $reason = $request->input('reason');
        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        DB::transaction(function () use ($product, $quantity, $reason, $organizationId) {
            // Find or pick the first active location
            $location = \App\Models\Inventory\InventoryLocation::where('organization_id', $organizationId)
                ->where('is_active', true)
                ->first();

            if ($location) {
                // Record movement via service
                $inventoryService = app(\App\Services\Inventory\InventoryService::class);
                $inventoryService->adjustStock(
                    $product->id,
                    $location->id,
                    $quantity,
                    $reason
                );
            }

            // Update product stock fields
            $product->increment('stock_quantity', $quantity);
            if ($quantity > 0) {
                $product->incrementAvailableQuantity($quantity);
            } else {
                $product->decrementAvailableQuantity(abs($quantity));
            }
        });

        // Push updated quantity to marketplaces
        \App\Jobs\Inventory\PushInventoryToMarketplaces::dispatch($product->id);

        return back()->with('success', "Stock adjusted by {$quantity}. Marketplace sync queued.");
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): Response
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.update')) {
            abort(403, 'You do not have permission to edit products.');
        }

        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;
        $product->load(['category', 'brand', 'images', 'ebayListings', 'shopifyListings']);

        $marketplaceSyncStatus = $this->ruleEngine->getDetailedSyncStatus($product, ['ebay', 'shopify']);

        return Inertia::render('Inventory/Products/Edit', [
            'product' => (new ProductResource($product))->resolve(),
            'filterOptions' => $this->getFilterOptions($organizationId),
            'marketplaceSyncStatus' => $marketplaceSyncStatus,
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product)
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.update')) {
            abort(403, 'You do not have permission to update products.');
        }

        $this->productService->update($product, $request->all(), $user);

        return redirect()
            ->route('inventory.products.show', $product)
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Archive the specified product (soft delete).
     */
    public function destroy(string $productId)
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.delete')) {
            abort(403, 'You do not have permission to delete products.');
        }

        $product = Product::findOrFail($productId);
        $this->deletionService->archive($product, $user);

        return redirect()
            ->route('inventory.products.index')
            ->with('success', 'Product archived successfully.');
    }

    /**
     * Get deletion impact summary for a product.
     */
    public function deletionImpact(Product $product): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.delete')) {
            abort(403, 'You do not have permission to delete products.');
        }

        $impact = $this->deletionService->getDeletionImpact($product);

        return response()->json($impact);
    }

    /**
     * Permanently delete an archived product.
     */
    public function forceDestroy(string $productId)
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.delete')) {
            abort(403, 'You do not have permission to delete products.');
        }

        $product = Product::onlyTrashed()->findOrFail($productId);
        $this->deletionService->forceDelete($product, $user);

        return redirect()
            ->route('inventory.products.index')
            ->with('success', 'Product permanently deleted.');
    }

    /**
     * Restore an archived product.
     */
    public function restore(string $productId)
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.delete')) {
            abort(403, 'You do not have permission to manage archived products.');
        }

        $product = Product::onlyTrashed()->findOrFail($productId);
        $this->deletionService->restore($product, $user);

        return redirect()
            ->route('inventory.products.show', $product)
            ->with('success', 'Product restored successfully. Status set to draft.');
    }

    /**
     * Duplicate a product.
     */
    public function duplicate(Product $product)
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.create')) {
            abort(403, 'You do not have permission to create products.');
        }

        $newProduct = $this->productService->duplicate($product, $user);

        return redirect()
            ->route('inventory.products.edit', $newProduct)
            ->with('success', 'Product duplicated successfully. Please update the details.');
    }

    /**
     * Perform bulk actions on products.
     */
    public function bulkAction(BulkProductRequest $request)
    {
        $user = Auth::user();
        $action = $request->input('action');
        $productIds = $request->input('product_ids');

        // Verify permission based on action
        $requiredPermission = match ($action) {
            'delete', 'restore', 'force_delete' => 'inventory.delete',
            'archive', 'activate', 'deactivate' => 'inventory.update',
            'export' => 'inventory.export',
            default => 'inventory.view',
        };

        if (! $user->hasPermission($requiredPermission)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        // Deletion-related actions go through ProductDeletionService
        $result = match ($action) {
            'delete' => $this->deletionService->bulkArchive($productIds, $user),
            'restore' => $this->deletionService->bulkRestore($productIds, $user),
            'force_delete' => $this->deletionService->bulkForceDelete($productIds, $user),
            default => $this->productService->bulkAction($action, $productIds, $user),
        };

        return back()->with('success', $result['message']);
    }

    /**
     * Export products.
     */
    public function export(Request $request)
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.export')) {
            abort(403, 'You do not have permission to export products.');
        }

        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;
        $format = $request->input('format', 'xlsx');
        $filters = $request->only(['category_id', 'brand_id', 'status', 'stock_status']);

        return $this->exportService->export($organizationId, $format, $filters);
    }

    /**
     * API: Return paginated products as JSON.
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        $query = Product::forOrganization($organizationId)
            ->with(['category', 'brand', 'images']);

        // Apply search
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        // Apply filters
        if ($request->filled('category_id')) {
            $query->byCategory($request->input('category_id'));
        }

        if ($request->filled('brand_id')) {
            $query->byBrand($request->input('brand_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['name', 'sku', 'selling_price', 'stock_quantity', 'created_at', 'updated_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = $request->input('per_page', 20);
        $products = $query->paginate($perPage);

        return response()->json([
            'products' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * API: Create product and return JSON.
     */
    public function apiStore(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.create')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $product = $this->productService->create($request->all(), $user);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully.',
                'product' => new ProductResource($product),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API: Return single product as JSON.
     */
    public function apiShow(Product $product): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $product->load(['category', 'brand', 'images', 'creator']);

        return response()->json([
            'product' => new ProductResource($product),
        ]);
    }

    /**
     * API: Update product and return JSON.
     */
    public function apiUpdate(Request $request, Product $product): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.update')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $this->productService->update($product, $request->all(), $user);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully.',
                'product' => new ProductResource($product->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API: Delete product and return JSON.
     */
    public function apiDestroy(Product $product): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.delete')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $this->productService->delete($product);

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API: Duplicate product and return JSON.
     */
    public function apiDuplicate(Product $product): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.create')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $newProduct = $this->productService->duplicate($product, $user);

            return response()->json([
                'success' => true,
                'message' => 'Product duplicated successfully.',
                'product' => new ProductResource($newProduct),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate product.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API: Bulk import products from CSV/JSON.
     */
    public function apiBulkImport(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.import')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'products' => 'required|array',
            'products.*.name' => 'required|string|max:255',
            'products.*.sku' => 'nullable|string|max:100',
        ]);

        $results = [
            'created' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($request->input('products') as $index => $productData) {
            try {
                $this->productService->create($productData, $user);
                $results['created']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Import completed. Created: {$results['created']}, Failed: {$results['failed']}",
            'results' => $results,
        ]);
    }
}
