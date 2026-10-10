<?php

namespace App\Services;

use App\Models\Catalog\Product;
use App\Models\MarketplaceSyncRule;

class MarketplaceSyncRuleEngine
{
    /**
     * Determine whether a product should sync to the given marketplace platform.
     *
     * Decision order:
     *   1. Per-product override ('include' | 'exclude' | 'auto')
     *   2. Active rules (priority ASC, first match wins)
     *   3. Default: true (sync to all)
     */
    public function shouldSync(Product $product, string $platform): bool
    {
        // Layer 1: per-product override
        $overrides = $product->marketplace_overrides ?? [];
        $override = $overrides[$platform] ?? null;

        if ($override === 'include') {
            return true;
        }

        if ($override === 'exclude') {
            return false;
        }

        // Layer 2: rule evaluation
        $rules = MarketplaceSyncRule::forOrganization($product->organization_id)
            ->active()
            ->forPlatform($platform)
            ->ordered()
            ->get();

        foreach ($rules as $rule) {
            if ($this->matchesRule($product, $rule)) {
                return $rule->action === 'include';
            }
        }

        // Layer 3: default — sync to all
        return true;
    }

    /**
     * Evaluate all conditions for a rule (all must match — AND logic).
     */
    private function matchesRule(Product $product, MarketplaceSyncRule $rule): bool
    {
        $conditions = $rule->conditions ?? [];

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! $this->evaluateCondition($product, $condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition against the product.
     */
    private function evaluateCondition(Product $product, array $condition): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;

        if ($field === null || $operator === null) {
            return false;
        }

        $productValue = $this->resolveField($product, $field);

        return match ($operator) {
            'in' => in_array($productValue, (array) $value),
            'not_in' => ! in_array($productValue, (array) $value),
            'between' => $this->evalBetween($productValue, $value),
            'greater_than' => is_numeric($productValue) && $productValue > $value,
            'less_than' => is_numeric($productValue) && $productValue < $value,
            default => false,
        };
    }

    /**
     * Resolve a supported field name to its value on the product.
     *
     * Supported fields:
     *   - category_id   (integer FK)
     *   - condition     (enum string, e.g. 'NEW', 'USED_EXCELLENT')
     *   - selling_price (decimal)
     *   - brand_id      (integer FK — 'brand' in the rule maps to brand_id)
     *   - created_by    (integer user id)
     */
    private function resolveField(Product $product, string $field): mixed
    {
        return match ($field) {
            'category_id' => $product->category_id,
            'condition' => $product->condition,
            'selling_price' => (float) $product->selling_price,
            'brand_id', 'brand' => $product->brand_id,
            'created_by' => $product->created_by,
            default => null,
        };
    }

    private function evalBetween(mixed $value, mixed $range): bool
    {
        if (! is_array($range) || count($range) < 2) {
            return false;
        }

        return is_numeric($value) && $value >= $range[0] && $value <= $range[1];
    }

    /**
     * Evaluate and return detailed sync status for all configured platforms.
     * Used by the UI to show "effective status" per platform.
     *
     * Returns: ['ebay' => [...], 'shopify' => [...]]
     */
    public function getDetailedSyncStatus(Product $product, array $platforms): array
    {
        $status = [];

        foreach ($platforms as $platform) {
            $overrides = $product->marketplace_overrides ?? [];
            $override = $overrides[$platform] ?? null;

            if ($override === 'include') {
                $status[$platform] = [
                    'syncs' => true,
                    'reason' => 'manual_include',
                    'label' => 'Manually included',
                    'rule' => null,
                ];

                continue;
            }

            if ($override === 'exclude') {
                $status[$platform] = [
                    'syncs' => false,
                    'reason' => 'manual_exclude',
                    'label' => 'Manually excluded',
                    'rule' => null,
                ];

                continue;
            }

            // Evaluate rules
            $rules = MarketplaceSyncRule::forOrganization($product->organization_id)
                ->active()
                ->forPlatform($platform)
                ->ordered()
                ->get();

            $matched = false;
            foreach ($rules as $rule) {
                if ($this->matchesRule($product, $rule)) {
                    $matched = true;
                    $syncs = $rule->action === 'include';
                    $status[$platform] = [
                        'syncs' => $syncs,
                        'reason' => $syncs ? 'rule_include' : 'rule_exclude',
                        'label' => ($syncs ? 'Included' : 'Excluded').' by rule: '.$rule->name,
                        'rule' => ['id' => $rule->id, 'name' => $rule->name],
                    ];
                    break;
                }
            }

            if (! $matched) {
                $status[$platform] = [
                    'syncs' => true,
                    'reason' => 'default',
                    'label' => 'Auto (syncs to all)',
                    'rule' => null,
                ];
            }
        }

        return $status;
    }
}
