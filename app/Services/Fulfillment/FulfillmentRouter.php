<?php

namespace App\Services\Fulfillment;

use App\Models\Fulfillment\FulfillmentRoutingRule;
use App\Models\Fulfillment\Order;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FulfillmentRouter
{
    /**
     * Evaluate routing rules in priority order and return the first matching location.
     */
    public function selectLocation(Order $order): ?InventoryLocation
    {
        $rules = $this->getActiveRules($order->organization_id);

        foreach ($rules as $rule) {
            $location = $this->evaluateRule($rule, $order);

            if ($location) {
                return $location;
            }
        }

        return null;
    }

    /**
     * Dispatch a rule to its corresponding strategy method.
     */
    public function evaluateRule(FulfillmentRoutingRule $rule, Order $order): ?InventoryLocation
    {
        return match ($rule->rule_type) {
            FulfillmentRoutingRule::TYPE_PROXIMITY => $this->proximityStrategy($order, $rule->parameters ?? []),
            FulfillmentRoutingRule::TYPE_HIGHEST_STOCK => $this->highestStockStrategy($order),
            FulfillmentRoutingRule::TYPE_ROUND_ROBIN => $this->roundRobinStrategy($order->organization_id),
            FulfillmentRoutingRule::TYPE_SPECIFIC_LOCATION => $this->specificLocationStrategy($rule->parameters ?? []),
            FulfillmentRoutingRule::TYPE_LOWEST_COST => $this->proximityStrategy($order, $rule->parameters ?? []),
            default => null,
        };
    }

    /**
     * Compare order shipping zip prefix (first 3 digits) to location zip prefixes.
     * Returns the location with the closest zip prefix match.
     */
    public function proximityStrategy(Order $order, array $params): ?InventoryLocation
    {
        $orderZip = $order->shipping_postal_code;

        if (! $orderZip) {
            return null;
        }

        $orderPrefix = substr(preg_replace('/[^0-9]/', '', $orderZip), 0, 3);

        if (strlen($orderPrefix) < 3) {
            return null;
        }

        $locations = InventoryLocation::where('organization_id', $order->organization_id)
            ->where('is_active', true)
            ->whereNotNull('postal_code')
            ->get();

        if ($locations->isEmpty()) {
            return null;
        }

        $bestLocation = null;
        $bestDiff = PHP_INT_MAX;
        $orderPrefixInt = (int) $orderPrefix;

        foreach ($locations as $location) {
            $locPrefix = substr(preg_replace('/[^0-9]/', '', $location->postal_code), 0, 3);

            if (strlen($locPrefix) < 3) {
                continue;
            }

            $diff = abs($orderPrefixInt - (int) $locPrefix);

            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $bestLocation = $location;
            }
        }

        return $bestLocation;
    }

    /**
     * Find the location with the most stock for the ordered items.
     */
    public function highestStockStrategy(Order $order): ?InventoryLocation
    {
        $order->loadMissing('items');

        $productIds = $order->items->pluck('product_id')->filter()->unique()->values();

        if ($productIds->isEmpty()) {
            return null;
        }

        $result = InventoryLevel::where('organization_id', $order->organization_id)
            ->whereIn('product_id', $productIds)
            ->where('quantity_on_hand', '>', 0)
            ->select('location_id', DB::raw('SUM(quantity_on_hand) as total_stock'))
            ->groupBy('location_id')
            ->orderByDesc('total_stock')
            ->first();

        if (! $result) {
            return null;
        }

        return InventoryLocation::where('id', $result->location_id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Pick the location with the fewest recent orders (round-robin distribution).
     */
    public function roundRobinStrategy(string $orgId): ?InventoryLocation
    {
        $locations = InventoryLocation::where('organization_id', $orgId)
            ->where('is_active', true)
            ->get();

        if ($locations->isEmpty()) {
            return null;
        }

        $locationIds = $locations->pluck('id');

        $orderCounts = Order::where('organization_id', $orgId)
            ->whereIn('location_id', $locationIds)
            ->where('created_at', '>=', now()->subDays(7))
            ->select('location_id', DB::raw('COUNT(*) as order_count'))
            ->groupBy('location_id')
            ->pluck('order_count', 'location_id');

        $bestLocation = null;
        $lowestCount = PHP_INT_MAX;

        foreach ($locations as $location) {
            $count = $orderCounts->get($location->id, 0);

            if ($count < $lowestCount) {
                $lowestCount = $count;
                $bestLocation = $location;
            }
        }

        return $bestLocation;
    }

    /**
     * Return the specifically configured location.
     */
    public function specificLocationStrategy(array $params): ?InventoryLocation
    {
        $locationId = $params['location_id'] ?? null;

        if (! $locationId) {
            return null;
        }

        return InventoryLocation::where('id', $locationId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Return active routing rules for an organization, ordered by priority.
     */
    public function getActiveRules(string $orgId): Collection
    {
        return FulfillmentRoutingRule::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->orderBy('priority', 'asc')
            ->get();
    }
}
