<?php

namespace App\Services\Fulfillment;

use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\ShippingRule;
use Illuminate\Support\Collection;

class ShippingRuleEngine
{
    /**
     * Evaluate all active rules against an order, returning the first match.
     */
    public function evaluate(Order $order): ?array
    {
        $rules = $this->getActiveRules($order->organization_id);

        foreach ($rules as $rule) {
            if ($this->matchesConditions($rule, $order)) {
                return [
                    'carrier' => $rule->action_carrier,
                    'service' => $rule->action_service,
                    'package_preset_id' => $rule->action_package_preset_id,
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                ];
            }
        }

        return null;
    }

    /**
     * Check whether all conditions on a rule match the given order.
     */
    public function matchesConditions(ShippingRule $rule, Order $order): bool
    {
        $conditions = $rule->conditions ?? [];

        if (empty($conditions)) {
            return true;
        }

        if (! $this->checkWeightRange($conditions, $order)) {
            return false;
        }

        if (! $this->checkValueRange($conditions, $order)) {
            return false;
        }

        if (! $this->checkDestination($conditions, $order)) {
            return false;
        }

        if (! $this->checkMarketplace($conditions, $order)) {
            return false;
        }

        if (! $this->checkItemCount($conditions, $order)) {
            return false;
        }

        return true;
    }

    /**
     * Check whether the order total weight falls within the rule weight range.
     */
    public function checkWeightRange(array $conditions, Order $order): bool
    {
        $totalWeight = $order->items->sum('weight_oz') ?? 0;

        if (isset($conditions['weight_min']) && $conditions['weight_min'] !== null && $conditions['weight_min'] !== '') {
            if ($totalWeight < (float) $conditions['weight_min']) {
                return false;
            }
        }

        if (isset($conditions['weight_max']) && $conditions['weight_max'] !== null && $conditions['weight_max'] !== '') {
            if ($totalWeight > (float) $conditions['weight_max']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether the order total amount falls within the rule value range.
     */
    public function checkValueRange(array $conditions, Order $order): bool
    {
        $totalValue = (float) ($order->total_amount ?? 0);

        if (isset($conditions['value_min']) && $conditions['value_min'] !== null && $conditions['value_min'] !== '') {
            if ($totalValue < (float) $conditions['value_min']) {
                return false;
            }
        }

        if (isset($conditions['value_max']) && $conditions['value_max'] !== null && $conditions['value_max'] !== '') {
            if ($totalValue > (float) $conditions['value_max']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether the order shipping destination matches the rule state/country lists.
     */
    public function checkDestination(array $conditions, Order $order): bool
    {
        $states = $conditions['destination_states'] ?? [];
        if (! empty($states)) {
            $orderState = strtoupper(trim($order->shipping_state ?? ''));
            $normalizedStates = array_map(function ($s) {
                return strtoupper(trim($s));
            }, $states);

            if (! in_array($orderState, $normalizedStates)) {
                return false;
            }
        }

        $countries = $conditions['destination_countries'] ?? [];
        if (! empty($countries)) {
            $orderCountry = strtoupper(trim($order->shipping_country ?? ''));
            $normalizedCountries = array_map(function ($c) {
                return strtoupper(trim($c));
            }, $countries);

            if (! in_array($orderCountry, $normalizedCountries)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether the order marketplace matches the rule marketplace filter.
     */
    public function checkMarketplace(array $conditions, Order $order): bool
    {
        $marketplace = $conditions['marketplace'] ?? null;

        if ($marketplace === null || $marketplace === '') {
            return true;
        }

        return strtolower($order->marketplace ?? '') === strtolower($marketplace);
    }

    /**
     * Check whether the order item count falls within the rule range.
     */
    public function checkItemCount(array $conditions, Order $order): bool
    {
        $itemCount = $order->items_count ?? $order->items->count();

        if (isset($conditions['item_count_min']) && $conditions['item_count_min'] !== null && $conditions['item_count_min'] !== '') {
            if ($itemCount < (int) $conditions['item_count_min']) {
                return false;
            }
        }

        if (isset($conditions['item_count_max']) && $conditions['item_count_max'] !== null && $conditions['item_count_max'] !== '') {
            if ($itemCount > (int) $conditions['item_count_max']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all active rules for the organization, ordered by priority.
     */
    public function getActiveRules(string $orgId): Collection
    {
        return ShippingRule::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * Run evaluation and return a detailed result with which rule matched and why.
     */
    public function testOrder(Order $order): array
    {
        $order->loadMissing('items');
        $rules = $this->getActiveRules($order->organization_id);
        $results = [];
        $matchedRule = null;

        foreach ($rules as $rule) {
            $conditions = $rule->conditions ?? [];
            $totalWeight = $order->items->sum('weight_oz') ?? 0;
            $totalValue = (float) ($order->total_amount ?? 0);
            $itemCount = $order->items_count ?? $order->items->count();

            $checks = [
                'weight_range' => $this->checkWeightRange($conditions, $order),
                'value_range' => $this->checkValueRange($conditions, $order),
                'destination' => $this->checkDestination($conditions, $order),
                'marketplace' => $this->checkMarketplace($conditions, $order),
                'item_count' => $this->checkItemCount($conditions, $order),
            ];

            $matched = ! in_array(false, $checks, true);

            $ruleResult = [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'priority' => $rule->priority,
                'matched' => $matched,
                'checks' => $checks,
                'action' => [
                    'carrier' => $rule->action_carrier,
                    'service' => $rule->action_service,
                    'package_preset_id' => $rule->action_package_preset_id,
                ],
            ];

            $results[] = $ruleResult;

            if ($matched && $matchedRule === null) {
                $matchedRule = $ruleResult;
            }
        }

        return [
            'order_id' => $order->id,
            'order_summary' => [
                'total_amount' => (float) ($order->total_amount ?? 0),
                'total_weight_oz' => $order->items->sum('weight_oz') ?? 0,
                'item_count' => $order->items_count ?? $order->items->count(),
                'shipping_state' => $order->shipping_state,
                'shipping_country' => $order->shipping_country,
                'marketplace' => $order->marketplace,
            ],
            'matched_rule' => $matchedRule,
            'all_rules' => $results,
        ];
    }
}
