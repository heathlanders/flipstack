<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): Response
    {
        $user = auth()->user();

        if (! $user) {
            return Inertia::render('Inventory/Categories/Index', [
                'categories' => [],
                'tree' => [],
                'stats' => ['total' => 0, 'active' => 0, 'inactive' => 0, 'with_products' => 0],
                'filters' => $request->only(['search', 'status', 'parent']),
            ]);
        }

        $organizationId = $user->currentTeam?->organization_id
            ?? $user->current_team_id
            ?? $user->current_organization_id
            ?? $user->organization_id
            ?? $user->organization?->id;

        if (! $organizationId) {
            return Inertia::render('Inventory/Categories/Index', [
                'categories' => [],
                'tree' => [],
                'stats' => ['total' => 0, 'active' => 0, 'inactive' => 0, 'with_products' => 0],
                'filters' => $request->only(['search', 'status', 'parent']),
            ]);
        }

        // Build query with filters
        $query = Category::with(['parent', 'children'])
            ->forOrganization($organizationId)
            ->withCount(['products', 'activeProducts']);

        // Apply search filter
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%'.$request->search.'%')
                ->orWhere('description', 'LIKE', '%'.$request->search.'%');
        }

        // Apply status filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        // Apply parent filter
        if ($request->filled('parent_id')) {
            if ($request->parent_id === 'root') {
                $query->rootCategories();
            } else {
                $query->where('parent_id', $request->parent_id);
            }
        }

        // Determine view mode and pagination
        $viewMode = $request->get('view', 'tree');

        if ($viewMode === 'tree') {
            // Return flat array - frontend builds tree structure
            $categories = $query->orderBy('lft')->get();
        } else {
            $categories = $query->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString();
        }

        // Get parent options for create/edit forms
        $parentOptions = Category::forOrganization($organizationId)
            ->active()
            ->rootCategories()
            ->orderBy('name')
            ->get(['id', 'name', 'depth'])
            ->map(function ($category) use ($organizationId) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'depth' => $category->depth,
                    'children' => $this->getChildrenOptions($category->id, $organizationId),
                ];
            });

        // Calculate statistics
        $stats = $this->getCategoryStats($organizationId);

        return Inertia::render('Inventory/Categories/Index', [
            'categories' => $categories,
            'parentOptions' => $parentOptions,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'parent_id', 'view']),
            'viewMode' => $viewMode,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $organizationId = auth()->user()->currentTeam?->organization_id ?? auth()->user()->current_organization_id ?? auth()->user()->organization_id;

        $parentOptions = Category::forOrganization($organizationId)
            ->active()
            ->orderBy('path')
            ->get(['id', 'name', 'depth', 'path'])
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => str_repeat('— ', $category->depth).$category->name,
                    'depth' => $category->depth,
                ];
            });

        return Inertia::render('Inventory/Categories/Create', [
            'parentOptions' => $parentOptions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $organizationId = auth()->user()->currentTeam?->organization_id ?? auth()->user()->current_organization_id ?? auth()->user()->organization_id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            // Generate unique slug
            $slug = $this->generateUniqueSlug($request->name, $organizationId);

            // Calculate nested set values
            $nestedSetValues = $this->calculateNestedSetValues($request->parent_id, $organizationId);

            // Create category
            $category = Category::create([
                'organization_id' => $organizationId,
                'parent_id' => $request->parent_id,
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'image' => $request->image,
                'color' => $request->color ?? '#6B7280',
                'icon' => $request->icon,
                'path' => $this->generatePath($request->parent_id, $slug),
                'lft' => $nestedSetValues['lft'],
                'rgt' => $nestedSetValues['rgt'],
                'depth' => $nestedSetValues['depth'],
                'sort_order' => $this->getNextSortOrder($request->parent_id, $organizationId),
                'is_active' => $request->boolean('is_active', true),
                'metadata' => $request->metadata ?? [],
            ]);

            // Update nested set values for affected nodes
            $this->updateNestedSetAfterInsert($nestedSetValues['lft'], $organizationId);

            DB::commit();

            return redirect()->route('inventory.categories.index')
                ->with('success', 'Category created successfully.');

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Failed to create category: '.$e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): Response
    {
        $category->load(['parent', 'children.children', 'products' => function ($query) {
            $query->with(['brand'])->limit(10);
        }]);

        // Get category analytics
        $analytics = [
            'products_count' => $category->products()->count(),
            'active_products_count' => $category->activeProducts()->count(),
            'total_value' => $category->products()->sum('selling_price'),
            'avg_price' => $category->products()->avg('selling_price'),
            'low_stock_count' => $category->products()
                ->where('stock_quantity', '<=', DB::raw('low_stock_threshold'))
                ->count(),
        ];

        // Get subcategories tree
        $subcategories = $this->getSubcategoriesTree($category->id);

        return Inertia::render('Inventory/Categories/Show', [
            'category' => $category,
            'analytics' => $analytics,
            'subcategories' => $subcategories,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category): Response
    {
        $organizationId = auth()->user()->currentTeam?->organization_id ?? auth()->user()->current_organization_id ?? auth()->user()->organization_id;

        $parentOptions = Category::forOrganization($organizationId)
            ->where('id', '!=', $category->id)
            ->active()
            ->orderBy('path')
            ->get(['id', 'name', 'depth', 'path'])
            ->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'name' => str_repeat('— ', $cat->depth).$cat->name,
                    'depth' => $cat->depth,
                ];
            });

        return Inertia::render('Inventory/Categories/Edit', [
            'category' => $category,
            'parentOptions' => $parentOptions,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $organizationId = auth()->user()->currentTeam?->organization_id ?? auth()->user()->current_organization_id ?? auth()->user()->organization_id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $oldParentId = $category->parent_id;
            $newParentId = $request->parent_id;

            // Generate unique slug if name changed
            $slug = $category->slug;
            if ($category->name !== $request->name) {
                $slug = $this->generateUniqueSlug($request->name, $organizationId, $category->id);
            }

            // Update basic fields
            $category->update([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'image' => $request->image,
                'color' => $request->color ?? $category->color,
                'icon' => $request->icon,
                'is_active' => $request->boolean('is_active', true),
                'metadata' => $request->metadata ?? [],
            ]);

            // Handle parent change (move in tree)
            if ($oldParentId !== $newParentId) {
                $this->moveCategory($category, $newParentId, $organizationId);
            }

            DB::commit();

            return redirect()->route('inventory.categories.index')
                ->with('success', 'Category updated successfully.');

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Failed to update category: '.$e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        DB::beginTransaction();

        try {
            // Check if category has products
            if ($category->products()->count() > 0) {
                return back()->withErrors(['error' => 'Cannot delete category that contains products.']);
            }

            // Check if category has children
            if ($category->children()->count() > 0) {
                return back()->withErrors(['error' => 'Cannot delete category that has subcategories.']);
            }

            // Update nested set values
            $this->updateNestedSetAfterDelete($category);

            $category->delete();

            DB::commit();

            return redirect()->route('inventory.categories.index')
                ->with('success', 'Category deleted successfully.');

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Failed to delete category: '.$e->getMessage()]);
        }
    }

    /**
     * Move category to new parent.
     */
    public function move(Request $request, Category $category)
    {
        $validator = Validator::make($request->all(), [
            'new_parent_id' => 'nullable|exists:categories,id',
            'position' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        DB::beginTransaction();

        try {
            $organizationId = auth()->user()->currentTeam?->organization_id ?? auth()->user()->current_organization_id ?? auth()->user()->organization_id;
            $this->moveCategory($category, $request->new_parent_id, $organizationId);

            DB::commit();

            return back()->with('success', 'Category moved successfully.');

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Failed to move category: '.$e->getMessage()]);
        }
    }

    /**
     * Get products for a category.
     */
    public function products(Category $category)
    {
        $products = $category->products()
            ->with(['brand', 'category'])
            ->paginate(20);

        return Inertia::render('Inventory/Categories/Products', [
            'category' => $category,
            'products' => $products,
        ]);
    }

    // API Methods for AJAX requests

    /**
     * Get categories as JSON for API calls.
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->currentTeam?->organization_id ?? auth()->user()->current_organization_id ?? auth()->user()->organization_id;

        $categories = Category::forOrganization($organizationId)
            ->with(['parent'])
            ->withCount('products')
            ->orderBy('lft')
            ->get();

        return response()->json([
            'categories' => $this->buildCategoryTree($categories),
        ]);
    }

    /**
     * Store category via API.
     */
    public function apiStore(Request $request): JsonResponse
    {
        $result = $this->store($request);

        if ($result instanceof \Illuminate\Http\RedirectResponse) {
            return response()->json(['success' => true], 201);
        }

        return response()->json(['success' => false, 'errors' => $result], 422);
    }

    /**
     * API: Return single category as JSON.
     */
    public function apiShow(Category $category): JsonResponse
    {
        $organizationId = auth()->user()->currentTeam?->organization_id ?? auth()->user()->current_organization_id ?? auth()->user()->organization_id;

        if ($category->organization_id !== $organizationId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $category->load(['parent', 'children']);
        $category->loadCount(['products', 'activeProducts']);

        return response()->json([
            'category' => $category,
        ]);
    }

    /**
     * API: Update category and return JSON.
     */
    public function apiUpdate(Request $request, Category $category): JsonResponse
    {
        $organizationId = auth()->user()->currentTeam?->organization_id ?? auth()->user()->current_organization_id ?? auth()->user()->organization_id;

        if ($category->organization_id !== $organizationId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $oldParentId = $category->parent_id;
            $newParentId = $request->parent_id;

            $slug = $category->slug;
            if ($category->name !== $request->name) {
                $slug = $this->generateUniqueSlug($request->name, $organizationId, $category->id);
            }

            $category->update([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'image' => $request->image,
                'color' => $request->color ?? $category->color,
                'icon' => $request->icon,
                'is_active' => $request->boolean('is_active', true),
                'metadata' => $request->metadata ?? [],
            ]);

            if ($oldParentId !== $newParentId) {
                $this->moveCategory($category, $newParentId, $organizationId);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully.',
                'category' => $category->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update category.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API: Delete category and return JSON.
     */
    public function apiDestroy(Category $category): JsonResponse
    {
        $organizationId = auth()->user()->currentTeam?->organization_id ?? auth()->user()->current_organization_id ?? auth()->user()->organization_id;

        if ($category->organization_id !== $organizationId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();

        try {
            if ($category->products()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category that contains products.',
                ], 422);
            }

            if ($category->children()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category that has subcategories.',
                ], 422);
            }

            $this->updateNestedSetAfterDelete($category);
            $category->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully.',
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API: Move category to new parent.
     */
    public function apiMove(Request $request, Category $category): JsonResponse
    {
        $organizationId = auth()->user()->currentTeam?->organization_id ?? auth()->user()->current_organization_id ?? auth()->user()->organization_id;

        if ($category->organization_id !== $organizationId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'new_parent_id' => 'nullable|exists:categories,id',
            'position' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $this->moveCategory($category, $request->new_parent_id, $organizationId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category moved successfully.',
                'category' => $category->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to move category.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    // Private Helper Methods

    /**
     * Build hierarchical category tree.
     */
    private function buildCategoryTree($categories, $parentId = null): array
    {
        $tree = [];

        foreach ($categories as $category) {
            if ($category->parent_id === $parentId) {
                $category->children_tree = $this->buildCategoryTree($categories, $category->id);
                $tree[] = $category;
            }
        }

        return $tree;
    }

    /**
     * Generate unique slug for category.
     */
    private function generateUniqueSlug(string $name, int $organizationId, ?string $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        $query = Category::forOrganization($organizationId)->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
            $query = Category::forOrganization($organizationId)->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    /**
     * Calculate nested set values for new category.
     */
    private function calculateNestedSetValues(?string $parentId, int $organizationId): array
    {
        if (! $parentId) {
            // Root category
            $maxRgt = Category::forOrganization($organizationId)->max('rgt') ?? 0;

            return [
                'lft' => $maxRgt + 1,
                'rgt' => $maxRgt + 2,
                'depth' => 0,
            ];
        }

        $parent = Category::findOrFail($parentId);

        return [
            'lft' => $parent->rgt,
            'rgt' => $parent->rgt + 1,
            'depth' => $parent->depth + 1,
        ];
    }

    /**
     * Update nested set values after insertion.
     */
    private function updateNestedSetAfterInsert(int $insertPoint, int $organizationId): void
    {
        // Update rgt values
        Category::forOrganization($organizationId)
            ->where('rgt', '>=', $insertPoint)
            ->increment('rgt', 2);

        // Update lft values
        Category::forOrganization($organizationId)
            ->where('lft', '>', $insertPoint)
            ->increment('lft', 2);
    }

    /**
     * Generate ltree path for category.
     */
    private function generatePath(?string $parentId, string $slug): string
    {
        if (! $parentId) {
            return $slug;
        }

        $parent = Category::findOrFail($parentId);

        return $parent->path.'.'.$slug;
    }

    /**
     * Get next sort order for siblings.
     */
    private function getNextSortOrder(?string $parentId, int $organizationId): int
    {
        $maxOrder = Category::forOrganization($organizationId)
            ->where('parent_id', $parentId)
            ->max('sort_order') ?? 0;

        return $maxOrder + 1;
    }

    /**
     * Move category to new position in tree.
     */
    private function moveCategory(Category $category, ?string $newParentId, int $organizationId): void
    {
        // This is a simplified version - full nested set move operations are complex
        // For production, consider using a package like kalnoy/nestedset

        $newPath = $this->generatePath($newParentId, $category->slug);
        $newDepth = $newParentId ? Category::findOrFail($newParentId)->depth + 1 : 0;

        $category->update([
            'parent_id' => $newParentId,
            'path' => $newPath,
            'depth' => $newDepth,
        ]);

        // Update children paths recursively
        $this->updateChildrenPaths($category);
    }

    /**
     * Update paths of all children recursively.
     */
    private function updateChildrenPaths(Category $category): void
    {
        $children = $category->children;

        foreach ($children as $child) {
            $newPath = $category->path.'.'.$child->slug;
            $child->update([
                'path' => $newPath,
                'depth' => $category->depth + 1,
            ]);

            $this->updateChildrenPaths($child);
        }
    }

    /**
     * Update nested set after category deletion.
     */
    private function updateNestedSetAfterDelete(Category $category): void
    {
        $organizationId = $category->organization_id;
        $lft = $category->lft;
        $rgt = $category->rgt;
        $width = $rgt - $lft + 1;

        // Update rgt values
        Category::forOrganization($organizationId)
            ->where('rgt', '>', $rgt)
            ->decrement('rgt', $width);

        // Update lft values
        Category::forOrganization($organizationId)
            ->where('lft', '>', $lft)
            ->decrement('lft', $width);
    }

    /**
     * Get children options for select dropdown.
     */
    private function getChildrenOptions(string $parentId, int $organizationId): array
    {
        return Category::forOrganization($organizationId)
            ->where('parent_id', $parentId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'depth'])
            ->map(function ($category) use ($organizationId) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'depth' => $category->depth,
                    'children' => $this->getChildrenOptions($category->id, $organizationId),
                ];
            })
            ->toArray();
    }

    /**
     * Get subcategories tree for category page.
     */
    private function getSubcategoriesTree(string $categoryId): array
    {
        $subcategories = Category::where('parent_id', $categoryId)
            ->with(['children'])
            ->withCount(['products', 'activeProducts'])
            ->orderBy('sort_order')
            ->get();

        return $subcategories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'products_count' => $category->products_count,
                'active_products_count' => $category->active_products_count,
                'children' => $this->getSubcategoriesTree($category->id),
            ];
        })->toArray();
    }

    /**
     * Get category statistics.
     */
    private function getCategoryStats(int $organizationId): array
    {
        $totalCategories = Category::forOrganization($organizationId)->count();
        $activeCategories = Category::forOrganization($organizationId)->active()->count();
        $rootCategories = Category::forOrganization($organizationId)->rootCategories()->count();
        $categoriesWithProducts = Category::forOrganization($organizationId)
            ->has('products')
            ->count();

        return [
            'total' => $totalCategories,
            'active' => $activeCategories,
            'inactive' => $totalCategories - $activeCategories,
            'root' => $rootCategories,
            'with_products' => $categoriesWithProducts,
            'empty' => $totalCategories - $categoriesWithProducts,
        ];
    }
}
