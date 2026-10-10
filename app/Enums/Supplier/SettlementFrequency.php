<?php

namespace App\Enums\Supplier;

enum SettlementFrequency: string
{
    case PER_SALE = 'per_sale';
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case BI_WEEKLY = 'bi_weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::PER_SALE => 'Per Sale (Immediate)',
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::BI_WEEKLY => 'Bi-Weekly',
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::CUSTOM => 'Custom Schedule',
        };
    }

    public function daysInterval(): ?int
    {
        return match ($this) {
            self::PER_SALE => 0,
            self::DAILY => 1,
            self::WEEKLY => 7,
            self::BI_WEEKLY => 14,
            self::MONTHLY => 30,
            self::QUARTERLY => 90,
            self::CUSTOM => null,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PER_SALE => 'Settlement processed immediately after each sale',
            self::DAILY => 'Settlements processed every day',
            self::WEEKLY => 'Settlements processed once per week',
            self::BI_WEEKLY => 'Settlements processed every two weeks',
            self::MONTHLY => 'Settlements processed once per month',
            self::QUARTERLY => 'Settlements processed every three months',
            self::CUSTOM => 'Custom settlement schedule',
        };
    }

    public function getNextSettlementDate(?\DateTime $fromDate = null): \DateTime
    {
        $date = $fromDate ? clone $fromDate : new \DateTime;

        return match ($this) {
            self::PER_SALE => $date,
            self::DAILY => $date->modify('+1 day'),
            self::WEEKLY => $date->modify('next monday'),
            self::BI_WEEKLY => $date->modify('+2 weeks')->modify('monday this week'),
            self::MONTHLY => $date->modify('first day of next month'),
            self::QUARTERLY => $date->modify('first day of +3 months'),
            self::CUSTOM => $date->modify('+1 month'),
        };
    }

    public static function forTier(string $tierCode): self
    {
        return match (strtoupper($tierCode)) {
            'PLATINUM' => self::PER_SALE,
            'GOLD' => self::WEEKLY,
            'SILVER' => self::BI_WEEKLY,
            'BRONZE', 'PROBATION' => self::MONTHLY,
            default => self::MONTHLY,
        };
    }
}
