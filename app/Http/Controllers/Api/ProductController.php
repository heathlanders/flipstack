<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsImageUrls;
use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductIdentifier;
use App\Models\Inventory\InventoryLocation;
use App\Services\Inventory\ProductDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    use FormatsImageUrls;
    use ResolvesOrganization;

    public function __construct(
        protected ProductDeletionService $deletionService
    ) {}

    /**
     * Get paginated list of products for mobile app.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $this->resolveOrganizationId($user);

        $query = Product::forOrganization($organizationId)
            ->with(['category:id,name', 'brand:id,name', 'images']);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%")
                    ->orWhere('barcode', 'ilike', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        // Price range filters
        if ($request->filled('min_price')) {
            $query->where('selling_price', '>=', $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('selling_price', '<=', $request->input('max_price'));
        }

        // Stock status filter
        if ($request->boolean('in_stock_only')) {
            $query->where('available_quantity', '>', 0);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['name', 'sku', 'selling_price', 'stock_quantity', 'created_at', 'updated_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products->map(function ($product) {
                return $this->formatProduct($product);
            }),
            'meta' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    /**
     * Get a single product by ID.
     */
    public function show(Product $product): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $this->resolveOrganizationId($user);

        // Verify organization access
        if ($product->organization_id !== $organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $product->load(['category', 'brand', 'images', 'identifiers']);

        return response()->json([
            'success' => true,
            'data' => $this->formatProductDetail($product),
        ]);
    }

    /**
     * Create a new product.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.create')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create products',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100',
            'description' => 'nullable|string',
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'barcode' => 'nullable|string|max:100',
            'condition' => 'nullable|string',
        ]);

        $organizationId = $this->resolveOrganizationId($user);

        if (! $organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization associated with this account.',
            ], 422);
        }

        $product = Product::create([
            ...$validated,
            'organization_id' => $organizationId,
            'created_by' => $user->id,
            'status' => 'active',
            'manage_stock' => true,
            'low_stock_threshold' => 10,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $this->formatProduct($product),
        ], 201);
    }

    /**
     * Update an existing product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.update')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update products',
            ], 403);
        }

        $organizationId = $this->resolveOrganizationId($user);

        // Verify organization access
        if ($product->organization_id !== $organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'selling_price' => 'sometimes|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'sometimes|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'barcode' => 'nullable|string|max:100',
            'condition' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,draft,archived',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $this->formatProduct($product->fresh()),
        ]);
    }

    /**
     * Archive a product (soft delete).
     */
    public function destroy(Product $product): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete products',
            ], 403);
        }

        $organizationId = $this->resolveOrganizationId($user);

        if ($product->organization_id !== $organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $this->deletionService->archive($product, $user);

        return response()->json([
            'success' => true,
            'message' => 'Product archived successfully',
        ]);
    }

    /**
     * Get deletion impact summary.
     */
    public function deletionImpact(Product $product): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.delete')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $organizationId = $this->resolveOrganizationId($user);

        if ($product->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->deletionService->getDeletionImpact($product),
        ]);
    }

    /**
     * Permanently delete an archived product.
     */
    public function forceDestroy(string $productId): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.delete')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $product = Product::onlyTrashed()->findOrFail($productId);
        $organizationId = $this->resolveOrganizationId($user);

        if ($product->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $this->deletionService->forceDelete($product, $user);

        return response()->json([
            'success' => true,
            'message' => 'Product permanently deleted',
        ]);
    }

    /**
     * Restore an archived product.
     */
    public function restoreProduct(string $productId): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasPermission('inventory.delete')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $product = Product::onlyTrashed()->findOrFail($productId);
        $organizationId = $this->resolveOrganizationId($user);

        if ($product->organization_id !== $organizationId) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $this->deletionService->restore($product, $user);

        return response()->json([
            'success' => true,
            'message' => 'Product restored successfully',
        ]);
    }

    /**
     * Get product statistics.
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $this->resolveOrganizationId($user);

        $stats = DB::table('products')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->select([
                DB::raw('COUNT(*) as total_products'),
                DB::raw('SUM(CASE WHEN stock_quantity > low_stock_threshold THEN 1 ELSE 0 END) as in_stock_products'),
                DB::raw('SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= low_stock_threshold THEN 1 ELSE 0 END) as low_stock_products'),
                DB::raw('SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_products'),
                DB::raw('SUM(stock_quantity * selling_price) as total_inventory_value'),
                DB::raw('AVG(selling_price) as average_product_price'),
            ])
            ->first();

        $categoriesCount = DB::table('categories')
            ->where('organization_id', $organizationId)
            ->count();

        $topCategory = DB::table('products')
            ->where('products.organization_id', $organizationId)
            ->whereNull('products.deleted_at')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('COUNT(*) as count'))
            ->groupBy('categories.name')
            ->orderByDesc('count')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'total_products' => (int) $stats->total_products,
                'in_stock_products' => (int) $stats->in_stock_products,
                'low_stock_products' => (int) $stats->low_stock_products,
                'out_of_stock_products' => (int) $stats->out_of_stock_products,
                'total_inventory_value' => round((float) $stats->total_inventory_value, 2),
                'average_product_price' => round((float) $stats->average_product_price, 2),
                'categories_count' => $categoriesCount,
                'top_category' => $topCategory?->name ?? 'N/A',
            ],
        ]);
    }

    /**
     * Get categories for the organization.
     */
    public function categories(): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $this->resolveOrganizationId($user);

        $categories = Category::where('organization_id', $organizationId)
            ->withCount('products')
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'parent_id' => $category->parent_id,
                    'products_count' => $category->products_count,
                    'created_at' => $category->created_at->toIso8601String(),
                    'updated_at' => $category->updated_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get inventory locations for the organization.
     */
    public function locations(): JsonResponse
    {
        $user = Auth::user();
        $organizationId = $this->resolveOrganizationId($user);

        $locations = InventoryLocation::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($location) {
                return [
                    'id' => $location->id,
                    'name' => $location->name,
                    'code' => $location->code,
                    'type' => $location->type,
                    'address' => $location->address,
                    'is_default' => $location->is_default,
                    'created_at' => $location->created_at->toIso8601String(),
                    'updated_at' => $location->updated_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $locations,
        ]);
    }

    /**
     * GET /api/products/barcode-lookup
     * Look up a product by barcode, SKU, or internal SKU.
     */
    public function barcodeLookup(Request $request): JsonResponse
    {
        $request->validate([
            'barcode' => 'required|string|max:100',
        ]);

        $user = Auth::user();
        $organizationId = $this->resolveOrganizationId($user);
        $barcode = $request->input('barcode');

        // Search by barcode, sku, or internal_sku on Product
        $product = Product::forOrganization($organizationId)
            ->where(function ($q) use ($barcode) {
                $q->where('barcode', $barcode)
                    ->orWhere('sku', $barcode)
                    ->orWhere('internal_sku', $barcode);
            })
            ->first();

        // If not found, check ProductIdentifier
        if (! $product) {
            $identifier = ProductIdentifier::where('value', $barcode)
                ->whereHas('product', function ($q) use ($organizationId) {
                    $q->where('organization_id', $organizationId);
                })
                ->first();

            if ($identifier) {
                $product = $identifier->product;
            }
        }

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'No product found for barcode: '.$barcode,
            ], 404);
        }

        $product->load(['category', 'brand', 'images', 'identifiers']);

        return response()->json([
            'success' => true,
            'data' => $this->formatProductDetail($product),
        ]);
    }

    /**
     * Format product for API response.
     */
    private function formatProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku ?? '',
            'description' => $product->description ?? $product->short_description ?? '',
            'price' => (float) $product->selling_price,
            'quantity' => (int) $product->stock_quantity,
            'category' => $product->category?->name ?? 'Uncategorized',
            'category_id' => $product->category_id,
            'brand' => $product->brand?->name,
            'brand_id' => $product->brand_id,
            'image' => $this->imageUrl($product->primaryImageUrl ?? $product->featured_image),
            'barcode' => $product->barcode,
            'status' => $product->status,
            'condition' => $product->condition,
            'cost_price' => (float) $product->cost_price,
            'is_in_stock' => $product->stock_quantity > 0,
            'is_low_stock' => $product->stock_quantity > 0 && $product->stock_quantity <= ($product->low_stock_threshold ?? 10),
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Format product with full details for API response.
     */
    private function formatProductDetail(Product $product): array
    {
        $data = $this->formatProduct($product);

        $data['images'] = $product->images->map(function ($image) {
            return [
                'id' => $image->id,
                'url' => $this->imageUrl($image->url),
                'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order,
            ];
        });

        $data['identifiers'] = $product->identifiers->map(function ($identifier) {
            return [
                'id' => $identifier->id,
                'type' => $identifier->type,
                'value' => $identifier->value,
                'is_primary' => $identifier->is_primary,
            ];
        });

        $data['msrp'] = (float) $product->msrp;
        $data['weight'] = (float) $product->weight;
        $data['length'] = (float) $product->length;
        $data['width'] = (float) $product->width;
        $data['height'] = (float) $product->height;
        $data['condition_description'] = $product->condition_description;
        $data['low_stock_threshold'] = $product->low_stock_threshold;
        $data['gallery_images'] = collect($product->gallery_images ?? [])
            ->map(fn ($url) => $this->imageUrl($url))
            ->filter()
            ->values()
            ->all();

        return $data;
    }
}
