<?php

namespace App\Enums;

/**
 * Account types the household holds money in (or owes money on).
 *
 * Loans are deliberately NOT an account type — they live in their own `loans`
 * table with their own outstanding principal (design doc D5).
 */
enum AccountType: string
{
    case Bank = 'bank';
    case Cash = 'cash';
    case CreditCard = 'credit_card';
    case Investment = 'investment';
    case OtherAsset = 'other_asset';
    case OtherLiability = 'other_liability';

    public function label(): string
    {
        return match ($this) {
            self::Bank => 'Bank Account',
            self::Cash => 'Cash',
            self::CreditCard => 'Credit Card',
            self::Investment => 'Investment Account',
            self::OtherAsset => 'Other Asset',
            self::OtherLiability => 'Other Liability',
        };
    }

    /**
     * The side of the balance sheet this account type belongs to.
     * Set once at account creation and stored on the row.
     */
    public function normalBalance(): NormalBalance
    {
        return match ($this) {
            self::Bank, self::Cash, self::Investment, self::OtherAsset => NormalBalance::Asset,
            self::CreditCard, self::OtherLiability => NormalBalance::Liability,
        };
    }

    /** Account types that hold spendable money, for the "current bank + cash" figure. */
    public function isSpendableCash(): bool
    {
        return in_array($this, [self::Bank, self::Cash], true);
    }
}
