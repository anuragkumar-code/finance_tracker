<?php

namespace App\Enums;

/**
 * Where a credit-card statement stands (spec section 4).
 *
 * A statement is a grouping of purchases already recorded, never a new expense,
 * so these states describe payment progress only.
 */
enum StatementStatus: string
{
    case Open = 'open';
    case Generated = 'generated';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Current cycle',
            self::Generated => 'Awaiting payment',
            self::PartiallyPaid => 'Partly paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'secondary',
            self::Generated => 'primary',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Overdue => 'danger',
        };
    }

    public function isSettled(): bool
    {
        return $this === self::Paid;
    }
}
