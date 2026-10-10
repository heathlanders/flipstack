<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StorefrontController extends Controller
{
    public function index(Request $request)
    {
        // Get organization - use logged-in user's org or default to first organization
        $organizationId = auth()->check()
            ? auth()->user()->organization_id
            : \App\Models\Organization::first()->id ?? 1;

        // Start building the query
        $query = Product::with(['category', 'brand', 'primaryImage'])
            ->forOrganization($organizationId)
            ->active()
            ->inStock();

        // Search
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        // Filter by brand
        if ($request->filled('brand')) {
            $query->byBrand($request->brand);
        }

        // Filter by price range
        if ($request->filled('min_price') || $request->filled('max_price')) {
            $min = $request->min_price ?? 0;
            $max = $request->max_price ?? 999999;
            $query->priceRange($min, $max);
        }

        // Filter by product type
        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        // Filter on sale items
        if ($request->boolean('on_sale')) {
            $query->where('msrp', '>', 0)
                ->whereColumn('msrp', '>', 'selling_price');
        }

        // Sorting
        $sortBy = $request->get('sort', 'newest');
        switch ($sortBy) {
            case 'price_low':
                $query->orderBy('selling_price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('selling_price', 'desc');
                break;
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            case 'popularity':
                $query->orderBy('total_sales', 'desc');
                break;
            case 'rating':
                $query->orderBy('average_rating', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Pagination
        $perPage = $request->get('per_page', 12);
        $products = $query->paginate($perPage)->withQueryString();

        // Get filters data
        $categories = Category::forOrganization($organizationId)
            ->active()
            ->rootCategories()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $brands = Brand::forOrganization($organizationId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('Storefront/Index', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'filters' => [
                'search' => $request->search,
                'category' => $request->category,
                'brand' => $request->brand,
                'min_price' => $request->min_price,
                'max_price' => $request->max_price,
                'type' => $request->type,
                'on_sale' => $request->boolean('on_sale'),
                'sort' => $sortBy,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $organizationId = auth()->check()
            ? auth()->user()->organization_id
            : \App\Models\Organization::first()->id ?? 1;

        $product = Product::with(['category', 'brand', 'images'])
            ->forOrganization($organizationId)
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();

        // Increment view count
        $product->incrementViews();

        // Get related products (same category)
        $relatedProducts = Product::with(['category', 'brand', 'primaryImage'])
            ->forOrganization($organizationId)
            ->active()
            ->inStock()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->get();

        return Inertia::render('Storefront/Show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
        ]);
    }
}
