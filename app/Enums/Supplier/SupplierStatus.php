<?php

namespace App\Enums\Supplier;

enum SupplierStatus: string
{
    case PENDING = 'pending';
    case ONBOARDING = 'onboarding';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case INACTIVE = 'inactive';
    case TERMINATED = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Review',
            self::ONBOARDING => 'Onboarding',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::INACTIVE => 'Inactive',
            self::TERMINATED => 'Terminated',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::ONBOARDING => 'blue',
            self::ACTIVE => 'green',
            self::SUSPENDED => 'orange',
            self::INACTIVE => 'gray',
            self::TERMINATED => 'red',
        };
    }

    public function canSubmitProducts(): bool
    {
        return in_array($this, [self::ACTIVE]);
    }

    public function canReceiveSettlements(): bool
    {
        return in_array($this, [self::ACTIVE, self::INACTIVE]);
    }

    public static function activeStatuses(): array
    {
        return [self::ACTIVE, self::ONBOARDING];
    }
}
