<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Controller for managing inventory stock alerts.
 *
 * Provides API endpoints for:
 * - Listing current stock alerts (low stock, out of stock, overstocked)
 * - Getting alert summaries/counts
 * - Dismissing/restoring alerts
 * - Configuring alert thresholds
 */
class InventoryAlertController extends Controller
{
    use ResolvesOrganization;

    /**
     * Get all current inventory alerts.
     *
     * Queries inventory_levels for items that:
     * - Have quantity_available <= 0 (out of stock)
     * - Have quantity_available <= reorder_point (low stock)
     * - Have quantity_on_hand > max_stock_level (overstocked)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->getOrganizationId($user);

        $query = InventoryLevel::query()
            ->where('organization_id', $organizationId)
            ->with(['product:id,name,sku,organization_id', 'location:id,name,type']);

        // Filter by alert type
        $alertType = $request->input('type');
        if ($alertType) {
            $query = $this->applyAlertTypeFilter($query, $alertType);
        } else {
            // Get all alerts (any threshold crossed)
            $query->where(function ($q) {
                $q->where('quantity_on_hand', '<=', 0) // Out of stock
                    ->orWhereRaw('quantity_available <= reorder_point AND reorder_point > 0') // Low stock
                    ->orWhereRaw('max_stock_level IS NOT NULL AND quantity_on_hand > max_stock_level'); // Overstocked
            });
        }

        // Filter by location
        if ($request->has('location_id')) {
            $query->where('location_id', $request->input('location_id'));
        }

        // Get dismissed alerts from user preferences
        $dismissedAlerts = $this->getDismissedAlerts($user);

        // Exclude dismissed alerts if requested
        if ($request->boolean('exclude_dismissed', true)) {
            $query->whereNotIn('id', $dismissedAlerts);
        }

        // Sort by severity (out of stock first, then low stock, then overstocked)
        $query->orderByRaw('
            CASE 
                WHEN quantity_on_hand <= 0 THEN 1
                WHEN quantity_available <= reorder_point AND reorder_point > 0 THEN 2
                WHEN max_stock_level IS NOT NULL AND quantity_on_hand > max_stock_level THEN 3
                ELSE 4
            END
        ');

        $perPage = min($request->input('per_page', 20), 100);
        $alerts = $query->paginate($perPage);

        // Transform results to include alert type
        $alerts->getCollection()->transform(function ($level) use ($dismissedAlerts) {
            return $this->transformToAlert($level, $dismissedAlerts);
        });

        return response()->json($alerts);
    }

    /**
     * Get alert summary counts by type.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->getOrganizationId($user);

        $cacheKey = "inventory_alerts_summary_{$organizationId}";
        $cacheTtl = 300; // 5 minutes

        $summary = Cache::remember($cacheKey, $cacheTtl, function () use ($organizationId) {
            $outOfStock = InventoryLevel::where('organization_id', $organizationId)
                ->where('quantity_on_hand', '<=', 0)
                ->count();

            $lowStock = InventoryLevel::where('organization_id', $organizationId)
                ->whereRaw('quantity_available <= reorder_point')
                ->where('reorder_point', '>', 0)
                ->where('quantity_on_hand', '>', 0) // Exclude out of stock
                ->count();

            $overstocked = InventoryLevel::where('organization_id', $organizationId)
                ->whereNotNull('max_stock_level')
                ->whereRaw('quantity_on_hand > max_stock_level')
                ->count();

            return [
                'out_of_stock' => $outOfStock,
                'low_stock' => $lowStock,
                'overstocked' => $overstocked,
                'total' => $outOfStock + $lowStock + $overstocked,
            ];
        });

        // Get dismissed counts
        $dismissedAlerts = $this->getDismissedAlerts($user);
        $summary['dismissed'] = count($dismissedAlerts);

        return response()->json([
            'data' => $summary,
            'cached_at' => now()->toISOString(),
        ]);
    }

    /**
     * Dismiss a single alert.
     * Stores the inventory level ID in user preferences.
     */
    public function dismiss(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->getOrganizationId($user);

        // Verify the inventory level exists and belongs to the organization
        $level = InventoryLevel::where('organization_id', $organizationId)
            ->find($id);

        if (! $level) {
            return response()->json([
                'message' => 'Alert not found',
            ], 404);
        }

        // Add to dismissed alerts
        $dismissedAlerts = $this->getDismissedAlerts($user);

        if (! in_array($id, $dismissedAlerts)) {
            $dismissedAlerts[] = $id;
            $this->saveDismissedAlerts($user, $dismissedAlerts);
        }

        // Clear summary cache
        $this->clearAlertCache($organizationId);

        return response()->json([
            'message' => 'Alert dismissed',
            'id' => $id,
        ]);
    }

    /**
     * Dismiss all alerts of a specific type.
     */
    public function dismissAll(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:low_stock,out_of_stock,overstocked,all',
        ]);

        $user = $request->user();
        $organizationId = $this->getOrganizationId($user);
        $type = $request->input('type');

        $query = InventoryLevel::where('organization_id', $organizationId);

        if ($type !== 'all') {
            $query = $this->applyAlertTypeFilter($query, $type);
        } else {
            $query->where(function ($q) {
                $q->where('quantity_on_hand', '<=', 0)
                    ->orWhereRaw('quantity_available <= reorder_point AND reorder_point > 0')
                    ->orWhereRaw('max_stock_level IS NOT NULL AND quantity_on_hand > max_stock_level');
            });
        }

        $alertIds = $query->pluck('id')->toArray();

        // Merge with existing dismissed alerts
        $dismissedAlerts = $this->getDismissedAlerts($user);
        $dismissedAlerts = array_unique(array_merge($dismissedAlerts, $alertIds));
        $this->saveDismissedAlerts($user, $dismissedAlerts);

        // Clear summary cache
        $this->clearAlertCache($organizationId);

        return response()->json([
            'message' => 'Alerts dismissed',
            'count' => count($alertIds),
        ]);
    }

    /**
     * Restore a dismissed alert.
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->getOrganizationId($user);

        // Verify the inventory level exists
        $level = InventoryLevel::where('organization_id', $organizationId)
            ->find($id);

        if (! $level) {
            return response()->json([
                'message' => 'Alert not found',
            ], 404);
        }

        // Remove from dismissed alerts
        $dismissedAlerts = $this->getDismissedAlerts($user);
        $dismissedAlerts = array_values(array_diff($dismissedAlerts, [$id]));
        $this->saveDismissedAlerts($user, $dismissedAlerts);

        // Clear summary cache
        $this->clearAlertCache($organizationId);

        return response()->json([
            'message' => 'Alert restored',
            'id' => $id,
        ]);
    }

    /**
     * Clear all dismissed alerts.
     */
    public function clearDismissed(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->getOrganizationId($user);

        $this->saveDismissedAlerts($user, []);
        $this->clearAlertCache($organizationId);

        return response()->json([
            'message' => 'Dismissed alerts cleared',
        ]);
    }

    /**
     * Get alert configuration (default thresholds).
     */
    public function config(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->getOrganizationId($user);

        $cacheKey = "inventory_alert_config_{$organizationId}";
        $cacheTtl = 1800; // 30 minutes

        $config = Cache::remember($cacheKey, $cacheTtl, function () use ($organizationId) {
            // Get average thresholds from existing inventory levels
            $stats = InventoryLevel::where('organization_id', $organizationId)
                ->selectRaw('
                    AVG(reorder_point) as avg_reorder_point,
                    AVG(reorder_quantity) as avg_reorder_quantity,
                    AVG(max_stock_level) as avg_max_stock_level
                ')
                ->first();

            return [
                'default_reorder_point' => (int) ($stats->avg_reorder_point ?? 5),
                'default_reorder_quantity' => (int) ($stats->avg_reorder_quantity ?? 10),
                'default_max_stock_level' => (int) ($stats->avg_max_stock_level ?? 100),
                'notifications' => [
                    'email_on_out_of_stock' => true,
                    'email_on_low_stock' => false,
                    'email_on_overstocked' => false,
                ],
            ];
        });

        return response()->json([
            'data' => $config,
        ]);
    }

    /**
     * Update alert configuration for a specific inventory level.
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $request->validate([
            'inventory_level_id' => 'required|uuid',
            'reorder_point' => 'sometimes|integer|min:0',
            'reorder_quantity' => 'sometimes|integer|min:0',
            'max_stock_level' => 'sometimes|nullable|integer|min:0',
        ]);

        $user = $request->user();
        $organizationId = $this->getOrganizationId($user);

        $level = InventoryLevel::where('organization_id', $organizationId)
            ->find($request->input('inventory_level_id'));

        if (! $level) {
            return response()->json([
                'message' => 'Inventory level not found',
            ], 404);
        }

        // Update thresholds
        if ($request->has('reorder_point')) {
            $level->reorder_point = $request->input('reorder_point');
        }
        if ($request->has('reorder_quantity')) {
            $level->reorder_quantity = $request->input('reorder_quantity');
        }
        if ($request->has('max_stock_level')) {
            $level->max_stock_level = $request->input('max_stock_level');
        }

        $level->version += 1;
        $level->save();

        // Clear caches
        $this->clearAlertCache($organizationId);

        return response()->json([
            'message' => 'Configuration updated',
            'data' => $level->only([
                'id',
                'reorder_point',
                'reorder_quantity',
                'max_stock_level',
            ]),
        ]);
    }

    /**
     * Get products with alerts (grouped by product).
     */
    public function products(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $this->getOrganizationId($user);

        $products = Product::where('organization_id', $organizationId)
            ->whereHas('inventoryLevels', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('quantity_on_hand', '<=', 0)
                        ->orWhereRaw('quantity_available <= reorder_point AND reorder_point > 0')
                        ->orWhereRaw('max_stock_level IS NOT NULL AND quantity_on_hand > max_stock_level');
                });
            })
            ->with(['inventoryLevels' => function ($q) {
                $q->where(function ($sub) {
                    $sub->where('quantity_on_hand', '<=', 0)
                        ->orWhereRaw('quantity_available <= reorder_point AND reorder_point > 0')
                        ->orWhereRaw('max_stock_level IS NOT NULL AND quantity_on_hand > max_stock_level');
                })
                    ->with('location:id,name,type');
            }])
            ->select('id', 'name', 'sku', 'organization_id')
            ->paginate($request->input('per_page', 20));

        // Transform to include alert info
        $products->getCollection()->transform(function ($product) {
            $alerts = $product->inventoryLevels->map(function ($level) {
                return [
                    'location_id' => $level->location_id,
                    'location_name' => $level->location?->name,
                    'alert_type' => $this->determineAlertType($level),
                    'quantity_on_hand' => $level->quantity_on_hand,
                    'quantity_available' => $level->quantity_available,
                    'reorder_point' => $level->reorder_point,
                    'max_stock_level' => $level->max_stock_level,
                ];
            });

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'alert_count' => $alerts->count(),
                'most_severe' => $this->getMostSevereAlert($alerts),
                'alerts' => $alerts,
            ];
        });

        return response()->json($products);
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    /**
     * Get the organization ID for the user.
     */
    protected function getOrganizationId($user): int
    {
        return $this->resolveOrganizationId($user)
            ?? abort(response()->json([
                'success' => false,
                'message' => 'No organization associated with this account.',
            ], 422));
    }

    /**
     * Apply alert type filter to query.
     */
    protected function applyAlertTypeFilter($query, string $type)
    {
        return match ($type) {
            'out_of_stock' => $query->where('quantity_on_hand', '<=', 0),
            'low_stock' => $query->whereRaw('quantity_available <= reorder_point')
                ->where('reorder_point', '>', 0)
                ->where('quantity_on_hand', '>', 0),
            'overstocked' => $query->whereNotNull('max_stock_level')
                ->whereRaw('quantity_on_hand > max_stock_level'),
            default => $query,
        };
    }

    /**
     * Determine the alert type for an inventory level.
     */
    protected function determineAlertType(InventoryLevel $level): string
    {
        if ($level->quantity_on_hand <= 0) {
            return 'out_of_stock';
        }

        if ($level->reorder_point > 0 && $level->quantity_available <= $level->reorder_point) {
            return 'low_stock';
        }

        if ($level->max_stock_level && $level->quantity_on_hand > $level->max_stock_level) {
            return 'overstocked';
        }

        return 'in_stock';
    }

    /**
     * Transform an inventory level to an alert response.
     */
    protected function transformToAlert(InventoryLevel $level, array $dismissedAlerts): array
    {
        $alertType = $this->determineAlertType($level);

        return [
            'id' => $level->id,
            'alert_type' => $alertType,
            'severity' => $this->getSeverity($alertType),
            'is_dismissed' => in_array($level->id, $dismissedAlerts),

            // Product info
            'product_id' => $level->product_id,
            'product_name' => $level->product?->name,
            'sku' => $level->product?->sku,
            'variation_id' => $level->product_variation_id,

            // Location info
            'location_id' => $level->location_id,
            'location_name' => $level->location?->name,
            'location_type' => $level->location?->type,

            // Quantities
            'quantity_on_hand' => $level->quantity_on_hand,
            'quantity_reserved' => $level->quantity_reserved,
            'quantity_available' => $level->quantity_available,

            // Thresholds
            'reorder_point' => $level->reorder_point,
            'reorder_quantity' => $level->reorder_quantity,
            'max_stock_level' => $level->max_stock_level,

            // Timestamps
            'last_movement_at' => $level->last_movement_at?->toISOString(),
            'created_at' => $level->created_at?->toISOString(),
        ];
    }

    /**
     * Get severity for alert type.
     */
    protected function getSeverity(string $alertType): string
    {
        return match ($alertType) {
            'out_of_stock' => 'critical',
            'low_stock' => 'warning',
            'overstocked' => 'info',
            default => 'none',
        };
    }

    /**
     * Get the most severe alert from a collection.
     */
    protected function getMostSevereAlert($alerts): ?string
    {
        $types = $alerts->pluck('alert_type');

        if ($types->contains('out_of_stock')) {
            return 'out_of_stock';
        }
        if ($types->contains('low_stock')) {
            return 'low_stock';
        }
        if ($types->contains('overstocked')) {
            return 'overstocked';
        }

        return null;
    }

    /**
     * Get dismissed alerts from user preferences.
     */
    protected function getDismissedAlerts($user): array
    {
        $preferences = $user->preferences ?? [];
        $dismissed = $preferences['dismissed_inventory_alerts'] ?? [];

        // Limit to prevent unbounded growth
        return array_slice($dismissed, -500);
    }

    /**
     * Save dismissed alerts to user preferences.
     */
    protected function saveDismissedAlerts($user, array $alerts): void
    {
        $preferences = $user->preferences ?? [];
        $preferences['dismissed_inventory_alerts'] = array_slice($alerts, -500);

        $user->preferences = $preferences;
        $user->save();
    }

    /**
     * Clear alert-related caches.
     */
    protected function clearAlertCache(int $organizationId): void
    {
        Cache::forget("inventory_alerts_summary_{$organizationId}");
        Cache::forget("inventory_alert_config_{$organizationId}");
    }
}
