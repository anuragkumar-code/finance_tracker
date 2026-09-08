<?php

namespace App\Enums;

/**
 * Which side of the balance sheet an account sits on.
 *
 * Balances are always stored as positive magnitudes. For a liability account
 * the balance means "amount owed", so an increase is a worse position.
 */
enum NormalBalance: string
{
    case Asset = 'asset';
    case Liability = 'liability';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Asset',
            self::Liability => 'Liability',
        };
    }
}
