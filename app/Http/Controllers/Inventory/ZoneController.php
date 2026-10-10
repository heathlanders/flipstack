<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\WarehouseBin;
use App\Models\Inventory\WarehouseZone;
use App\Services\Inventory\BinManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ZoneController extends Controller
{
    public function __construct(
        protected BinManagementService $binService
    ) {
        $this->middleware(['auth']);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $locations = InventoryLocation::where('organization_id', $organizationId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $selectedLocationId = $request->get('location_id', $locations->first()?->id);

        $zones = [];
        $binStats = ['total_bins' => 0, 'active_bins' => 0, 'total_zones' => 0];

        if ($selectedLocationId) {
            $zones = WarehouseZone::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('location_id', $selectedLocationId)
                ->withCount(['bins', 'bins as active_bins_count' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('sort_order')
                ->get();

            $zoneIds = $zones->pluck('id');
            $aggregates = InventoryLevel::query()
                ->join('warehouse_bins', 'warehouse_bins.id', '=', 'inventory_levels.bin_id')
                ->whereIn('warehouse_bins.zone_id', $zoneIds)
                ->groupBy('warehouse_bins.zone_id')
                ->selectRaw('warehouse_bins.zone_id, COUNT(DISTINCT inventory_levels.product_id) AS products_count, COALESCE(SUM(inventory_levels.quantity_on_hand), 0) AS total_qty')
                ->get()
                ->keyBy('zone_id');

            $zones->each(function ($zone) use ($aggregates) {
                $agg = $aggregates->get($zone->id);
                $zone->products_count = (int) ($agg->products_count ?? 0);
                $zone->total_qty = (int) ($agg->total_qty ?? 0);
            });

            $binStats['total_zones'] = $zones->count();
            $binStats['total_bins'] = $zones->sum('bins_count');
            $binStats['active_bins'] = $zones->sum('active_bins_count');
        }

        return Inertia::render('Inventory/Warehouse/Zones', [
            'locations' => $locations,
            'selectedLocationId' => $selectedLocationId,
            'zones' => $zones,
            'stats' => $binStats,
            'zoneTypes' => [
                'receiving' => 'Receiving',
                'storage' => 'Storage',
                'picking' => 'Picking',
                'shipping' => 'Shipping',
                'returns' => 'Returns',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $validated = $request->validate([
            'location_id' => 'required|uuid|exists:inventory_locations,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
            'type' => 'required|string|in:receiving,storage,picking,shipping,returns',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $this->binService->createZone($validated, $organizationId);

        return redirect()->back()->with('success', 'Zone created.');
    }

    public function update(Request $request, WarehouseZone $zone)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
            'type' => 'required|string|in:receiving,storage,picking,shipping,returns',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $zone->update($validated);

        return redirect()->back()->with('success', 'Zone updated.');
    }

    public function destroy(WarehouseZone $zone)
    {
        if ($zone->bins()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete a zone that has bins. Remove bins first.');
        }

        $zone->delete();

        return redirect()->back()->with('success', 'Zone deleted.');
    }

    public function generateBins(Request $request, WarehouseZone $zone)
    {
        $validated = $request->validate([
            'aisles' => 'required|integer|min:1|max:50',
            'racks' => 'required|integer|min:1|max:50',
            'shelves' => 'required|integer|min:1|max:20',
        ]);

        $count = $this->binService->generateBinsForZone($zone, $validated['aisles'], $validated['racks'], $validated['shelves']);

        return redirect()->back()->with('success', "{$count} bins generated.");
    }

    public function bins(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $locations = InventoryLocation::where('organization_id', $organizationId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $selectedLocationId = $request->get('location_id', $locations->first()?->id);

        $query = WarehouseBin::query()
            ->whereHas('zone', fn ($q) => $q->withoutGlobalScopes()->where('organization_id', $organizationId)
                ->when($selectedLocationId, fn ($zq) => $zq->where('location_id', $selectedLocationId)))
            ->with('zone:id,name,code,type')
            ->withCount('inventoryLevels')
            ->addSelect([
                'total_qty' => InventoryLevel::selectRaw('COALESCE(SUM(quantity_on_hand), 0)')
                    ->whereColumn('bin_id', 'warehouse_bins.id'),
            ]);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ILIKE', "%{$search}%")
                    ->orWhere('barcode', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->get('zone_id'));
        }

        if ($request->boolean('with_stock')) {
            $query->whereHas('inventoryLevels', fn ($q) => $q->where('quantity_on_hand', '>', 0));
        }

        $sort = $request->get('sort', 'code');
        $dir = strtolower($request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowed = ['code', 'current_count', 'max_capacity', 'is_active', 'barcode', 'inventory_levels_count', 'total_qty'];
        if ($sort === 'zone') {
            $query->join('warehouse_zones', 'warehouse_zones.id', '=', 'warehouse_bins.zone_id')
                ->orderBy('warehouse_zones.code', $dir)
                ->select('warehouse_bins.*');
        } elseif (in_array($sort, $allowed, true)) {
            $query->orderBy($sort, $dir);
        } else {
            $sort = 'code';
            $query->orderBy('code', $dir);
        }

        $bins = $query->paginate(50)->withQueryString();

        $zones = $selectedLocationId
            ? WarehouseZone::withoutGlobalScopes()->where('organization_id', $organizationId)->where('location_id', $selectedLocationId)->select('id', 'name', 'code')->get()
            : collect();

        return Inertia::render('Inventory/Warehouse/Bins', [
            'bins' => $bins,
            'locations' => $locations,
            'zones' => $zones,
            'selectedLocationId' => $selectedLocationId,
            'filters' => array_merge($request->only('search', 'zone_id', 'location_id', 'with_stock'), ['sort' => $sort, 'dir' => $dir]),
        ]);
    }

    public function storeBin(Request $request)
    {
        $validated = $request->validate([
            'zone_id' => 'required|uuid|exists:warehouse_zones,id',
            'code' => 'required|string|max:50',
            'label' => 'nullable|string|max:255',
            'aisle' => 'nullable|string|max:10',
            'rack' => 'nullable|string|max:10',
            'shelf' => 'nullable|string|max:10',
            'bin_position' => 'nullable|string|max:10',
            'max_capacity' => 'nullable|integer|min:1',
        ]);

        $this->binService->createBin($validated);

        return redirect()->back()->with('success', 'Bin created.');
    }

    public function updateBin(Request $request, WarehouseBin $bin)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'label' => 'nullable|string|max:255',
            'max_capacity' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $bin->update($validated);

        return redirect()->back()->with('success', 'Bin updated.');
    }

    public function destroyBin(WarehouseBin $bin)
    {
        if ($bin->inventoryLevels()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete a bin that has inventory assigned.');
        }

        $bin->delete();

        return redirect()->back()->with('success', 'Bin deleted.');
    }

    public function show(Request $request, WarehouseZone $zone): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        abort_if($zone->organization_id !== $organizationId, 404);

        $zone->load('location:id,name');

        $binsQuery = WarehouseBin::query()
            ->where('zone_id', $zone->id)
            ->withCount('inventoryLevels')
            ->addSelect([
                'total_qty' => InventoryLevel::selectRaw('COALESCE(SUM(quantity_on_hand), 0)')
                    ->whereColumn('bin_id', 'warehouse_bins.id'),
            ]);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $binsQuery->where(function ($q) use ($search) {
                $q->where('code', 'ILIKE', "%{$search}%")
                    ->orWhere('barcode', 'ILIKE', "%{$search}%")
                    ->orWhere('label', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->boolean('with_stock')) {
            $binsQuery->whereHas('inventoryLevels', fn ($q) => $q->where('quantity_on_hand', '>', 0));
        }

        $sort = $request->get('sort', 'code');
        $dir = strtolower($request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowed = ['code', 'label', 'current_count', 'max_capacity', 'is_active', 'barcode', 'inventory_levels_count', 'total_qty'];
        if (! in_array($sort, $allowed, true)) {
            $sort = 'code';
        }
        $binsQuery->orderBy($sort, $dir);

        $bins = $binsQuery->paginate(50)->withQueryString();

        $summary = DB::table('inventory_levels')
            ->join('warehouse_bins', 'warehouse_bins.id', '=', 'inventory_levels.bin_id')
            ->where('warehouse_bins.zone_id', $zone->id)
            ->selectRaw('COUNT(DISTINCT inventory_levels.product_id) AS distinct_products, COALESCE(SUM(inventory_levels.quantity_on_hand), 0) AS total_qty, COALESCE(SUM(inventory_levels.quantity_reserved), 0) AS total_reserved')
            ->first();

        $stats = [
            'total_bins' => (int) WarehouseBin::where('zone_id', $zone->id)->count(),
            'active_bins' => (int) WarehouseBin::where('zone_id', $zone->id)->where('is_active', true)->count(),
            'empty_bins' => (int) WarehouseBin::where('zone_id', $zone->id)
                ->whereDoesntHave('inventoryLevels', fn ($q) => $q->where('quantity_on_hand', '>', 0))
                ->count(),
            'distinct_products' => (int) ($summary->distinct_products ?? 0),
            'total_qty' => (int) ($summary->total_qty ?? 0),
            'total_reserved' => (int) ($summary->total_reserved ?? 0),
        ];

        return Inertia::render('Inventory/Warehouse/ZoneDetail', [
            'zone' => $zone,
            'bins' => $bins,
            'stats' => $stats,
            'filters' => array_merge($request->only('search', 'with_stock'), ['sort' => $sort, 'dir' => $dir]),
        ]);
    }

    public function showBin(Request $request, WarehouseBin $bin): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $bin->load(['zone' => function ($q) {
            $q->withoutGlobalScopes()->with('location:id,name');
        }]);

        abort_if($bin->zone?->organization_id !== $organizationId, 404);

        $sort = $request->get('sort', 'quantity_on_hand');
        $dir = strtolower($request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $levelsQuery = InventoryLevel::query()
            ->where('bin_id', $bin->id)
            ->with([
                'product:id,name,sku,available_quantity,stock_quantity,featured_image,brand_id,status',
                'product.brand:id,name',
                'product.primaryImage:id,product_id,url,is_primary',
            ]);

        $direct = ['quantity_on_hand', 'quantity_reserved'];
        if (in_array($sort, $direct, true)) {
            $levelsQuery->orderBy($sort, $dir);
        } elseif ($sort === 'product_name') {
            $levelsQuery->leftJoin('products', 'products.id', '=', 'inventory_levels.product_id')
                ->orderBy('products.name', $dir)
                ->select('inventory_levels.*');
        } elseif ($sort === 'product_sku') {
            $levelsQuery->leftJoin('products', 'products.id', '=', 'inventory_levels.product_id')
                ->orderBy('products.sku', $dir)
                ->select('inventory_levels.*');
        } elseif ($sort === 'product_brand') {
            $levelsQuery->leftJoin('products', 'products.id', '=', 'inventory_levels.product_id')
                ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
                ->orderBy('brands.name', $dir)
                ->select('inventory_levels.*');
        } else {
            $sort = 'quantity_on_hand';
            $levelsQuery->orderBy('quantity_on_hand', $dir);
        }

        $levels = $levelsQuery->paginate(50)->withQueryString();

        $summary = DB::table('inventory_levels')
            ->where('bin_id', $bin->id)
            ->selectRaw('COUNT(DISTINCT product_id) AS distinct_products, COALESCE(SUM(quantity_on_hand), 0) AS total_qty, COALESCE(SUM(quantity_reserved), 0) AS total_reserved')
            ->first();

        return Inertia::render('Inventory/Warehouse/BinDetail', [
            'bin' => $bin,
            'zone' => $bin->zone,
            'levels' => $levels,
            'stats' => [
                'distinct_products' => (int) ($summary->distinct_products ?? 0),
                'total_qty' => (int) ($summary->total_qty ?? 0),
                'total_reserved' => (int) ($summary->total_reserved ?? 0),
            ],
            'filters' => ['sort' => $sort, 'dir' => $dir],
        ]);
    }
}
