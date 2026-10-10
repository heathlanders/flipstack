<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreCategoryRequest;
use App\Http\Requests\Inventory\UpdateCategoryRequest;
use App\Http\Resources\Inventory\CategoryResource;
use App\Models\Catalog\Category;
use App\Services\Inventory\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private CategoryService $categoryService
    ) {
        $this->middleware('auth:sanctum');
        $this->authorizeResource(Category::class, 'category');
    }

    /**
     * Display category tree
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->current_organization_id;
        $rootId = $request->get('root_id');

        $categories = $this->categoryService->getCategoryTree($organizationId, $rootId);

        return response()->json([
            'data' => CategoryResource::collection($categories),
        ]);
    }

    /**
     * Store a newly created category
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['organization_id'] = auth()->user()->current_organization_id;
        $data['created_by'] = auth()->id();

        $category = $this->categoryService->createCategory($data);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => new CategoryResource($category),
        ], 201);
    }

    /**
     * Display the specified category
     */
    public function show(Category $category): JsonResponse
    {
        $category->load(['attributes', 'children', 'parent']);

        return response()->json([
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Update the specified category
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => new CategoryResource($category->fresh()),
        ]);
    }

    /**
     * Remove the specified category
     */
    public function destroy(Category $category, Request $request): JsonResponse
    {
        $strategy = $request->get('strategy', 'move_to_parent');

        $this->categoryService->deleteCategory($category, $strategy);

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }

    /**
     * Move category to new parent
     */
    public function move(Category $category, Request $request): JsonResponse
    {
        $request->validate([
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $newParent = $request->parent_id ? Category::findOrFail($request->parent_id) : null;

        $this->categoryService->moveCategory($category, $newParent);

        return response()->json([
            'message' => 'Category moved successfully',
            'data' => new CategoryResource($category->fresh()),
        ]);
    }
}
