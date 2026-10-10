<?php

namespace App\Services\Ebay;

use App\Models\Ebay\EbayPolicy;
use App\Models\Ebay\EbayPolicyRule;
use App\Models\Integration\IntegrationCredential;
use Illuminate\Support\Facades\Log;

class EbayPolicyRulesEngine
{
    protected IntegrationCredential $credential;

    public function __construct(IntegrationCredential $credential)
    {
        $this->credential = $credential;
    }

    public function evaluate(array $productData): ?EbayPolicyRule
    {
        $rules = EbayPolicyRule::where('integration_credential_id', $this->credential->id)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->evaluateConditions($productData)) {
                Log::debug('Policy rule matched', [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'product_data' => $productData,
                ]);

                return $rule;
            }
        }

        return null;
    }

    public function getApplicablePolicies(array $productData): array
    {
        $matchedRule = $this->evaluate($productData);

        if ($matchedRule) {
            return [
                'fulfillment' => $matchedRule->fulfillmentPolicy?->ebay_policy_id ?? $this->getDefaultPolicyId('fulfillment'),
                'payment' => $matchedRule->paymentPolicy?->ebay_policy_id ?? $this->getDefaultPolicyId('payment'),
                'return' => $matchedRule->returnPolicy?->ebay_policy_id ?? $this->getDefaultPolicyId('return'),
                'matched_rule' => [
                    'id' => $matchedRule->id,
                    'name' => $matchedRule->name,
                ],
            ];
        }

        return [
            'fulfillment' => $this->getDefaultPolicyId('fulfillment'),
            'payment' => $this->getDefaultPolicyId('payment'),
            'return' => $this->getDefaultPolicyId('return'),
            'matched_rule' => null,
        ];
    }

    protected function getDefaultPolicyId(string $type): ?string
    {
        // First try to get from ebay_policies table (new system)
        $defaultPolicy = EbayPolicy::where('integration_credential_id', $this->credential->id)
            ->where('policy_type', $type)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($defaultPolicy) {
            return $defaultPolicy->ebay_policy_id;
        }

        // Fall back to credential's direct policy IDs (legacy system)
        return match ($type) {
            'fulfillment' => $this->credential->fulfillment_policy_id,
            'payment' => $this->credential->payment_policy_id,
            'return' => $this->credential->return_policy_id,
            default => null,
        };
    }

    public function testRule(EbayPolicyRule $rule, array $productData): array
    {
        $matches = $rule->evaluateConditions($productData);

        $conditionResults = [];
        foreach ($rule->conditions ?? [] as $index => $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;
            $productValue = $productData[$field] ?? null;

            $conditionResults[] = [
                'condition' => $condition,
                'product_value' => $productValue,
                'matches' => $this->evaluateSingleCondition($productValue, $operator, $value),
            ];
        }

        return [
            'rule_matches' => $matches,
            'condition_logic' => $rule->condition_logic,
            'condition_results' => $conditionResults,
            'policies' => $matches ? [
                'fulfillment' => $rule->fulfillmentPolicy?->name,
                'payment' => $rule->paymentPolicy?->name,
                'return' => $rule->returnPolicy?->name,
            ] : null,
        ];
    }

    protected function evaluateSingleCondition($productValue, string $operator, $conditionValue): bool
    {
        if ($productValue === null) {
            return false;
        }

        if (is_numeric($conditionValue) && is_numeric($productValue)) {
            $productValue = (float) $productValue;
            $conditionValue = (float) $conditionValue;
        }

        return match ($operator) {
            '=' => $productValue == $conditionValue,
            '!=' => $productValue != $conditionValue,
            '>' => $productValue > $conditionValue,
            '>=' => $productValue >= $conditionValue,
            '<' => $productValue < $conditionValue,
            '<=' => $productValue <= $conditionValue,
            'contains' => is_string($productValue) && str_contains(strtolower($productValue), strtolower((string) $conditionValue)),
            'in' => is_array($conditionValue) && in_array($productValue, $conditionValue),
            default => false,
        };
    }

    public function buildProductDataFromQueueItem(object $queueItem): array
    {
        $enrichedData = $queueItem->enriched_data ?? [];
        $price = $queueItem->selling_price ?? $enrichedData['suggested_price'] ?? null;

        return [
            'price' => $price,
            'item_value' => $price,
            'weight_lbs' => $queueItem->weight_lbs ?? $enrichedData['weight_lbs'] ?? null,
            'shipping_class' => $queueItem->suggested_shipping_class ?? $enrichedData['shipping_class'] ?? null,
            'condition' => $queueItem->condition ?? $enrichedData['condition'] ?? null,
            'category' => $queueItem->product_category ?? $enrichedData['category'] ?? null,
            'estimated_shipping_cost' => $queueItem->shipping_cost ?? $enrichedData['estimated_shipping_cost'] ?? null,
        ];
    }

    public static function getAvailableFields(): array
    {
        return EbayPolicyRule::getAvailableFields();
    }

    public static function getOperators(): array
    {
        return EbayPolicyRule::getOperators();
    }
}
