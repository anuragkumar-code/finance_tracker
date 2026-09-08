<?php

namespace App\Enums;

/**
 * The financial event a transaction row belongs to (spec section 9).
 *
 * Only Expense, Income and Transfer are exposed in Phase 1; the remaining
 * cases exist in the column now so later phases need no enum migration.
 */
enum TransactionType: string
{
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';
    case AssetPurchase = 'asset_purchase';
    case LiabilityPayment = 'liability_payment';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Spent',
            self::Income => 'Received',
            self::Transfer => 'Moved',
            self::AssetPurchase => 'Asset Purchase',
            self::LiabilityPayment => 'Liability Payment',
            self::Adjustment => 'Adjustment',
        };
    }

    /** Types the Phase 1 application layer is allowed to create. */
    public static function phaseOne(): array
    {
        return [self::Expense, self::Income, self::Transfer];
    }

    /**
     * Whether this type counts toward "Total Spending" (spec section 26).
     *
     * Loan interest also counts, but it lives on loan_payments rather than as
     * its own transaction row, so it is added separately by the report layer.
     */
    public function countsAsSpending(): bool
    {
        return $this === self::Expense;
    }

    /** Multi-account events are written as two linked legs sharing a transfer_group_id. */
    public function isTwoLegged(): bool
    {
        return in_array($this, [self::Transfer, self::LiabilityPayment], true);
    }
}
