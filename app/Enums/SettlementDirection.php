<?php

namespace App\Enums;

/**
 * Which way money is owed once a shared cost is squared up.
 *
 * TheyOwe: the household paid more than its share, so part of what it paid was
 * really a loan to the friend. That part is taken out of spending.
 *
 * WeOwe: the friend paid more than their share, so part of what they paid was
 * the household's own spending, even though it never left a household account.
 * That part is added to spending.
 */
enum SettlementDirection: string
{
    case TheyOwe = 'they_owe';
    case WeOwe = 'we_owe';

    public function label(): string
    {
        return match ($this) {
            self::TheyOwe => 'owes you',
            self::WeOwe => 'you owe',
        };
    }
}
