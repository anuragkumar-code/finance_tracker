<?php

namespace App\Enums;

/**
 * Which side of a linked pair a transaction row represents.
 *
 * Single-account events use `Single`. Two-legged events (transfers, credit-card
 * bill payments) write one "from" row and one "to" row sharing a transfer_group_id.
 */
enum LegRole: string
{
    case Single = 'single';
    case TransferFrom = 'transfer_from';
    case TransferTo = 'transfer_to';
    case PaymentFrom = 'payment_from';
    case PaymentTo = 'payment_to';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single entry',
            self::TransferFrom => 'Transferred from',
            self::TransferTo => 'Transferred to',
            self::PaymentFrom => 'Paid from',
            self::PaymentTo => 'Paid towards',
        };
    }

    /** The money-leaving side of a linked pair. */
    public function isSource(): bool
    {
        return in_array($this, [self::TransferFrom, self::PaymentFrom], true);
    }
}
