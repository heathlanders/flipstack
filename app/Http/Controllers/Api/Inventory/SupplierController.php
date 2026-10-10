<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreSupplierRequest;
use App\Http\Requests\Inventory\UpdateSupplierRequest;
use App\Http\Resources\Inventory\SupplierResource;
use App\Models\Inventory\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->authorizeResource(Supplier::class, 'supplier');
    }

    /**
     * Display a listing of suppliers
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->current_organization_id;

        $query = Supplier::where('organization_id', $organizationId);

        // Filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('is_preferred')) {
            $query->where('is_preferred', $request->boolean('is_preferred'));
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');

        if ($sortBy === 'performance') {
            $query->orderBy('performance_rating', 'desc');
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $suppliers = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => SupplierResource::collection($suppliers),
            'meta' => [
                'pagination' => [
                    'current_page' => $suppliers->currentPage(),
                    'total' => $suppliers->total(),
                    'per_page' => $suppliers->perPage(),
                    'last_page' => $suppliers->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Store a newly created supplier
     */
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['organization_id'] = auth()->user()->current_organization_id;
        $data['created_by'] = auth()->id();

        $supplier = Supplier::create($data);

        return response()->json([
            'message' => 'Supplier created successfully',
            'data' => new SupplierResource($supplier),
        ], 201);
    }

    /**
     * Display the specified supplier
     */
    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load(['supplierProducts.product', 'creator']);

        return response()->json([
            'data' => new SupplierResource($supplier),
        ]);
    }

    /**
     * Update the specified supplier
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return response()->json([
            'message' => 'Supplier updated successfully',
            'data' => new SupplierResource($supplier->fresh()),
        ]);
    }

    /**
     * Remove the specified supplier
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        // Check if supplier has active product relationships
        if ($supplier->supplierProducts()->where('is_active', true)->exists()) {
            return response()->json([
                'message' => 'Cannot delete supplier with active product relationships',
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'Supplier deleted successfully',
        ]);
    }

    /**
     * Get supplier performance metrics
     */
    public function performance(Supplier $supplier): JsonResponse
    {
        $metrics = [
            'performance_rating' => $supplier->performance_rating,
            'total_orders' => $supplier->total_orders,
            'on_time_delivery_rate' => $supplier->on_time_delivery_rate,
            'product_count' => $supplier->supplierProducts()->where('is_active', true)->count(),
            'average_lead_time' => $supplier->supplierProducts()->avg('lead_time_days'),
            'average_quality_rating' => $supplier->supplierProducts()->avg('quality_rating'),
        ];

        return response()->json(['data' => $metrics]);
    }
}
