<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreLocationRequest;
use App\Http\Requests\Inventory\UpdateLocationRequest;
use App\Http\Resources\Inventory\LocationResource;
use App\Models\Inventory\InventoryLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->authorizeResource(InventoryLocation::class, 'location');
    }

    /**
     * Display a listing of locations
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->current_organization_id;

        $query = InventoryLocation::where('organization_id', $organizationId);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $locations = $query->orderBy('name')->get();

        return response()->json([
            'data' => LocationResource::collection($locations),
        ]);
    }

    /**
     * Store a newly created location
     */
    public function store(StoreLocationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['organization_id'] = auth()->user()->current_organization_id;

        // If this is set as default, unset other defaults
        if ($data['is_default'] ?? false) {
            InventoryLocation::where('organization_id', $data['organization_id'])
                ->update(['is_default' => false]);
        }

        $location = InventoryLocation::create($data);

        return response()->json([
            'message' => 'Location created successfully',
            'data' => new LocationResource($location),
        ], 201);
    }

    /**
     * Display the specified location
     */
    public function show(InventoryLocation $location): JsonResponse
    {
        return response()->json([
            'data' => new LocationResource($location),
        ]);
    }

    /**
     * Update the specified location
     */
    public function update(UpdateLocationRequest $request, InventoryLocation $location): JsonResponse
    {
        $data = $request->validated();

        // If this is set as default, unset other defaults
        if ($data['is_default'] ?? false) {
            InventoryLocation::where('organization_id', $location->organization_id)
                ->where('id', '!=', $location->id)
                ->update(['is_default' => false]);
        }

        $location->update($data);

        return response()->json([
            'message' => 'Location updated successfully',
            'data' => new LocationResource($location->fresh()),
        ]);
    }

    /**
     * Remove the specified location
     */
    public function destroy(InventoryLocation $location): JsonResponse
    {
        // Check if location has inventory
        if ($location->inventoryLevels()->where('quantity_on_hand', '>', 0)->exists()) {
            return response()->json([
                'message' => 'Cannot delete location with existing inventory',
            ], 422);
        }

        $location->delete();

        return response()->json([
            'message' => 'Location deleted successfully',
        ]);
    }

    /**
     * Get location analytics
     */
    public function analytics(InventoryLocation $location): JsonResponse
    {
        $analytics = [
            'total_inventory_value' => $location->getTotalInventoryValue(),
            'total_product_count' => $location->getTotalProductCount(),
            'inventory_levels_count' => $location->inventoryLevels()->count(),
            'recent_movements_count' => $location->inventoryMovements()
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
        ];

        return response()->json(['data' => $analytics]);
    }
}
