<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventoryController extends Controller
{
    /**
     * Display the inventory dashboard
     */
    public function dashboard()
    {
        // Get basic inventory stats
        $organizationId = auth()->user()->current_organization_id ?? 1;

        $analytics = [
            'total_products' => DB::table('products')->where('organization_id', $organizationId)->whereNull('deleted_at')->count(),
            'total_categories' => DB::table('categories')->where('organization_id', $organizationId)->count(),
            'total_suppliers' => DB::table('suppliers')->where('organization_id', $organizationId)->count(),
            'inventory_valuation' => [
                'total_value' => DB::table('inventory_levels')
                    ->where('organization_id', $organizationId)
                    ->sum(DB::raw('quantity_on_hand * average_cost')),
                'total_quantity' => DB::table('inventory_levels')
                    ->where('organization_id', $organizationId)
                    ->sum('quantity_on_hand'),
            ],
            'low_stock_alerts' => DB::table('inventory_levels')
                ->where('organization_id', $organizationId)
                ->whereColumn('inventory_levels.quantity_available', '<=', 'inventory_levels.reorder_point') // FIXED: Added table qualification
                ->count(),
        ];

        // Get recent movements
        $recentMovements = DB::table('inventory_movements')
            ->join('products', 'inventory_movements.product_id', '=', 'products.id')
            ->join('inventory_locations', 'inventory_movements.location_id', '=', 'inventory_locations.id')
            ->where('inventory_movements.organization_id', $organizationId)
            ->select(
                'inventory_movements.*',
                'products.name as product_name',
                'inventory_locations.name as location_name'
            )
            ->orderBy('inventory_movements.created_at', 'desc')
            ->limit(10)
            ->get();

        // Get low stock alerts with product details
        $lowStockAlerts = DB::table('inventory_levels')
            ->join('products', 'inventory_levels.product_id', '=', 'products.id')
            ->join('inventory_locations', 'inventory_levels.location_id', '=', 'inventory_locations.id')
            ->where('inventory_levels.organization_id', $organizationId)
            ->whereColumn('inventory_levels.quantity_available', '<=', 'inventory_levels.reorder_point') // FIXED: Added table qualification
            ->select(
                'inventory_levels.*',
                'products.name as product_name',
                'products.sku',
                'inventory_locations.name as location_name'
            )
            ->orderBy('inventory_levels.quantity_available') // Also qualified for consistency
            ->limit(10)
            ->get();

        // Sample top categories data (you can enhance this)
        $topCategories = DB::table('categories')
            ->where('organization_id', $organizationId)
            ->select('id', 'name')
            ->limit(5)
            ->get()
            ->map(function ($category, $index) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'value' => rand(1000, 10000), // Replace with actual calculation
                    'color' => ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6'][$index] ?? '#6B7280',
                ];
            });

        return Inertia::render('Inventory/Dashboard', [
            'analytics' => $analytics,
            'recentMovements' => $recentMovements,
            'lowStockAlerts' => $lowStockAlerts,
            'topCategories' => $topCategories,
        ]);
    }

    /**
     * Display inventory levels
     */
    public function levels(Request $request)
    {
        $organizationId = auth()->user()->current_organization_id ?? 1;

        $levels = DB::table('inventory_levels')
            ->join('products', 'inventory_levels.product_id', '=', 'products.id')
            ->join('inventory_locations', 'inventory_levels.location_id', '=', 'inventory_locations.id')
            ->where('inventory_levels.organization_id', $organizationId)
            ->select(
                'inventory_levels.*',
                'products.name as product_name',
                'products.sku',
                'inventory_locations.name as location_name'
            )
            ->paginate(15);

        return Inertia::render('Inventory/Operations/Levels', [
            'levels' => $levels,
        ]);
    }

    /**
     * Display inventory movements
     */
    public function movements(Request $request)
    {
        $organizationId = auth()->user()->current_organization_id ?? 1;

        $movements = DB::table('inventory_movements')
            ->join('products', 'inventory_movements.product_id', '=', 'products.id')
            ->join('inventory_locations', 'inventory_movements.location_id', '=', 'inventory_locations.id')
            ->leftJoin('users', 'inventory_movements.created_by', '=', 'users.id')
            ->where('inventory_movements.organization_id', $organizationId)
            ->select(
                'inventory_movements.*',
                'products.name as product_name',
                'products.sku',
                'inventory_locations.name as location_name',
                'users.name as created_by_name'
            )
            ->orderBy('inventory_movements.created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Inventory/Operations/Movements', [
            'movements' => $movements,
        ]);
    }

    /**
     * Show stock adjustment form
     */
    public function adjustForm()
    {
        $organizationId = auth()->user()->current_organization_id ?? 1;

        $products = DB::table('products')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->select('id', 'name', 'sku')
            ->orderBy('name')
            ->get();

        $locations = DB::table('inventory_locations')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Operations/Adjust', [
            'products' => $products,
            'locations' => $locations,
        ]);
    }

    /**
     * Process stock adjustment
     */
    public function adjustStock(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'location_id' => 'required|exists:inventory_locations,id',
            'quantity' => 'required|integer|not_in:0',
            'reason' => 'required|string|max:255',
        ]);

        $organizationId = auth()->user()->current_organization_id ?? 1;

        // This is a simplified version - you'd use your InventoryService here
        return redirect()->route('inventory.operations.levels')
            ->with('success', 'Stock adjustment completed successfully');
    }

    // API Methods

    /**
     * API: Return inventory levels as JSON.
     */
    public function apiLevels(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->current_organization_id ?? 1;

        $query = DB::table('inventory_levels')
            ->join('products', 'inventory_levels.product_id', '=', 'products.id')
            ->join('inventory_locations', 'inventory_levels.location_id', '=', 'inventory_locations.id')
            ->where('inventory_levels.organization_id', $organizationId)
            ->select(
                'inventory_levels.*',
                'products.name as product_name',
                'products.sku',
                'inventory_locations.name as location_name'
            );

        if ($request->filled('product_id')) {
            $query->where('inventory_levels.product_id', $request->input('product_id'));
        }

        if ($request->filled('location_id')) {
            $query->where('inventory_levels.location_id', $request->input('location_id'));
        }

        if ($request->filled('low_stock')) {
            $query->whereColumn('inventory_levels.quantity_available', '<=', 'inventory_levels.reorder_point');
        }

        $perPage = $request->input('per_page', 15);
        $levels = $query->paginate($perPage);

        return response()->json([
            'levels' => $levels->items(),
            'meta' => [
                'current_page' => $levels->currentPage(),
                'last_page' => $levels->lastPage(),
                'per_page' => $levels->perPage(),
                'total' => $levels->total(),
            ],
        ]);
    }

    /**
     * API: Return inventory movements as JSON.
     */
    public function apiMovements(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->current_organization_id ?? 1;

        $query = DB::table('inventory_movements')
            ->join('products', 'inventory_movements.product_id', '=', 'products.id')
            ->join('inventory_locations', 'inventory_movements.location_id', '=', 'inventory_locations.id')
            ->leftJoin('users', 'inventory_movements.created_by', '=', 'users.id')
            ->where('inventory_movements.organization_id', $organizationId)
            ->select(
                'inventory_movements.*',
                'products.name as product_name',
                'products.sku',
                'inventory_locations.name as location_name',
                'users.name as created_by_name'
            );

        if ($request->filled('product_id')) {
            $query->where('inventory_movements.product_id', $request->input('product_id'));
        }

        if ($request->filled('location_id')) {
            $query->where('inventory_movements.location_id', $request->input('location_id'));
        }

        if ($request->filled('movement_type')) {
            $query->where('inventory_movements.movement_type', $request->input('movement_type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('inventory_movements.created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('inventory_movements.created_at', '<=', $request->input('date_to'));
        }

        $query->orderBy('inventory_movements.created_at', 'desc');

        $perPage = $request->input('per_page', 15);
        $movements = $query->paginate($perPage);

        return response()->json([
            'movements' => $movements->items(),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    /**
     * API: Return inventory analytics as JSON.
     */
    public function apiAnalytics(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->current_organization_id ?? 1;

        $analytics = [
            'total_products' => DB::table('products')->where('organization_id', $organizationId)->whereNull('deleted_at')->count(),
            'total_categories' => DB::table('categories')->where('organization_id', $organizationId)->count(),
            'total_suppliers' => DB::table('suppliers')->where('organization_id', $organizationId)->count(),
            'inventory_valuation' => [
                'total_value' => DB::table('inventory_levels')
                    ->where('organization_id', $organizationId)
                    ->sum(DB::raw('quantity_on_hand * average_cost')),
                'total_quantity' => DB::table('inventory_levels')
                    ->where('organization_id', $organizationId)
                    ->sum('quantity_on_hand'),
            ],
            'low_stock_count' => DB::table('inventory_levels')
                ->where('organization_id', $organizationId)
                ->whereColumn('inventory_levels.quantity_available', '<=', 'inventory_levels.reorder_point')
                ->count(),
            'out_of_stock_count' => DB::table('inventory_levels')
                ->where('organization_id', $organizationId)
                ->where('quantity_on_hand', 0)
                ->count(),
        ];

        return response()->json([
            'analytics' => $analytics,
        ]);
    }

    /**
     * API: Return low stock products as JSON.
     */
    public function apiLowStockAlerts(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->current_organization_id ?? 1;

        $query = DB::table('inventory_levels')
            ->join('products', 'inventory_levels.product_id', '=', 'products.id')
            ->join('inventory_locations', 'inventory_levels.location_id', '=', 'inventory_locations.id')
            ->where('inventory_levels.organization_id', $organizationId)
            ->whereColumn('inventory_levels.quantity_available', '<=', 'inventory_levels.reorder_point')
            ->select(
                'inventory_levels.*',
                'products.name as product_name',
                'products.sku',
                'inventory_locations.name as location_name'
            )
            ->orderBy('inventory_levels.quantity_available');

        $perPage = $request->input('per_page', 15);
        $alerts = $query->paginate($perPage);

        return response()->json([
            'alerts' => $alerts->items(),
            'meta' => [
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'per_page' => $alerts->perPage(),
                'total' => $alerts->total(),
            ],
        ]);
    }

    /**
     * API: Adjust stock and return result JSON.
     */
    public function apiAdjustStock(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'location_id' => 'required|exists:inventory_locations,id',
            'quantity' => 'required|integer|not_in:0',
            'reason' => 'required|string|max:255',
        ]);

        $organizationId = auth()->user()->current_organization_id ?? 1;

        try {
            // Record the movement
            DB::table('inventory_movements')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'organization_id' => $organizationId,
                'product_id' => $request->input('product_id'),
                'location_id' => $request->input('location_id'),
                'movement_type' => $request->input('quantity') > 0 ? 'adjustment_in' : 'adjustment_out',
                'quantity' => abs($request->input('quantity')),
                'reason' => $request->input('reason'),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update inventory level
            DB::table('inventory_levels')
                ->where('organization_id', $organizationId)
                ->where('product_id', $request->input('product_id'))
                ->where('location_id', $request->input('location_id'))
                ->increment('quantity_on_hand', $request->input('quantity'));

            return response()->json([
                'success' => true,
                'message' => 'Stock adjustment completed successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust stock.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API: Transfer stock between locations and return JSON.
     */
    public function apiTransferStock(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_location_id' => 'required|exists:inventory_locations,id',
            'to_location_id' => 'required|exists:inventory_locations,id|different:from_location_id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $organizationId = auth()->user()->current_organization_id ?? 1;

        try {
            DB::beginTransaction();

            // Check available quantity
            $fromLevel = DB::table('inventory_levels')
                ->where('organization_id', $organizationId)
                ->where('product_id', $request->input('product_id'))
                ->where('location_id', $request->input('from_location_id'))
                ->first();

            if (! $fromLevel || $fromLevel->quantity_on_hand < $request->input('quantity')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock for transfer.',
                ], 422);
            }

            // Decrement from source
            DB::table('inventory_levels')
                ->where('organization_id', $organizationId)
                ->where('product_id', $request->input('product_id'))
                ->where('location_id', $request->input('from_location_id'))
                ->decrement('quantity_on_hand', $request->input('quantity'));

            // Increment at destination (upsert)
            DB::table('inventory_levels')
                ->updateOrInsert(
                    [
                        'organization_id' => $organizationId,
                        'product_id' => $request->input('product_id'),
                        'location_id' => $request->input('to_location_id'),
                    ],
                    [
                        'quantity_on_hand' => DB::raw('COALESCE(quantity_on_hand, 0) + '.$request->input('quantity')),
                        'updated_at' => now(),
                    ]
                );

            // Record movements
            $movementData = [
                'organization_id' => $organizationId,
                'product_id' => $request->input('product_id'),
                'quantity' => $request->input('quantity'),
                'reason' => 'Stock transfer: '.($request->input('notes') ?? 'N/A'),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('inventory_movements')->insert(array_merge($movementData, [
                'id' => \Illuminate\Support\Str::uuid(),
                'location_id' => $request->input('from_location_id'),
                'movement_type' => 'transfer_out',
            ]));

            DB::table('inventory_movements')->insert(array_merge($movementData, [
                'id' => \Illuminate\Support\Str::uuid(),
                'location_id' => $request->input('to_location_id'),
                'movement_type' => 'transfer_in',
            ]));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock transfer completed successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer stock.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API: Reserve stock and return JSON.
     */
    public function apiReserveStock(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'location_id' => 'required|exists:inventory_locations,id',
            'quantity' => 'required|integer|min:1',
            'reference' => 'nullable|string|max:255',
        ]);

        $organizationId = auth()->user()->current_organization_id ?? 1;

        try {
            $level = DB::table('inventory_levels')
                ->where('organization_id', $organizationId)
                ->where('product_id', $request->input('product_id'))
                ->where('location_id', $request->input('location_id'))
                ->first();

            if (! $level || $level->quantity_available < $request->input('quantity')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient available stock for reservation.',
                ], 422);
            }

            DB::table('inventory_levels')
                ->where('organization_id', $organizationId)
                ->where('product_id', $request->input('product_id'))
                ->where('location_id', $request->input('location_id'))
                ->update([
                    'quantity_reserved' => DB::raw('COALESCE(quantity_reserved, 0) + '.$request->input('quantity')),
                    'quantity_available' => DB::raw('quantity_available - '.$request->input('quantity')),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Stock reserved successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reserve stock.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API: Release reserved stock and return JSON.
     */
    public function apiReleaseStock(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'location_id' => 'required|exists:inventory_locations,id',
            'quantity' => 'required|integer|min:1',
            'reference' => 'nullable|string|max:255',
        ]);

        $organizationId = auth()->user()->current_organization_id ?? 1;

        try {
            $level = DB::table('inventory_levels')
                ->where('organization_id', $organizationId)
                ->where('product_id', $request->input('product_id'))
                ->where('location_id', $request->input('location_id'))
                ->first();

            if (! $level || ($level->quantity_reserved ?? 0) < $request->input('quantity')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient reserved stock to release.',
                ], 422);
            }

            DB::table('inventory_levels')
                ->where('organization_id', $organizationId)
                ->where('product_id', $request->input('product_id'))
                ->where('location_id', $request->input('location_id'))
                ->update([
                    'quantity_reserved' => DB::raw('quantity_reserved - '.$request->input('quantity')),
                    'quantity_available' => DB::raw('quantity_available + '.$request->input('quantity')),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Reserved stock released successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to release stock.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
