<?php

namespace App\Enums;

/**
 * Why the money was spent (spec section 6) — orthogonal to category.
 *
 * Category says what was bought; purpose says what role it played in the
 * household's finances.
 */
enum Purpose: string
{
    case Necessity = 'necessity';
    case Lifestyle = 'lifestyle';
    case Family = 'family';
    case Investment = 'investment';
    case Debt = 'debt';
    case Emergency = 'emergency';
    case Discretionary = 'discretionary';

    public function label(): string
    {
        return match ($this) {
            self::Necessity => 'Necessity',
            self::Lifestyle => 'Lifestyle',
            self::Family => 'Family',
            self::Investment => 'Investment',
            self::Debt => 'Debt',
            self::Emergency => 'Emergency',
            self::Discretionary => 'Discretionary',
        };
    }

    /** Purposes that roll up into the dashboard's "Essentials" figure. */
    public function isEssential(): bool
    {
        return in_array($this, [self::Necessity, self::Debt], true);
    }
}
