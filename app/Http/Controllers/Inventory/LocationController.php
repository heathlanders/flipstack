<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreLocationRequest;
use App\Http\Requests\Inventory\UpdateLocationRequest;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\WarehouseBin;
use App\Models\Inventory\WarehouseZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display a listing of inventory locations.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $query = InventoryLocation::where('organization_id', $organizationId);

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhere('city', 'LIKE', "%{$search}%");
            });
        }

        // Apply type filter
        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        // Apply status filter
        if ($request->filled('status')) {
            $isActive = $request->get('status') === 'active';
            $query->where('is_active', $isActive);
        }

        // Apply sorting
        $sortBy = $request->get('sort', 'name');
        $sortDirection = 'asc';
        if (str_ends_with($sortBy, '_desc')) {
            $sortBy = str_replace('_desc', '', $sortBy);
            $sortDirection = 'desc';
        }

        $allowedSorts = ['name', 'code', 'type', 'city', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('name');
        }

        $locations = $query->paginate(12)->withQueryString();

        // Calculate stats
        $allLocations = InventoryLocation::where('organization_id', $organizationId);
        $stats = [
            'total' => $allLocations->count(),
            'active' => (clone $allLocations)->where('is_active', true)->count(),
            'warehouses' => (clone $allLocations)->where('type', 'warehouse')->count(),
            'stores' => (clone $allLocations)->where('type', 'store')->count(),
        ];

        // Get distinct types for filter dropdown
        $types = InventoryLocation::where('organization_id', $organizationId)
            ->distinct()
            ->pluck('type')
            ->filter()
            ->values();

        return Inertia::render('Inventory/Locations/Index', [
            'locations' => $locations,
            'stats' => $stats,
            'types' => $types,
            'filters' => $request->only(['search', 'type', 'status', 'sort']),
        ]);
    }

    /**
     * Show the form for creating a new location.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $printers = \App\Models\Printing\OrganizationPrinter::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->select('id', 'name', 'driver', 'location')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Locations/Create', [
            'types' => [
                'warehouse' => 'Warehouse',
                'store' => 'Store',
                'supplier' => 'Supplier Location',
                'customer' => 'Customer Location',
                'virtual' => 'Virtual Location',
            ],
            'printers' => $printers,
        ]);
    }

    /**
     * Store a newly created location in storage.
     */
    public function store(StoreLocationRequest $request)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        try {
            $data = $request->validated();
            $data['organization_id'] = $organizationId;

            // If setting as default, unset other defaults
            if (! empty($data['is_default'])) {
                InventoryLocation::where('organization_id', $organizationId)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $location = InventoryLocation::create($data);

            Log::info('Inventory location created', [
                'location_id' => $location->id,
                'user_id' => $user->id,
                'organization_id' => $organizationId,
            ]);

            return redirect()->route('inventory.locations.index')
                ->with('success', 'Location created successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to create inventory location', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return back()->withErrors(['error' => 'Failed to create location.']);
        }
    }

    /**
     * Display the specified location.
     */
    public function show(InventoryLocation $location): Response
    {
        $user = request()->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        // Verify organization access
        if ($location->organization_id !== $organizationId) {
            abort(403);
        }

        // Load relationships and calculate stats
        $location->loadCount(['inventoryLevels']);

        $inventoryStats = [
            'total_products' => $location->getTotalProductCount(),
            'total_value' => $location->getTotalInventoryValue(),
            'inventory_levels_count' => $location->inventory_levels_count ?? 0,
        ];

        // Zones + bins summary for this location
        $zoneRows = WarehouseZone::forLocation($location->id)
            ->withCount('bins')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $binIds = WarehouseBin::whereIn('zone_id', $zoneRows->pluck('id'))->pluck('id');

        $binsSummary = [
            'zones_count' => $zoneRows->count(),
            'bins_count' => $binIds->count(),
            'occupied_bins' => $binIds->isEmpty() ? 0 : InventoryLevel::whereIn('bin_id', $binIds)
                ->where('quantity_on_hand', '>', 0)
                ->distinct('bin_id')
                ->count('bin_id'),
            'zones' => $zoneRows->map(fn ($z) => [
                'id' => $z->id,
                'name' => $z->name,
                'code' => $z->code,
                'type' => $z->type,
                'bins_count' => (int) $z->bins_count,
                'is_active' => (bool) $z->is_active,
            ])->values(),
        ];

        // Top products at this location (limit 10), default by qty desc; client toggles to value
        $topProducts = InventoryLevel::with([
            'product:id,name,sku',
            'bin:id,code,zone_id',
            'bin.zone:id,name',
        ])
            ->where('location_id', $location->id)
            ->where('quantity_on_hand', '>', 0)
            ->orderByDesc('quantity_on_hand')
            ->limit(10)
            ->get()
            ->map(fn ($lvl) => [
                'product_id' => $lvl->product_id,
                'product_name' => $lvl->product?->name,
                'product_sku' => $lvl->product?->sku,
                'bin_code' => $lvl->bin?->code,
                'zone_name' => $lvl->bin?->zone?->name,
                'quantity_on_hand' => (int) $lvl->quantity_on_hand,
                'quantity_reserved' => (int) $lvl->quantity_reserved,
                'value' => (float) ($lvl->quantity_on_hand * ($lvl->average_cost ?? 0)),
            ])
            ->values();

        // Recent movements at this location (limit 20)
        $recentMovements = InventoryMovement::with([
            'product:id,name,sku',
            'creator:id,name',
        ])
            ->byLocation($location->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'type_label' => $m->getMovementTypeLabel(),
                'product_id' => $m->product_id,
                'product_name' => $m->product?->name,
                'product_sku' => $m->product?->sku,
                'quantity' => (int) $m->quantity,
                'user_name' => $m->creator?->name,
                'reason' => $m->reason,
                'created_at' => $m->created_at,
            ])
            ->values();

        return Inertia::render('Inventory/Locations/Show', [
            'location' => $location,
            'inventory_stats' => $inventoryStats,
            'bins_summary' => $binsSummary,
            'top_products' => $topProducts,
            'recent_movements' => $recentMovements,
        ]);
    }

    /**
     * Show the form for editing the specified location.
     */
    public function edit(InventoryLocation $location): Response
    {
        $user = request()->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        // Verify organization access
        if ($location->organization_id !== $organizationId) {
            abort(403);
        }

        $printers = \App\Models\Printing\OrganizationPrinter::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->select('id', 'name', 'driver', 'location')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Locations/Edit', [
            'location' => $location,
            'types' => [
                'warehouse' => 'Warehouse',
                'store' => 'Store',
                'supplier' => 'Supplier Location',
                'customer' => 'Customer Location',
                'virtual' => 'Virtual Location',
            ],
            'printers' => $printers,
        ]);
    }

    /**
     * Update the specified location in storage.
     */
    public function update(UpdateLocationRequest $request, InventoryLocation $location)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        // Verify organization access
        if ($location->organization_id !== $organizationId) {
            abort(403);
        }

        try {
            $data = $request->validated();

            // If setting as default, unset other defaults
            if (! empty($data['is_default']) && ! $location->is_default) {
                InventoryLocation::where('organization_id', $organizationId)
                    ->where('is_default', true)
                    ->where('id', '!=', $location->id)
                    ->update(['is_default' => false]);
            }

            $location->update($data);

            Log::info('Inventory location updated', [
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            return redirect()->route('inventory.locations.index')
                ->with('success', 'Location updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update inventory location', [
                'error' => $e->getMessage(),
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            return back()->withErrors(['error' => 'Failed to update location.']);
        }
    }

    /**
     * Remove the specified location from storage.
     */
    public function destroy(InventoryLocation $location)
    {
        $user = request()->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        // Verify organization access
        if ($location->organization_id !== $organizationId) {
            abort(403);
        }

        try {
            // Check if location has inventory
            $hasInventory = $location->inventoryLevels()
                ->where('quantity_on_hand', '>', 0)
                ->exists();

            if ($hasInventory) {
                return back()->withErrors([
                    'error' => 'Cannot delete location with active inventory. Transfer inventory first.',
                ]);
            }

            $location->delete();

            Log::info('Inventory location deleted', [
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            return redirect()->route('inventory.locations.index')
                ->with('success', 'Location deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to delete inventory location', [
                'error' => $e->getMessage(),
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            return back()->withErrors(['error' => 'Failed to delete location.']);
        }
    }

    /**
     * Get location analytics data.
     */
    public function analytics(InventoryLocation $location)
    {
        $user = request()->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        // Verify organization access
        if ($location->organization_id !== $organizationId) {
            abort(403);
        }

        // Get analytics data
        $analytics = [
            'total_products' => $location->getTotalProductCount(),
            'total_value' => $location->getTotalInventoryValue(),
            'top_products' => $location->inventoryLevels()
                ->with('product:id,name,sku')
                ->orderByDesc('quantity_on_hand')
                ->limit(10)
                ->get()
                ->map(fn ($level) => [
                    'product' => $level->product,
                    'quantity' => $level->quantity_on_hand,
                    'value' => $level->quantity_on_hand * ($level->average_cost ?? 0),
                ]),
            'recent_movements' => $location->inventoryMovements()
                ->with('product:id,name,sku')
                ->latest()
                ->limit(20)
                ->get(),
        ];

        return Inertia::render('Inventory/Locations/Analytics', [
            'location' => $location,
            'analytics' => $analytics,
        ]);
    }

    /**
     * Verify a location's address with EasyPost for ship-from use.
     */
    public function verifyShipFromAddress(Request $request, InventoryLocation $location)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        if ($location->organization_id !== $organizationId) {
            abort(403);
        }

        // Check required address fields
        if (! $location->address_line1 || ! $location->city || ! $location->state || ! $location->postal_code) {
            return back()->withErrors([
                'address' => 'Address Line 1, City, State, and Postal Code are required to verify a ship-from address.',
            ]);
        }

        try {
            $organization = $user->organization;
            $easyPostService = new \App\Services\Fulfillment\EasyPostService($organization);

            $address = $easyPostService->createAddress([
                'name' => $location->contact_person ?? $location->name,
                'street1' => $location->address_line1,
                'street2' => $location->address_line2,
                'city' => $location->city,
                'state' => $location->state,
                'zip' => $location->postal_code,
                'country' => $location->country ?? 'US',
                'phone' => $location->phone,
                'email' => $location->email,
            ]);

            $verified = $easyPostService->verifyAddress($address->id ?? $address['id'] ?? null);

            $location->update([
                'easypost_address_id' => $address->id ?? $address['id'] ?? null,
                'ship_from_verified' => true,
                'is_ship_from' => true,
            ]);

            Log::info('Ship-from address verified via EasyPost', [
                'location_id' => $location->id,
                'easypost_address_id' => $location->easypost_address_id,
                'user_id' => $user->id,
            ]);

            return back()->with('success', 'Ship-from address verified successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to verify ship-from address', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return back()->withErrors([
                'address' => 'Address verification failed: '.$e->getMessage(),
            ]);
        }
    }

    // API Methods

    /**
     * API: Return locations as JSON.
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $query = InventoryLocation::where('organization_id', $organizationId);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhere('city', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sortBy = $request->input('sort_by', 'name');
        $sortOrder = $request->input('sort_order', 'asc');
        $allowedSorts = ['name', 'code', 'type', 'city', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = $request->input('per_page', 15);
        $locations = $query->paginate($perPage);

        return response()->json([
            'locations' => $locations->items(),
            'meta' => [
                'current_page' => $locations->currentPage(),
                'last_page' => $locations->lastPage(),
                'per_page' => $locations->perPage(),
                'total' => $locations->total(),
            ],
        ]);
    }

    /**
     * API: Create location and return JSON.
     */
    public function apiStore(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'type' => 'required|string|in:warehouse,store,supplier,customer,virtual',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $data = $validator->validated();
            $data['organization_id'] = $organizationId;

            if (! empty($data['is_default'])) {
                InventoryLocation::where('organization_id', $organizationId)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $location = InventoryLocation::create($data);

            Log::info('Inventory location created via API', [
                'location_id' => $location->id,
                'user_id' => $user->id,
                'organization_id' => $organizationId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Location created successfully.',
                'location' => $location,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create inventory location via API', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create location.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API: Return single location as JSON.
     */
    public function apiShow(InventoryLocation $location): JsonResponse
    {
        $user = request()->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        if ($location->organization_id !== $organizationId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $location->loadCount(['inventoryLevels']);

        return response()->json([
            'location' => $location,
            'inventory_stats' => [
                'total_products' => $location->getTotalProductCount(),
                'total_value' => $location->getTotalInventoryValue(),
                'inventory_levels_count' => $location->inventory_levels_count ?? 0,
            ],
        ]);
    }

    /**
     * API: Update location and return JSON.
     */
    public function apiUpdate(Request $request, InventoryLocation $location): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        if ($location->organization_id !== $organizationId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'type' => 'required|string|in:warehouse,store,supplier,customer,virtual',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $data = $validator->validated();

            if (! empty($data['is_default']) && ! $location->is_default) {
                InventoryLocation::where('organization_id', $organizationId)
                    ->where('is_default', true)
                    ->where('id', '!=', $location->id)
                    ->update(['is_default' => false]);
            }

            $location->update($data);

            Log::info('Inventory location updated via API', [
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully.',
                'location' => $location->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update inventory location via API', [
                'error' => $e->getMessage(),
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update location.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API: Delete location and return JSON.
     */
    public function apiDestroy(InventoryLocation $location): JsonResponse
    {
        $user = request()->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        if ($location->organization_id !== $organizationId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $hasInventory = $location->inventoryLevels()
                ->where('quantity_on_hand', '>', 0)
                ->exists();

            if ($hasInventory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete location with active inventory. Transfer inventory first.',
                ], 422);
            }

            $location->delete();

            Log::info('Inventory location deleted via API', [
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Location deleted successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete inventory location via API', [
                'error' => $e->getMessage(),
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete location.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
