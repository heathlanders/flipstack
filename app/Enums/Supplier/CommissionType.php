<?php

namespace App\Enums\Supplier;

enum CommissionType: string
{
    case PERCENTAGE = 'percentage';
    case FLAT_FEE = 'flat_fee';
    case TIERED = 'tiered';
    case CATEGORY_BASED = 'category_based';
    case HYBRID = 'hybrid';
    case VOLUME_BASED = 'volume_based';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::PERCENTAGE => 'Percentage of Sale',
            self::FLAT_FEE => 'Flat Fee per Item',
            self::TIERED => 'Tiered by Performance',
            self::CATEGORY_BASED => 'Category-Based',
            self::HYBRID => 'Hybrid (Percentage + Fee)',
            self::VOLUME_BASED => 'Volume-Based Tiers',
            self::CUSTOM => 'Custom Rules',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PERCENTAGE => 'A fixed percentage of the sale price',
            self::FLAT_FEE => 'A fixed dollar amount per item sold',
            self::TIERED => 'Commission rates vary based on supplier performance tier',
            self::CATEGORY_BASED => 'Different rates for different product categories',
            self::HYBRID => 'Combination of percentage and flat fee',
            self::VOLUME_BASED => 'Rates change based on sales volume',
            self::CUSTOM => 'Complex custom rules defined in JSON',
        };
    }

    public function requiresBasePercentage(): bool
    {
        return in_array($this, [
            self::PERCENTAGE,
            self::TIERED,
            self::CATEGORY_BASED,
            self::HYBRID,
        ]);
    }

    public function requiresFlatFee(): bool
    {
        return in_array($this, [
            self::FLAT_FEE,
            self::HYBRID,
        ]);
    }

    public function supportsCustomRules(): bool
    {
        return $this === self::CUSTOM;
    }
}
