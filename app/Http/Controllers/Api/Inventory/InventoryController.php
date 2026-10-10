<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Http\Requests\Inventory\ReserveStockRequest;
use App\Http\Requests\Inventory\TransferStockRequest;
use App\Http\Resources\Inventory\InventoryLevelResource;
use App\Http\Resources\Inventory\InventoryMovementResource;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryMovement;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
        private InventoryMovementService $movementService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get inventory levels
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->current_organization_id;

        $query = InventoryLevel::with(['product', 'productVariation', 'location'])
            ->where('organization_id', $organizationId);

        // Filters
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('low_stock')) {
            $query->lowStock();
        }

        if ($request->has('out_of_stock')) {
            $query->outOfStock();
        }

        $levels = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InventoryLevelResource::collection($levels),
            'meta' => [
                'pagination' => [
                    'current_page' => $levels->currentPage(),
                    'total' => $levels->total(),
                    'per_page' => $levels->perPage(),
                    'last_page' => $levels->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Adjust stock levels
     */
    public function adjustStock(AdjustStockRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $movement = $this->inventoryService->adjustStock(
                $data['product_id'],
                $data['location_id'],
                $data['quantity'],
                $data['reason'] ?? 'Manual adjustment',
                $data['product_variation_id'] ?? null,
                $data['unit_cost'] ?? null,
                $data['metadata'] ?? []
            );

            return response()->json([
                'message' => 'Stock adjusted successfully',
                'data' => new InventoryMovementResource($movement),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Stock adjustment failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Transfer stock between locations
     */
    public function transferStock(TransferStockRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $movements = $this->inventoryService->transferStock(
                $data['product_id'],
                $data['from_location_id'],
                $data['to_location_id'],
                $data['quantity'],
                $data['product_variation_id'] ?? null,
                $data['reason'] ?? 'Stock transfer'
            );

            return response()->json([
                'message' => 'Stock transferred successfully',
                'data' => [
                    'out_movement' => new InventoryMovementResource($movements[0]),
                    'in_movement' => new InventoryMovementResource($movements[1]),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Stock transfer failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reserve stock
     */
    public function reserveStock(ReserveStockRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $this->inventoryService->reserveStock(
                $data['product_id'],
                $data['location_id'],
                $data['quantity'],
                $data['reference_type'],
                $data['reference_id'],
                $data['product_variation_id'] ?? null
            );

            return response()->json([
                'message' => 'Stock reserved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Stock reservation failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Release reserved stock
     */
    public function releaseStock(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'location_id' => 'required|exists:inventory_locations,id',
            'quantity' => 'required|integer|min:1',
            'product_variation_id' => 'nullable|exists:product_variations,id',
        ]);

        try {
            $this->inventoryService->releaseReservedStock(
                $request->product_id,
                $request->location_id,
                $request->quantity,
                $request->product_variation_id
            );

            return response()->json([
                'message' => 'Reserved stock released successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Stock release failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get inventory movements
     */
    public function movements(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->current_organization_id;

        $query = InventoryMovement::with(['product', 'productVariation', 'location', 'creator'])
            ->where('organization_id', $organizationId);

        // Filters
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }

        $movements = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InventoryMovementResource::collection($movements),
            'meta' => [
                'pagination' => [
                    'current_page' => $movements->currentPage(),
                    'total' => $movements->total(),
                    'per_page' => $movements->perPage(),
                    'last_page' => $movements->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Get low stock alerts
     */
    public function lowStockAlerts(): JsonResponse
    {
        $organizationId = auth()->user()->current_organization_id;

        $alerts = $this->inventoryService->getLowStockAlerts($organizationId);

        return response()->json([
            'data' => InventoryLevelResource::collection($alerts),
            'count' => $alerts->count(),
        ]);
    }

    /**
     * Get inventory valuation
     */
    public function valuation(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->current_organization_id;

        $valuation = $this->inventoryService->calculateInventoryValuation(
            $organizationId,
            $request->get('location_id')
        );

        return response()->json(['data' => $valuation]);
    }

    /**
     * Get inventory analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->current_organization_id;

        $startDate = $request->get('start_date', now()->subDays(30));
        $endDate = $request->get('end_date', now());

        $summary = $this->movementService->getMovementSummary($organizationId, $startDate, $endDate);

        // Additional analytics
        $totalProducts = Product::where('organization_id', $organizationId)->count();
        $lowStockCount = $this->inventoryService->getLowStockAlerts($organizationId)->count();
        $valuation = $this->inventoryService->calculateInventoryValuation($organizationId);

        return response()->json([
            'data' => [
                'movement_summary' => $summary,
                'total_products' => $totalProducts,
                'low_stock_alerts' => $lowStockCount,
                'inventory_valuation' => $valuation,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ],
        ]);
    }
}
