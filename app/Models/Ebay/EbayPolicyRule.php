<?php

namespace App\Models\Ebay;

use App\Models\Integration\IntegrationCredential;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbayPolicyRule extends Model
{
    protected $table = 'ebay_policy_rules';

    protected $fillable = [
        'organization_id',
        'integration_credential_id',
        'name',
        'description',
        'priority',
        'conditions',
        'condition_logic',
        'fulfillment_policy_id',
        'payment_policy_id',
        'return_policy_id',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    const LOGIC_AND = 'AND';

    const LOGIC_OR = 'OR';

    const OPERATORS = [
        '=' => 'equals',
        '!=' => 'not equals',
        '>' => 'greater than',
        '>=' => 'greater than or equals',
        '<' => 'less than',
        '<=' => 'less than or equals',
        'contains' => 'contains',
        'in' => 'in list',
    ];

    const AVAILABLE_FIELDS = [
        'price' => ['label' => 'Selling Price', 'type' => 'number'],
        'item_value' => ['label' => 'Item Value', 'type' => 'number'],
        'weight_lbs' => ['label' => 'Weight (lbs)', 'type' => 'number'],
        'shipping_class' => ['label' => 'Shipping Class', 'type' => 'select', 'options' => [
            'envelope', 'small_box', 'medium_box', 'large_box', 'oversized', 'freight',
        ]],
        'condition' => ['label' => 'Item Condition', 'type' => 'select', 'options' => [
            'new', 'like_new', 'good', 'fair', 'poor', 'refurbished',
        ]],
        'category' => ['label' => 'Product Category', 'type' => 'text'],
        'estimated_shipping_cost' => ['label' => 'Estimated Shipping Cost', 'type' => 'number'],
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function integrationCredential(): BelongsTo
    {
        return $this->belongsTo(IntegrationCredential::class);
    }

    public function fulfillmentPolicy(): BelongsTo
    {
        return $this->belongsTo(EbayPolicy::class, 'fulfillment_policy_id');
    }

    public function paymentPolicy(): BelongsTo
    {
        return $this->belongsTo(EbayPolicy::class, 'payment_policy_id');
    }

    public function returnPolicy(): BelongsTo
    {
        return $this->belongsTo(EbayPolicy::class, 'return_policy_id');
    }

    public function scopeForCredential(Builder $query, int $credentialId): Builder
    {
        return $query->where('integration_credential_id', $credentialId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByDesc('priority');
    }

    public function evaluateConditions(array $productData): bool
    {
        $conditions = $this->conditions ?? [];

        if (empty($conditions)) {
            return true;
        }

        $results = [];

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;

            if (! $field || $value === null) {
                continue;
            }

            $productValue = $productData[$field] ?? null;
            $results[] = $this->evaluateCondition($productValue, $operator, $value);
        }

        if (empty($results)) {
            return true;
        }

        if ($this->condition_logic === self::LOGIC_OR) {
            return in_array(true, $results, true);
        }

        return ! in_array(false, $results, true);
    }

    protected function evaluateCondition($productValue, string $operator, $conditionValue): bool
    {
        if ($productValue === null) {
            return false;
        }

        // Convert to comparable types
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

    public function getPolicies(): array
    {
        return [
            'fulfillment' => $this->fulfillment_policy_id,
            'payment' => $this->payment_policy_id,
            'return' => $this->return_policy_id,
        ];
    }

    public static function getOperators(): array
    {
        return self::OPERATORS;
    }

    public static function getAvailableFields(): array
    {
        return self::AVAILABLE_FIELDS;
    }
}
