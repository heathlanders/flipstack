<?php

namespace App\Services\Supplier;

use App\Enums\Supplier\CommissionType;
use App\Models\Catalog\Product;
use App\Models\Inventory\Supplier;
use App\Models\Supplier\CommissionStructure;
use App\Models\Supplier\SupplierTier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CommissionCalculatorService
{
    /**
     * Calculate commission for a single sale
     */
    public function calculateCommission(
        Product $product,
        Supplier $supplier,
        float $salePrice,
        ?string $marketplace = null,
        array $context = []
    ): array {
        // Find applicable commission structure
        $structure = $this->findApplicableStructure($product, $supplier);

        if (! $structure) {
            Log::warning("No commission structure found for supplier {$supplier->id}, product {$product->id}");

            return $this->buildResult(0, 0, null, 'No commission structure found');
        }

        // Get supplier's current tier for adjustments
        $tier = $supplier->currentTier;
        $tierAdjustment = $tier ? $tier->commission_adjustment : 0;

        // Calculate base commission
        $baseCommission = $this->calculateBaseCommission($structure, $salePrice, $product, $context);

        // Apply tier adjustment
        $tierBonus = $salePrice * ($tierAdjustment / 100);

        // Apply volume bonus if applicable
        $volumeBonus = $this->calculateVolumeBonus($structure, $supplier, $salePrice);

        // Calculate final commission
        $totalCommission = $baseCommission + $tierBonus + $volumeBonus;

        // Apply min/max caps
        $totalCommission = $this->applyCaps($structure, $totalCommission);

        // Calculate supplier payout
        $supplierPayout = $salePrice - $totalCommission;

        return $this->buildResult(
            $totalCommission,
            $supplierPayout,
            $structure,
            'Commission calculated successfully',
            [
                'base_commission' => $baseCommission,
                'tier_adjustment' => $tierBonus,
                'volume_bonus' => $volumeBonus,
                'tier_name' => $tier?->tier_name,
                'structure_type' => $structure->type,
                'effective_rate' => $salePrice > 0 ? ($totalCommission / $salePrice) * 100 : 0,
            ]
        );
    }

    /**
     * Calculate commission for multiple products (bulk calculation)
     */
    public function calculateBulkCommission(
        Collection $products,
        Supplier $supplier,
        array $salePrices,
        array $context = []
    ): array {
        $results = [];
        $totalCommission = 0;
        $totalPayout = 0;

        foreach ($products as $index => $product) {
            $salePrice = $salePrices[$index] ?? $product->selling_price;
            $result = $this->calculateCommission($product, $supplier, $salePrice, null, $context);

            $results[] = [
                'product_id' => $product->id,
                'sale_price' => $salePrice,
                'commission' => $result['commission'],
                'payout' => $result['supplier_payout'],
                'details' => $result['details'],
            ];

            $totalCommission += $result['commission'];
            $totalPayout += $result['supplier_payout'];
        }

        return [
            'items' => $results,
            'summary' => [
                'total_items' => count($products),
                'total_sales' => array_sum($salePrices),
                'total_commission' => $totalCommission,
                'total_payout' => $totalPayout,
                'average_commission_rate' => array_sum($salePrices) > 0
                    ? ($totalCommission / array_sum($salePrices)) * 100
                    : 0,
            ],
        ];
    }

    /**
     * Find the applicable commission structure
     */
    protected function findApplicableStructure(Product $product, Supplier $supplier): ?CommissionStructure
    {
        return CommissionStructure::findApplicable(
            $supplier->organization_id,
            $supplier->id,
            $product->category_id
        );
    }

    /**
     * Calculate base commission based on structure type
     */
    protected function calculateBaseCommission(
        CommissionStructure $structure,
        float $salePrice,
        Product $product,
        array $context = []
    ): float {
        return match ($structure->type) {
            CommissionType::PERCENTAGE->value, 'percentage' => $this->calculatePercentageCommission($structure, $salePrice),

            CommissionType::FLAT_FEE->value, 'flat_fee' => $this->calculateFlatFeeCommission($structure),

            CommissionType::TIERED->value, 'tiered' => $this->calculateTieredCommission($structure, $salePrice),

            CommissionType::CATEGORY_BASED->value, 'category_based' => $this->calculateCategoryCommission($structure, $salePrice, $product),

            CommissionType::HYBRID->value, 'hybrid' => $this->calculateHybridCommission($structure, $salePrice, $product),

            CommissionType::VOLUME_BASED->value, 'volume_based' => $this->calculateVolumeBasedCommission($structure, $salePrice, $context),

            CommissionType::CUSTOM->value, 'custom' => $this->calculateCustomCommission($structure, $salePrice, $product, $context),

            default => $salePrice * ($structure->base_percentage / 100),
        };
    }

    /**
     * Calculate percentage-based commission
     */
    protected function calculatePercentageCommission(CommissionStructure $structure, float $salePrice): float
    {
        return $salePrice * ($structure->base_percentage / 100);
    }

    /**
     * Calculate flat fee commission
     */
    protected function calculateFlatFeeCommission(CommissionStructure $structure): float
    {
        return $structure->flat_fee_amount ?? 0;
    }

    /**
     * Calculate tiered commission based on price ranges
     */
    protected function calculateTieredCommission(CommissionStructure $structure, float $salePrice): float
    {
        $tiers = $structure->price_tiers ?? [];

        if (empty($tiers)) {
            return $salePrice * ($structure->base_percentage / 100);
        }

        // Sort tiers by min_price
        usort($tiers, fn ($a, $b) => ($a['min_price'] ?? 0) <=> ($b['min_price'] ?? 0));

        foreach ($tiers as $tier) {
            $minPrice = $tier['min_price'] ?? 0;
            $maxPrice = $tier['max_price'] ?? PHP_FLOAT_MAX;

            if ($salePrice >= $minPrice && $salePrice <= $maxPrice) {
                $rate = $tier['percentage'] ?? $structure->base_percentage;

                return $salePrice * ($rate / 100);
            }
        }

        // Default to base percentage if no tier matches
        return $salePrice * ($structure->base_percentage / 100);
    }

    /**
     * Calculate category-based commission
     */
    protected function calculateCategoryCommission(
        CommissionStructure $structure,
        float $salePrice,
        Product $product
    ): float {
        $overrides = $structure->category_overrides ?? [];
        $categoryId = (string) $product->category_id;

        if (isset($overrides[$categoryId])) {
            $rate = $overrides[$categoryId]['percentage'] ?? $structure->base_percentage;

            return $salePrice * ($rate / 100);
        }

        // Check parent categories
        if ($product->category) {
            $parentId = (string) $product->category->parent_id;
            if ($parentId && isset($overrides[$parentId])) {
                $rate = $overrides[$parentId]['percentage'] ?? $structure->base_percentage;

                return $salePrice * ($rate / 100);
            }
        }

        return $salePrice * ($structure->base_percentage / 100);
    }

    /**
     * Calculate hybrid commission (percentage + flat fee)
     */
    protected function calculateHybridCommission(
        CommissionStructure $structure,
        float $salePrice,
        Product $product
    ): float {
        $percentageCommission = $salePrice * ($structure->base_percentage / 100);
        $flatFee = $structure->flat_fee_amount ?? 0;

        return $percentageCommission + $flatFee;
    }

    /**
     * Calculate volume-based commission
     */
    protected function calculateVolumeBasedCommission(
        CommissionStructure $structure,
        float $salePrice,
        array $context = []
    ): float {
        $volumeBonuses = $structure->volume_bonuses ?? [];
        $currentVolume = $context['supplier_monthly_volume'] ?? 0;

        $applicableRate = $structure->base_percentage;

        foreach ($volumeBonuses as $bonus) {
            $minVolume = $bonus['min_volume'] ?? 0;
            $maxVolume = $bonus['max_volume'] ?? PHP_FLOAT_MAX;

            if ($currentVolume >= $minVolume && $currentVolume <= $maxVolume) {
                $applicableRate = $bonus['percentage'] ?? $applicableRate;
                break;
            }
        }

        return $salePrice * ($applicableRate / 100);
    }

    /**
     * Calculate custom commission using defined rules
     */
    protected function calculateCustomCommission(
        CommissionStructure $structure,
        float $salePrice,
        Product $product,
        array $context = []
    ): float {
        $rules = $structure->custom_rules ?? [];
        $commission = $salePrice * ($structure->base_percentage / 100);

        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $product, $salePrice, $context)) {
                $adjustment = $rule['adjustment'] ?? 0;
                $adjustmentType = $rule['adjustment_type'] ?? 'percentage';

                if ($adjustmentType === 'percentage') {
                    $commission += $salePrice * ($adjustment / 100);
                } else {
                    $commission += $adjustment;
                }
            }
        }

        return max(0, $commission);
    }

    /**
     * Evaluate a custom rule
     */
    protected function evaluateRule(array $rule, Product $product, float $salePrice, array $context): bool
    {
        $field = $rule['field'] ?? null;
        $operator = $rule['operator'] ?? '=';
        $value = $rule['value'] ?? null;

        if (! $field || $value === null) {
            return false;
        }

        $actualValue = match ($field) {
            'price' => $salePrice,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'condition' => $product->condition,
            'marketplace' => $context['marketplace'] ?? null,
            default => $product->{$field} ?? $context[$field] ?? null,
        };

        return match ($operator) {
            '=' => $actualValue == $value,
            '!=' => $actualValue != $value,
            '>' => $actualValue > $value,
            '>=' => $actualValue >= $value,
            '<' => $actualValue < $value,
            '<=' => $actualValue <= $value,
            'in' => in_array($actualValue, (array) $value),
            'not_in' => ! in_array($actualValue, (array) $value),
            default => false,
        };
    }

    /**
     * Calculate volume bonus
     */
    protected function calculateVolumeBonus(
        CommissionStructure $structure,
        Supplier $supplier,
        float $salePrice
    ): float {
        $volumeBonuses = $structure->volume_bonuses ?? [];

        if (empty($volumeBonuses)) {
            return 0;
        }

        // Get supplier's current month sales volume
        $currentVolume = $supplier->total_sales_ytd ?? 0;

        foreach ($volumeBonuses as $bonus) {
            $minVolume = $bonus['min_volume'] ?? 0;
            $maxVolume = $bonus['max_volume'] ?? PHP_FLOAT_MAX;

            if ($currentVolume >= $minVolume && $currentVolume <= $maxVolume) {
                $bonusRate = $bonus['bonus_percentage'] ?? 0;

                return $salePrice * ($bonusRate / 100);
            }
        }

        return 0;
    }

    /**
     * Apply minimum and maximum commission caps
     */
    protected function applyCaps(CommissionStructure $structure, float $commission): float
    {
        if ($structure->min_commission_amount && $commission < $structure->min_commission_amount) {
            return $structure->min_commission_amount;
        }

        if ($structure->max_commission_amount && $commission > $structure->max_commission_amount) {
            return $structure->max_commission_amount;
        }

        return $commission;
    }

    /**
     * Build result array
     */
    protected function buildResult(
        float $commission,
        float $supplierPayout,
        ?CommissionStructure $structure,
        string $message,
        array $details = []
    ): array {
        return [
            'commission' => round($commission, 2),
            'supplier_payout' => round($supplierPayout, 2),
            'structure_id' => $structure?->id,
            'structure_name' => $structure?->name,
            'message' => $message,
            'details' => $details,
        ];
    }

    /**
     * Preview commission for a potential sale
     */
    public function previewCommission(
        int $organizationId,
        ?string $supplierId,
        ?string $categoryId,
        float $salePrice
    ): array {
        $structure = CommissionStructure::findApplicable($organizationId, $supplierId, $categoryId);

        if (! $structure) {
            return [
                'commission' => 0,
                'supplier_payout' => $salePrice,
                'effective_rate' => 0,
                'structure' => null,
                'message' => 'No commission structure found',
            ];
        }

        $commission = $salePrice * ($structure->base_percentage / 100);

        return [
            'commission' => round($commission, 2),
            'supplier_payout' => round($salePrice - $commission, 2),
            'effective_rate' => $structure->base_percentage,
            'structure' => [
                'id' => $structure->id,
                'name' => $structure->name,
                'type' => $structure->type,
            ],
            'message' => 'Preview calculated successfully',
        ];
    }

    /**
     * Get commission breakdown for reporting
     */
    public function getCommissionBreakdown(
        Supplier $supplier,
        \Carbon\Carbon $startDate,
        \Carbon\Carbon $endDate
    ): array {
        // This would typically query actual sales data
        // For now, return a structure that shows what the breakdown looks like

        return [
            'supplier_id' => $supplier->id,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_sales' => 0,
                'total_commission' => 0,
                'total_payout' => 0,
                'average_rate' => 0,
            ],
            'by_structure' => [],
            'by_category' => [],
            'by_tier' => [],
        ];
    }

    /**
     * Calculate tier comparison showing potential earnings at each tier.
     */
    public function calculateTierComparison(Supplier $supplier, float $salePrice): array
    {
        $structure = CommissionStructure::findApplicable(
            $supplier->organization_id,
            $supplier->id,
            null
        );

        $basePercentage = $structure ? $structure->base_percentage : 0;

        $tiers = SupplierTier::where('organization_id', $supplier->organization_id)
            ->where('is_active', true)
            ->orderBy('min_performance_score')
            ->get();

        $currentTier = $supplier->currentTier;

        return $tiers->map(function ($tier) use ($salePrice, $basePercentage, $currentTier) {
            $adjustedRate = $basePercentage - $tier->commission_adjustment;
            $commission = $salePrice * ($adjustedRate / 100);
            $payout = $salePrice - $commission;

            return [
                'tier_id' => $tier->id,
                'tier_name' => $tier->tier_name,
                'tier_code' => $tier->tier_code,
                'commission_adjustment' => $tier->commission_adjustment,
                'effective_rate' => round($adjustedRate, 2),
                'commission' => round(max(0, $commission), 2),
                'supplier_payout' => round(min($salePrice, $payout), 2),
                'is_current' => $currentTier && $currentTier->id === $tier->id,
            ];
        })->toArray();
    }

    /**
     * Get commission summary for a supplier over a given period.
     */
    public function getCommissionSummary(Supplier $supplier, string $period): array
    {
        $now = now();

        $startDate = match ($period) {
            'quarter' => $now->copy()->startOfQuarter(),
            'year' => $now->copy()->startOfYear(),
            default => $now->copy()->startOfMonth(),
        };

        $endDate = $now->copy()->endOfDay();

        $result = \DB::table('settlement_items as si')
            ->join('settlements as s', 's.id', '=', 'si.settlement_id')
            ->where('s.supplier_id', $supplier->id)
            ->whereBetween('si.sale_date', [$startDate, $endDate])
            ->selectRaw('
                COALESCE(SUM(si.sale_price), 0) as total_sales,
                COALESCE(SUM(si.supplier_payout), 0) as total_payout,
                COALESCE(SUM(si.final_commission), 0) as total_commission,
                COUNT(*) as total_items
            ')
            ->first();

        $totalSales = (float) $result->total_sales;
        $totalCommission = (float) $result->total_commission;

        return [
            'total_sales' => $totalSales,
            'total_payout' => (float) $result->total_payout,
            'total_commission' => $totalCommission,
            'total_items' => (int) $result->total_items,
            'average_rate' => $totalSales > 0 ? round(($totalCommission / $totalSales) * 100, 2) : 0,
        ];
    }
}
