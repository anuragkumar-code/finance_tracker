<?php

namespace App\Enums;

/**
 * Whether a transaction leg adds to or subtracts from its account's balance.
 *
 * Amounts are always stored as positive magnitudes (design doc D2); this enum
 * carries the direction, so report queries never need to flip signs.
 */
enum BalanceEffect: string
{
    case Increase = 'increase';
    case Decrease = 'decrease';

    public function sign(): int
    {
        return $this === self::Increase ? 1 : -1;
    }

    public function opposite(): self
    {
        return $this === self::Increase ? self::Decrease : self::Increase;
    }
}
