<?php

namespace App\Services;

use App\Enums\BalanceEffect;
use App\Enums\LegRole;
use App\Enums\NormalBalance;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Sole owner of balance arithmetic and the sign matrix (design doc section 1.3).
 *
 * Nothing else in the application decides whether a transaction adds to or
 * subtracts from an account — if that logic appears in a controller or a Blade
 * view, it is a bug.
 *
 * All money arithmetic uses bcmath at scale 2. Floats are never used for
 * balances: 0.1 + 0.2 must equal 0.30 in a system whose whole purpose is
 * matching real bank statements.
 */
class AccountBalanceService
{
    private const SCALE = 2;

    /**
     * Which direction a leg moves its account's balance.
     *
     * Balances are stored as positive magnitudes, so for a liability account an
     * "increase" means the household owes MORE.
     *
     * @throws InvalidArgumentException for Adjustment, where the direction is
     *                                  chosen by the reconciliation, not derived.
     */
    public function effectFor(
        TransactionType $type,
        LegRole $legRole,
        NormalBalance $normalBalance,
    ): BalanceEffect {
        $isAsset = $normalBalance === NormalBalance::Asset;

        return match ($type) {
            // Money consumed: leaves an asset account, or grows what's owed on a card.
            TransactionType::Expense => $isAsset ? BalanceEffect::Decrease : BalanceEffect::Increase,

            // Money received: into an asset account, or a refund reducing a card balance.
            TransactionType::Income => $isAsset ? BalanceEffect::Increase : BalanceEffect::Decrease,

            TransactionType::Transfer => match ($legRole) {
                LegRole::TransferFrom => BalanceEffect::Decrease,
                LegRole::TransferTo => BalanceEffect::Increase,
                default => throw new InvalidArgumentException(
                    "Transfer legs must be transfer_from or transfer_to, got {$legRole->value}."
                ),
            },

            // Both legs decrease: the bank loses cash, and the debt owed shrinks.
            TransactionType::LiabilityPayment => BalanceEffect::Decrease,

            TransactionType::AssetPurchase => $isAsset ? BalanceEffect::Decrease : BalanceEffect::Increase,

            TransactionType::Adjustment => throw new InvalidArgumentException(
                'Adjustment direction must be supplied explicitly by the reconciliation workflow.'
            ),
        };
    }

    /**
     * Authoritative balance, derived from source rows (spec Rule 8).
     *
     * Transactions dated before the account's opening_balance_date are excluded:
     * the opening balance is a snapshot that already embodies their net effect,
     * so counting them again would double-count. Such rows still appear in
     * spending reports as historical activity.
     */
    public function balance(Account $account, CarbonInterface|string|null $asOf = null): string
    {
        $query = Transaction::query()
            ->where('account_id', $account->getKey())
            ->where('transaction_date', '>=', $account->opening_balance_date);

        if ($asOf !== null) {
            $query->where('transaction_date', '<=', $asOf);
        }

        $totals = $query->selectRaw(
            "COALESCE(SUM(CASE WHEN balance_effect = 'increase' THEN amount ELSE 0 END), 0) AS increases,
             COALESCE(SUM(CASE WHEN balance_effect = 'decrease' THEN amount ELSE 0 END), 0) AS decreases"
        )->first();

        $balance = bcadd((string) $account->opening_balance, (string) $totals->increases, self::SCALE);

        return bcsub($balance, (string) $totals->decreases, self::SCALE);
    }

    /**
     * Refresh the denormalised cache from source rows.
     *
     * Called synchronously inside the same DB transaction as every ledger write,
     * so the cache can never be observed stale by a subsequent read.
     */
    public function recalculate(Account $account): string
    {
        $balance = $this->balance($account);

        $account->forceFill([
            'cached_balance' => $balance,
            'cached_balance_as_of' => now(),
        ])->save();

        return $balance;
    }

    /**
     * Recalculate several accounts at once, de-duplicated.
     *
     * @param  iterable<Account|int|null>  $accounts
     */
    public function recalculateMany(iterable $accounts): void
    {
        $ids = collect($accounts)
            ->filter()
            ->map(fn (Account|int $a) => $a instanceof Account ? $a->getKey() : $a)
            ->unique()
            ->all();

        foreach (Account::withTrashed()->findMany($ids) as $account) {
            $this->recalculate($account);
        }
    }

    /**
     * Self-heal pass: re-derive every account from its ledger rows.
     *
     * A consistency check, never the primary mechanism — if this ever changes a
     * balance, something wrote to the ledger outside the service layer.
     *
     * @return array<string, array{from: string, to: string}> drifted accounts, keyed by name
     */
    public function recalculateAll(): array
    {
        $drift = [];

        foreach (Account::withTrashed()->cursor() as $account) {
            $before = (string) $account->cached_balance;
            $after = $this->recalculate($account);

            if (bccomp($before, $after, self::SCALE) !== 0) {
                $drift[$account->name] = ['from' => $before, 'to' => $after];
            }
        }

        return $drift;
    }

    /** Net worth: what the household owns minus what it owes (spec section 19J). */
    public function netWorth(): array
    {
        $assets = (string) Account::query()->active()->assets()->sum('cached_balance');
        $liabilities = (string) Account::query()->active()->liabilities()->sum('cached_balance');

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'net_worth' => bcsub($assets, $liabilities, self::SCALE),
        ];
    }
}
