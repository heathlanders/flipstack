<?php

namespace App\Enums\Supplier;

enum SettlementStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case APPROVED = 'approved';
    case PENDING_PAYMENT = 'pending_payment';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case PARTIALLY_PAID = 'partially_paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case DISPUTED = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_REVIEW => 'Pending Review',
            self::APPROVED => 'Approved',
            self::PENDING_PAYMENT => 'Pending Payment',
            self::PROCESSING => 'Processing',
            self::PAID => 'Paid',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::DISPUTED => 'Disputed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING_REVIEW => 'yellow',
            self::APPROVED => 'blue',
            self::PENDING_PAYMENT => 'purple',
            self::PROCESSING => 'indigo',
            self::PAID => 'green',
            self::PARTIALLY_PAID => 'orange',
            self::FAILED => 'red',
            self::CANCELLED => 'gray',
            self::DISPUTED => 'red',
        };
    }

    public function canBeEdited(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING_REVIEW]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::PENDING_REVIEW,
            self::APPROVED,
            self::PENDING_PAYMENT,
        ]);
    }

    public function canBeDisputed(): bool
    {
        return in_array($this, [
            self::APPROVED,
            self::PENDING_PAYMENT,
            self::PAID,
            self::PARTIALLY_PAID,
        ]);
    }

    public function isFinalized(): bool
    {
        return in_array($this, [self::PAID, self::CANCELLED]);
    }

    public static function pendingStatuses(): array
    {
        return [
            self::DRAFT,
            self::PENDING_REVIEW,
            self::APPROVED,
            self::PENDING_PAYMENT,
            self::PROCESSING,
        ];
    }
}
