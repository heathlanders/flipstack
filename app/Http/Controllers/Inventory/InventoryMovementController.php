<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockMovementRequest;
use App\Http\Resources\InventoryLevelResource;
use App\Http\Resources\InventoryMovementResource;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class InventoryMovementController extends Controller
{
    /**
     * Get the current organization ID from the authenticated user.
     */
    protected function getOrganizationId(): int
    {
        $user = auth()->user();

        return $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;
    }

    /**
     * Display a listing of inventory movements.
     */
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $organizationId = $this->getOrganizationId();

        if (! $user->hasPermission('inventory.view')) {
            abort(403, 'You do not have permission to view inventory movements.');
        }

        $movements = InventoryMovement::where('organization_id', $organizationId)
            ->with([
                'product:id,name,sku,stock_quantity',
                'location:id,name,type',
                'createdBy:id,name',
            ])
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->location_id, fn ($q, $id) => $q->where('location_id', $id))
            ->when($request->date_from, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($request->search, fn ($q, $search) => $q->whereHas('product', fn ($pq) => $pq->where('name', 'ilike', "%{$search}%")
                ->orWhere('sku', 'ilike', "%{$search}%")
            )
            )
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_direction ?? 'desc')
            ->paginate($request->per_page ?? 20)
            ->withQueryString();

        return Inertia::render('Inventory/Movements/Index', [
            'movements' => InventoryMovementResource::collection($movements),
            'filters' => $request->only([
                'type',
                'product_id',
                'location_id',
                'date_from',
                'date_to',
                'search',
                'sort_by',
                'sort_direction',
            ]),
            'filterOptions' => $this->getFilterOptions($organizationId),
            'permissions' => [
                'can_create' => $user->hasPermission('inventory.create'),
                'can_adjust' => $user->hasPermission('inventory.update'),
                'can_transfer' => $user->hasPermission('inventory.update'),
                'can_export' => $user->hasPermission('inventory.export'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new movement.
     */
    public function create(Request $request): Response
    {
        $user = auth()->user();
        $organizationId = $this->getOrganizationId();

        if (! $user->hasPermission('inventory.update')) {
            abort(403, 'You do not have permission to create stock movements.');
        }

        $preselectedProduct = null;
        if ($request->product_id) {
            $preselectedProduct = Product::where('organization_id', $organizationId)
                ->where('id', $request->product_id)
                ->first(['id', 'name', 'sku', 'stock_quantity', 'manage_stock', 'allow_backorders', 'low_stock_threshold']);
        }

        return Inertia::render('Inventory/Movements/Create', [
            'locations' => InventoryLocation::where('organization_id', $organizationId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
            'reasonCodes' => $this->getReasonCodes(),
            'movementTypes' => InventoryMovement::getMovementTypesWithLabels(),
            'preselectedProduct' => $preselectedProduct,
            'permissions' => [
                'can_adjust' => $user->hasPermission('inventory.update'),
                'can_transfer' => $user->hasPermission('inventory.update'),
            ],
        ]);
    }

    /**
     * Store a newly created movement.
     */
    public function store(StockMovementRequest $request): RedirectResponse|JsonResponse
    {
        $user = auth()->user();
        $organizationId = $this->getOrganizationId();
        $validated = $request->validated();

        try {
            $affectedProductIds = [];
            $movements = DB::transaction(function () use ($validated, $organizationId, $user, &$affectedProductIds) {
                $createdMovements = [];

                foreach ($validated['items'] as $item) {
                    $affectedProductIds[$item['product_id']] = true;
                    // Get current inventory level for balance tracking
                    $inventoryLevel = InventoryLevel::where('organization_id', $organizationId)
                        ->where('product_id', $item['product_id'])
                        ->where('location_id', $item['location_id'])
                        ->first();

                    $balanceBefore = $inventoryLevel?->quantity_on_hand ?? 0;

                    // Create the movement
                    $movement = InventoryMovement::create([
                        'organization_id' => $organizationId,
                        'product_id' => $item['product_id'],
                        'product_variation_id' => $item['product_variation_id'] ?? null,
                        'location_id' => $item['location_id'],
                        'type' => $validated['type'],
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'] ?? null,
                        'total_cost' => isset($item['unit_cost']) ? $item['quantity'] * $item['unit_cost'] : null,
                        'reason' => $validated['reason'] ?? null,
                        'notes' => $validated['notes'] ?? null,
                        'reference_type' => $validated['reference_type'] ?? null,
                        'reference_id' => $validated['reference_id'] ?? null,
                        'reference_number' => $validated['reference_number'] ?? null,
                        'created_by' => $user->id,
                        'balance_before' => $balanceBefore,
                    ]);

                    // Update inventory level
                    $this->updateInventoryLevel($movement, $organizationId);

                    // Update balance_after
                    $inventoryLevel = InventoryLevel::where('organization_id', $organizationId)
                        ->where('product_id', $item['product_id'])
                        ->where('location_id', $item['location_id'])
                        ->first();

                    $movement->update(['balance_after' => $inventoryLevel?->quantity_on_hand ?? 0]);

                    $createdMovements[] = $movement->fresh();
                }

                return $createdMovements;
            });

            // Push updated quantity to marketplaces for each affected product.
            foreach (array_keys($affectedProductIds) as $productId) {
                \App\Jobs\Inventory\PushInventoryToMarketplaces::dispatch($productId);
            }

            $message = count($movements) === 1
                ? 'Stock movement recorded successfully.'
                : count($movements).' stock movements recorded successfully.';

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'movements' => InventoryMovementResource::collection(collect($movements)),
                ]);
            }

            return redirect()->route('inventory.movements.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Stock movement failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'data' => $validated,
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process stock movement: '.$e->getMessage(),
                ], 500);
            }

            return back()
                ->withInput()
                ->withErrors(['general' => 'Failed to process stock movement. Please try again.']);
        }
    }

    /**
     * Display the specified movement.
     */
    public function show(InventoryMovement $movement): Response|JsonResponse
    {
        $user = auth()->user();
        $organizationId = $this->getOrganizationId();

        if ($movement->organization_id !== $organizationId) {
            abort(404);
        }

        if (! $user->hasPermission('inventory.view')) {
            abort(403, 'You do not have permission to view inventory movements.');
        }

        $movement->load([
            'product:id,name,sku,stock_quantity',
            'location:id,name,type',
            'createdBy:id,name,email',
        ]);

        if (request()->wantsJson()) {
            return response()->json([
                'movement' => new InventoryMovementResource($movement),
            ]);
        }

        return Inertia::render('Inventory/Movements/Show', [
            'movement' => new InventoryMovementResource($movement),
        ]);
    }

    /**
     * Get stock levels for a specific product.
     */
    public function productStockLevels(Product $product): JsonResponse
    {
        $organizationId = $this->getOrganizationId();

        if ($product->organization_id !== $organizationId) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $levels = InventoryLevel::where('organization_id', $organizationId)
            ->where('product_id', $product->id)
            ->with('location:id,name,type')
            ->get();

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock_quantity' => $product->stock_quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
                'manage_stock' => $product->manage_stock,
                'allow_backorders' => $product->allow_backorders,
            ],
            'levels' => InventoryLevelResource::collection($levels),
            'summary' => [
                'total_on_hand' => $levels->sum('quantity_on_hand'),
                'total_reserved' => $levels->sum('quantity_reserved'),
                'total_available' => $levels->sum('quantity_available'),
                'total_value' => $levels->sum('total_cost'),
                'location_count' => $levels->count(),
            ],
        ]);
    }

    /**
     * Get available locations for API.
     */
    public function locations(): JsonResponse
    {
        $organizationId = $this->getOrganizationId();

        $locations = InventoryLocation::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return response()->json([
            'locations' => $locations,
        ]);
    }

    /**
     * Get movements for API with pagination.
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $user = auth()->user();
        $organizationId = $this->getOrganizationId();

        if (! $user->hasPermission('inventory.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $movements = InventoryMovement::where('organization_id', $organizationId)
            ->with([
                'product:id,name,sku',
                'location:id,name',
                'createdBy:id,name',
            ])
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->location_id, fn ($q, $id) => $q->where('location_id', $id))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'movements' => InventoryMovementResource::collection($movements),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    /**
     * Get available reason codes.
     */
    public function reasonCodes(): JsonResponse
    {
        return response()->json([
            'reason_codes' => $this->getReasonCodes(),
        ]);
    }

    /**
     * Update inventory level based on movement.
     */
    protected function updateInventoryLevel(InventoryMovement $movement, int $organizationId): void
    {
        $level = InventoryLevel::getOrCreateForProductLocation(
            $organizationId,
            $movement->product_id,
            $movement->location_id,
            $movement->product_variation_id
        );

        $quantityChange = match ($movement->type) {
            InventoryMovement::TYPE_PURCHASE,
            InventoryMovement::TYPE_TRANSFER_IN,
            InventoryMovement::TYPE_RETURN,
            InventoryMovement::TYPE_FOUND => abs($movement->quantity),
            InventoryMovement::TYPE_SALE,
            InventoryMovement::TYPE_TRANSFER_OUT,
            InventoryMovement::TYPE_DAMAGED,
            InventoryMovement::TYPE_LOST => -abs($movement->quantity),
            InventoryMovement::TYPE_ADJUSTMENT => $movement->quantity,
            default => 0,
        };

        if ($quantityChange > 0) {
            $level->addStock($quantityChange, $movement->unit_cost);
        } elseif ($quantityChange < 0) {
            $level->removeStock(abs($quantityChange));
        }

        // Also update the main product stock_quantity AND mirror to available_quantity
        // so manual movements propagate to marketplace listings.
        $product = $movement->product;
        if ($product && $product->manage_stock) {
            $product->increment('stock_quantity', $quantityChange);
            if ($quantityChange > 0) {
                $product->incrementAvailableQuantity($quantityChange);
            } elseif ($quantityChange < 0) {
                $product->decrementAvailableQuantity(abs($quantityChange));
            }
        }
    }

    /**
     * Get filter options for the movements list.
     */
    protected function getFilterOptions(int $organizationId): array
    {
        return [
            'movementTypes' => InventoryMovement::getMovementTypesWithLabels(),
            'locations' => InventoryLocation::where('organization_id', $organizationId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
        ];
    }

    /**
     * Get reason codes with labels.
     */
    protected function getReasonCodes(): array
    {
        return [
            ['value' => 'recount', 'label' => 'Physical Recount'],
            ['value' => 'damaged', 'label' => 'Damaged'],
            ['value' => 'theft', 'label' => 'Theft/Loss'],
            ['value' => 'found', 'label' => 'Found Stock'],
            ['value' => 'received', 'label' => 'Received Shipment'],
            ['value' => 'returned', 'label' => 'Customer Return'],
            ['value' => 'correction', 'label' => 'Data Correction'],
            ['value' => 'expired', 'label' => 'Expired'],
            ['value' => 'quality', 'label' => 'Quality Issue'],
            ['value' => 'other', 'label' => 'Other'],
        ];
    }
}
