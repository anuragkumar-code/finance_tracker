<?php

namespace App\Enums;

/**
 * Why the money went out — the "was this worth it?" axis (spec section 6).
 *
 * Deliberately short. The original list (necessity / lifestyle / discretionary /
 * emergency / family / investment / debt) was seven overlapping options and the
 * household stopped filling it in, which is worse than a blunter list they
 * actually use. "Lifestyle" and "discretionary" meant the same thing in
 * practice, and "emergency" duplicated the planned-status field.
 *
 * What is left answers one question: could we have skipped this?
 */
enum Purpose: string
{
    case Need = 'need';
    case Want = 'want';
    case Family = 'family';
    case Investment = 'investment';
    case Debt = 'debt';

    public function label(): string
    {
        return match ($this) {
            self::Need => 'Needed it',
            self::Want => 'Wanted it',
            self::Family => 'For family',
            self::Investment => 'Investment',
            self::Debt => 'Loan / EMI',
        };
    }

    /** One line of plain English, shown under the picker. */
    public function hint(): string
    {
        return match ($this) {
            self::Need => 'Rent, bills, groceries, medicine',
            self::Want => 'Eating out, gadgets, treats',
            self::Family => 'Parents, gifts, family support',
            self::Investment => 'Savings, SIP, buying an asset',
            self::Debt => 'EMI or paying down a loan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Need => 'primary',
            self::Want => 'warning',
            self::Family => 'info',
            self::Investment => 'success',
            self::Debt => 'secondary',
        };
    }

    /** Spending the household could not realistically have avoided. */
    public function isEssential(): bool
    {
        return in_array($this, [self::Need, self::Debt], true);
    }
}
