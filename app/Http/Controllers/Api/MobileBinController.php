<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\WarehouseBin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileBinController extends Controller
{
    use ResolvesOrganization;

    /**
     * GET /api/bins
     * List bins, optionally filtered by location/zone or a code/barcode search.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'location_id' => 'nullable|uuid',
            'zone_id' => 'nullable|uuid',
            'search' => 'nullable|string|max:100',
        ]);

        $organizationId = $this->organizationId($request);

        $query = WarehouseBin::query()
            ->with('zone:id,name,code,location_id')
            ->withCount('inventoryLevels')
            ->whereHas('zone', function ($q) use ($request, $organizationId) {
                $q->where('organization_id', $organizationId);
                if ($request->filled('location_id')) {
                    $q->where('location_id', $request->location_id);
                }
            });

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('code', 'ilike', "%{$search}%")->orWhere('barcode', 'ilike', "%{$search}%"));
        }

        $bins = $query->orderBy('code')->limit(200)->get();

        return response()->json([
            'success' => true,
            'data' => $bins->map(fn ($bin) => $this->formatBin($bin)),
        ]);
    }

    /**
     * GET /api/bins/scan?barcode=...
     * Resolve a scanned bin barcode to the bin and its contents.
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate(['barcode' => 'required|string|max:100']);

        $organizationId = $this->organizationId($request);

        $bin = WarehouseBin::byBarcode($request->barcode)
            ->whereHas('zone', fn ($q) => $q->where('organization_id', $organizationId))
            ->with('zone:id,name,code,location_id')
            ->first();

        if (! $bin) {
            return response()->json(['success' => false, 'message' => 'No bin matches that barcode.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatBin($bin, withContents: true),
        ]);
    }

    /**
     * GET /api/bins/{bin}
     * Bin detail with contents.
     */
    public function show(Request $request, WarehouseBin $bin): JsonResponse
    {
        if (! $this->binBelongsToOrg($bin, $this->organizationId($request))) {
            return response()->json(['success' => false, 'message' => 'Bin not found'], 404);
        }

        $bin->load('zone:id,name,code,location_id');

        return response()->json([
            'success' => true,
            'data' => $this->formatBin($bin, withContents: true),
        ]);
    }

    /**
     * POST /api/bins/{bin}/assign
     * Place a product (by id or scanned barcode) into this bin at the bin's location.
     */
    public function assign(Request $request, WarehouseBin $bin): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        if (! $this->binBelongsToOrg($bin, $organizationId)) {
            return response()->json(['success' => false, 'message' => 'Bin not found'], 404);
        }

        $request->validate([
            'product_id' => 'required_without:barcode|nullable|uuid',
            'barcode' => 'required_without:product_id|nullable|string|max:100',
        ]);

        if (! $bin->is_active) {
            return response()->json(['success' => false, 'message' => 'Bin is not active.'], 422);
        }

        $product = $this->resolveProduct($request, $organizationId);
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found for the given barcode/id.'], 404);
        }

        $locationId = $bin->zone->location_id;
        if (! $locationId) {
            return response()->json(['success' => false, 'message' => 'Bin zone has no location.'], 422);
        }

        try {
            $level = DB::transaction(function () use ($product, $organizationId, $locationId, $bin) {
                $level = InventoryLevel::where('organization_id', $organizationId)
                    ->where('product_id', $product->id)
                    ->where('location_id', $locationId)
                    ->lockForUpdate()
                    ->first();

                if (! $level) {
                    $level = InventoryLevel::create([
                        'organization_id' => $organizationId,
                        'product_id' => $product->id,
                        'location_id' => $locationId,
                        'quantity_on_hand' => 0,
                        'quantity_reserved' => 0,
                    ]);
                }

                // Already in this bin: no-op.
                if ($level->bin_id === $bin->id) {
                    return $level;
                }

                $freshBin = WarehouseBin::lockForUpdate()->find($bin->id);
                if ($freshBin->max_capacity !== null && $freshBin->current_count >= $freshBin->max_capacity) {
                    throw new \RuntimeException('Bin is at capacity.');
                }

                // Decrement the previous bin's occupancy, if any.
                if ($level->bin_id) {
                    WarehouseBin::where('id', $level->bin_id)->where('current_count', '>', 0)->decrement('current_count');
                }

                $level->update(['bin_id' => $bin->id]);
                $freshBin->increment('current_count');

                return $level;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        audit_event(
            action: 'inventory.bin_assigned',
            description: "Placed {$product->name} into bin {$bin->full_code}",
            subject: $level,
            metadata: [
                'product_id' => (string) $product->id,
                'bin_id' => (string) $bin->id,
                'bin_code' => $bin->full_code,
                'location_id' => (string) $locationId,
                'source' => 'mobile',
            ],
            severity: 'info',
            category: 'inventory',
        );

        $bin->load('zone:id,name,code,location_id');

        return response()->json([
            'success' => true,
            'message' => "Placed {$product->name} into {$bin->full_code}",
            'data' => $this->formatBin($bin->fresh('zone'), withContents: true),
        ]);
    }

    private function resolveProduct(Request $request, int $organizationId): ?Product
    {
        if ($request->filled('product_id')) {
            return Product::where('organization_id', $organizationId)->find($request->product_id);
        }

        $scanned = $request->barcode;

        return Product::where('organization_id', $organizationId)
            ->where(function ($q) use ($scanned) {
                $q->where('barcode', $scanned)
                    ->orWhere('sku', $scanned)
                    ->orWhere('internal_sku', $scanned)
                    ->orWhereHas('identifiers', fn ($iq) => $iq->where('value', $scanned));
            })
            ->first();
    }

    private function binBelongsToOrg(WarehouseBin $bin, int $organizationId): bool
    {
        $bin->loadMissing('zone:id,organization_id,location_id');

        return $bin->zone && (int) $bin->zone->organization_id === $organizationId;
    }

    private function organizationId(Request $request): int
    {
        return $this->resolveOrganizationId($request->user())
            ?? abort(response()->json([
                'success' => false,
                'message' => 'No organization associated with this account.',
            ], 422));
    }

    private function formatBin(WarehouseBin $bin, bool $withContents = false): array
    {
        $data = [
            'id' => $bin->id,
            'code' => $bin->code,
            'full_code' => $bin->full_code,
            'barcode' => $bin->barcode,
            'aisle' => $bin->aisle,
            'rack' => $bin->rack,
            'shelf' => $bin->shelf,
            'is_active' => $bin->is_active,
            'max_capacity' => $bin->max_capacity,
            'current_count' => $bin->current_count,
            'items_count' => $bin->inventory_levels_count ?? $bin->current_count,
            'zone' => $bin->relationLoaded('zone') && $bin->zone ? [
                'id' => $bin->zone->id,
                'name' => $bin->zone->name,
                'code' => $bin->zone->code,
            ] : null,
        ];

        if ($withContents) {
            $levels = InventoryLevel::where('bin_id', $bin->id)
                ->with('product:id,name,sku,barcode')
                ->get();

            $data['contents'] = $levels->map(fn ($lvl) => [
                'inventory_level_id' => $lvl->id,
                'product_id' => $lvl->product_id,
                'product_name' => $lvl->product?->name,
                'sku' => $lvl->product?->sku,
                'barcode' => $lvl->product?->barcode,
                'quantity_on_hand' => $lvl->quantity_on_hand,
                'quantity_available' => $lvl->quantity_on_hand - $lvl->quantity_reserved,
            ])->values();
        }

        return $data;
    }
}
