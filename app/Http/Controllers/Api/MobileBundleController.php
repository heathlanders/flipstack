<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Catalog\ProductBundle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileBundleController extends Controller
{
    use ResolvesOrganization;

    /**
     * GET /api/bundles
     * List bundles with live computed availability (read-only).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'active' => 'nullable|boolean',
        ]);

        $organizationId = $this->organizationId($request);

        $query = ProductBundle::where('organization_id', $organizationId)
            ->with(['product:id,name,sku,selling_price,available_quantity', 'items.componentProduct:id,name,sku,available_quantity'])
            ->withCount('items');

        if ($request->boolean('active', false)) {
            $query->where('is_active', true);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'ilike', "%{$search}%")->orWhere('sku', 'ilike', "%{$search}%"));
            });
        }

        $bundles = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $bundles->map(fn ($b) => $this->formatBundle($b, withComponents: true)),
            'stats' => [
                'total' => $bundles->count(),
                'active' => $bundles->where('is_active', true)->count(),
            ],
        ]);
    }

    /**
     * GET /api/bundles/{bundle}
     * Bundle detail with components + per-component availability.
     */
    public function show(Request $request, ProductBundle $bundle): JsonResponse
    {
        if ($bundle->organization_id !== $this->organizationId($request)) {
            return response()->json(['success' => false, 'message' => 'Bundle not found'], 404);
        }

        $bundle->load(['product:id,name,sku,selling_price,available_quantity', 'items.componentProduct:id,name,sku,available_quantity']);
        $bundle->loadCount('items');

        return response()->json([
            'success' => true,
            'data' => $this->formatBundle($bundle, withComponents: true),
        ]);
    }

    private function organizationId(Request $request): int
    {
        return $this->resolveOrganizationId($request->user())
            ?? abort(response()->json([
                'success' => false,
                'message' => 'No organization associated with this account.',
            ], 422));
    }

    private function formatBundle(ProductBundle $bundle, bool $withComponents = false): array
    {
        $data = [
            'id' => $bundle->id,
            'name' => $bundle->name,
            'bundle_type' => $bundle->bundle_type,
            'pick_n_quantity' => $bundle->pick_n_quantity,
            'is_active' => $bundle->is_active,
            'items_count' => $bundle->items_count,
            'available_quantity' => $bundle->calculateAvailableQuantity(),
            'product' => $bundle->product ? [
                'id' => $bundle->product->id,
                'name' => $bundle->product->name,
                'sku' => $bundle->product->sku,
                'selling_price' => (float) $bundle->product->selling_price,
            ] : null,
        ];

        if ($withComponents) {
            $data['components'] = $bundle->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->component_product_id,
                'name' => $item->componentProduct?->name,
                'sku' => $item->componentProduct?->sku,
                'quantity' => $item->quantity,
                'is_required' => $item->is_required,
                'component_available' => $item->componentProduct?->available_quantity ?? 0,
            ])->values();
        }

        return $data;
    }
}
