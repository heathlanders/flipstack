<?php

namespace App\Http\Controllers\Printing;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Inventory\WarehouseLocation;
use App\Models\Printing\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display a listing of warehouse locations.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $query = WarehouseLocation::where('organization_id', $organizationId);

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('location_code', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhere('aisle', 'LIKE', "%{$search}%");
            });
        }

        // Apply aisle filter
        if ($request->filled('aisle')) {
            $query->where('aisle', $request->get('aisle'));
        }

        // Apply active filter
        if ($request->filled('active_only') && $request->get('active_only') === 'true') {
            $query->where('is_active', true);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'location_code');
        $sortDirection = $request->get('sort_direction', 'asc');

        $allowedSorts = ['location_code', 'aisle', 'shelf', 'bin', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $locations = $query->paginate(24)->withQueryString();

        // Calculate stats
        $allLocations = WarehouseLocation::where('organization_id', $organizationId);
        $stats = [
            'total' => $allLocations->count(),
            'active' => (clone $allLocations)->where('is_active', true)->count(),
            'occupied' => Product::where('organization_id', $organizationId)->whereNotNull('warehouse_location_id')->distinct('warehouse_location_id')->count('warehouse_location_id'),
            'empty' => 0,
            'aisles' => (clone $allLocations)->distinct()->count('aisle'),
        ];

        // Get distinct aisles for filter dropdown
        $aisles = WarehouseLocation::where('organization_id', $organizationId)
            ->distinct()
            ->pluck('aisle')
            ->filter()
            ->values();

        return Inertia::render('Printing/Locations/Index', [
            'locations' => $locations,
            'stats' => $stats,
            'filters' => $request->only(['search', 'aisle', 'active_only', 'sort_by', 'sort_direction']),
            'aisles' => $aisles,
            'bulk_actions' => [
                'activate' => 'Activate Selected',
                'deactivate' => 'Deactivate Selected',
                'export' => 'Export to CSV',
            ],
        ]);
    }

    /**
     * Show the form for creating a new warehouse location.
     */
    public function create(): Response
    {
        return Inertia::render('Printing/Locations/Create', [
            'location_patterns' => [
                'aisle_shelf_bin' => 'Aisle-Shelf-Bin (A1-B2-C3)',
                'zone_row_position' => 'Zone-Row-Position (Z1-R5-P10)',
                'custom' => 'Custom Format',
            ],
        ]);
    }

    /**
     * Store a newly created warehouse location.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $validated = $request->validate([
            'location_code' => 'required|string|max:50|unique:warehouse_locations,location_code,NULL,id,organization_id,'.$organizationId,
            'description' => 'nullable|string|max:255',
            'aisle' => 'nullable|string|max:10',
            'shelf' => 'nullable|string|max:10',
            'bin' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);

        try {
            $validated['organization_id'] = $organizationId;
            $validated['is_active'] = $validated['is_active'] ?? true;

            $location = WarehouseLocation::create($validated);

            Log::info('Warehouse location created', [
                'location_id' => $location->id,
                'user_id' => $user->id,
                'organization_id' => $organizationId,
            ]);

            return redirect()->route('printing.locations.index')
                ->with('success', 'Location created successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to create warehouse location', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return back()->withErrors(['error' => 'Failed to create location.']);
        }
    }

    /**
     * Display the specified warehouse location.
     */
    public function show(WarehouseLocation $location): Response
    {
        $user = request()->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        // Verify organization access
        if ($location->organization_id !== $organizationId) {
            abort(403);
        }

        $products = Product::where('warehouse_location_id', $location->id)
            ->limit(50)
            ->get(['id', 'name', 'sku', 'status']);

        $recentJobs = PrintJob::where('organization_id', $organizationId)
            ->whereHas('product', fn ($q) => $q->where('warehouse_location_id', $location->id))
            ->with(['product:id,name,sku', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $analytics = [
            'labels_printed_week' => PrintJob::where('organization_id', $organizationId)
                ->whereHas('product', fn ($q) => $q->where('warehouse_location_id', $location->id))
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->startOfWeek())
                ->sum('quantity'),
            'labels_printed_month' => PrintJob::where('organization_id', $organizationId)
                ->whereHas('product', fn ($q) => $q->where('warehouse_location_id', $location->id))
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->startOfMonth())
                ->sum('quantity'),
            'most_printed_product' => null,
            'usage_trend' => 'stable',
        ];

        return Inertia::render('Printing/Locations/Show', [
            'location' => $location,
            'products' => $products,
            'recent_jobs' => $recentJobs,
            'analytics' => $analytics,
        ]);
    }

    /**
     * Show the form for editing the specified warehouse location.
     */
    public function edit(WarehouseLocation $location): Response
    {
        $user = request()->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        // Verify organization access
        if ($location->organization_id !== $organizationId) {
            abort(403);
        }

        return Inertia::render('Printing/Locations/Edit', [
            'location' => $location,
        ]);
    }

    /**
     * Update the specified warehouse location.
     */
    public function update(Request $request, WarehouseLocation $location)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        // Verify organization access
        if ($location->organization_id !== $organizationId) {
            abort(403);
        }

        $validated = $request->validate([
            'location_code' => 'required|string|max:50|unique:warehouse_locations,location_code,'.$location->id.',id,organization_id,'.$organizationId,
            'description' => 'nullable|string|max:255',
            'aisle' => 'nullable|string|max:10',
            'shelf' => 'nullable|string|max:10',
            'bin' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);

        try {
            $location->update($validated);

            Log::info('Warehouse location updated', [
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            return redirect()->route('printing.locations.show', $location->id)
                ->with('success', 'Location updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update warehouse location', [
                'error' => $e->getMessage(),
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            return back()->withErrors(['error' => 'Failed to update location.']);
        }
    }

    /**
     * Remove the specified warehouse location.
     */
    public function destroy(WarehouseLocation $location)
    {
        $user = request()->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        // Verify organization access
        if ($location->organization_id !== $organizationId) {
            abort(403);
        }

        try {
            $assignedProducts = Product::where('warehouse_location_id', $location->id)->count();
            if ($assignedProducts > 0) {
                return back()->withErrors(['error' => "Cannot delete location with {$assignedProducts} assigned product(s)."]);
            }

            $location->delete();

            Log::info('Warehouse location deleted', [
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            return redirect()->route('printing.locations.index')
                ->with('success', 'Location deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to delete warehouse location', [
                'error' => $e->getMessage(),
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            return back()->withErrors(['error' => 'Failed to delete location.']);
        }
    }

    /**
     * Bulk action handler.
     */
    public function bulkAction(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $validated = $request->validate([
            'action' => 'required|in:activate,deactivate,delete',
            'location_ids' => 'required|array|min:1',
            'location_ids.*' => 'integer|exists:warehouse_locations,id',
        ]);

        $locations = WarehouseLocation::where('organization_id', $organizationId)
            ->whereIn('id', $validated['location_ids'])
            ->get();

        if ($locations->isEmpty()) {
            return back()->withErrors(['error' => 'No valid locations selected.']);
        }

        try {
            switch ($validated['action']) {
                case 'activate':
                    WarehouseLocation::whereIn('id', $locations->pluck('id'))
                        ->update(['is_active' => true]);
                    $message = count($locations).' locations activated.';
                    break;

                case 'deactivate':
                    WarehouseLocation::whereIn('id', $locations->pluck('id'))
                        ->update(['is_active' => false]);
                    $message = count($locations).' locations deactivated.';
                    break;

                case 'delete':
                    WarehouseLocation::whereIn('id', $locations->pluck('id'))->delete();
                    $message = count($locations).' locations deleted.';
                    break;
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Failed to perform bulk action on warehouse locations', [
                'error' => $e->getMessage(),
                'action' => $validated['action'],
                'user_id' => $user->id,
            ]);

            return back()->withErrors(['error' => 'Failed to perform bulk action.']);
        }
    }
}
