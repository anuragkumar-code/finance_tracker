<?php

namespace App\Services\Reporting;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Loan;

/**
 * What the household owns minus what it owes (spec section 19J).
 *
 * This is the ONE definition of net worth in the application. It previously
 * existed in two services, neither of which counted loans — which meant the
 * Accounts page could report a healthy net worth while ignoring lakhs of loan
 * debt. A number that wrong is worse than no number at all.
 *
 * Two things this deliberately does NOT do:
 *
 * - It does not exclude set-aside money. An emergency fund is not spendable, but
 *   the household still owns it; leaving it out would understate their position
 *   as badly as leaving out the loans overstated it. It is reported separately
 *   so the distinction stays visible.
 * - It does not guess at the value of an unvalued asset. Those are counted and
 *   reported as a caveat instead, so a partial picture is never mistaken for a
 *   complete one.
 *
 * Loan debt is REMAINING CASH TO PAY (remaining EMIs x EMI amount), which
 * includes future interest, because the simplified loan model tracks EMIs rather
 * than principal (design doc D11) — the conservative direction, and labelled.
 */
class NetWorthService
{
    private const SCALE = 2;

    /**
     * @return array{
     *     bank_cash: string, set_aside: string, investments: string, asset_value: string,
     *     account_assets: string, assets: string,
     *     card_debt: string, other_liabilities: string, loan_debt: string, liabilities: string,
     *     net_worth: string, unvalued_assets: int
     * }
     */
    public function summary(): array
    {
        $spendable = $this->sumAccounts(Account::query()->active()->spendableCash());
        $setAside = $this->sumAccounts(Account::query()->active()->setAside());
        $investments = $this->sumAccounts(Account::query()->active()->ofType(AccountType::Investment));

        $accountAssets = $this->sumAccounts(Account::query()->active()->assets());
        $assetValue = $this->assetValue();
        $assets = bcadd($accountAssets, $assetValue, self::SCALE);

        $cardDebt = $this->sumAccounts(Account::query()->active()->ofType(AccountType::CreditCard));
        $otherLiabilities = $this->sumAccounts(Account::query()->active()->ofType(AccountType::OtherLiability));
        $loanDebt = $this->loanDebt();

        $liabilities = bcadd(
            $this->sumAccounts(Account::query()->active()->liabilities()),
            $loanDebt,
            self::SCALE,
        );

        return [
            'bank_cash' => $spendable,
            'set_aside' => $setAside,
            'investments' => $investments,
            'asset_value' => $assetValue,
            'account_assets' => $accountAssets,
            'assets' => $assets,

            'card_debt' => $cardDebt,
            'other_liabilities' => $otherLiabilities,
            'loan_debt' => $loanDebt,
            'liabilities' => $liabilities,

            'net_worth' => bcsub($assets, $liabilities, self::SCALE),
            'unvalued_assets' => Asset::query()->active()->whereNull('current_value')->count(),
        ];
    }

    /** Cash still owed across running loans. */
    public function loanDebt(): string
    {
        return Loan::query()->active()->get()->reduce(
            fn (string $carry, Loan $loan) => bcadd($carry, $loan->remainingAmount(), self::SCALE),
            '0.00',
        );
    }

    /** Only assets someone has actually put a number on. */
    public function assetValue(): string
    {
        return bcadd((string) Asset::query()->active()->valued()->sum('current_value'), '0', self::SCALE);
    }

    private function sumAccounts($query): string
    {
        return bcadd((string) $query->sum('cached_balance'), '0', self::SCALE);
    }
}
