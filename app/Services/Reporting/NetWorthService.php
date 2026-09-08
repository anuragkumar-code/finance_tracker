<?php

namespace App\Services\Reporting;

use App\Models\Account;
use App\Models\Loan;

/**
 * What the household owns minus what it owes (spec section 19J).
 *
 * This is the ONE definition of net worth in the application. It previously
 * existed in two services, neither of which counted loans — which meant the
 * Accounts page could report a healthy net worth while ignoring lakhs of loan
 * debt. A number that wrong is worse than no number at all.
 *
 * Loan debt here is REMAINING CASH TO PAY (remaining EMIs x EMI amount), which
 * includes future interest, because the simplified loan model tracks EMIs
 * rather than principal (design doc D11). That overstates debt relative to a
 * lender's payoff quote, deliberately — the conservative direction — and every
 * screen showing it says so.
 */
class NetWorthService
{
    private const SCALE = 2;

    /**
     * @return array{
     *     bank_cash: string, investments: string, other_assets: string, assets: string,
     *     card_debt: string, other_liabilities: string, loan_debt: string, liabilities: string,
     *     net_worth: string
     * }
     */
    public function summary(): array
    {
        $bankCash = $this->sum(Account::query()->active()->spendableCash());
        $investments = $this->sum(Account::query()->active()->ofType(\App\Enums\AccountType::Investment));
        $otherAssets = $this->sum(Account::query()->active()->ofType(\App\Enums\AccountType::OtherAsset));

        $assets = $this->sum(Account::query()->active()->assets());

        $cardDebt = $this->sum(Account::query()->active()->ofType(\App\Enums\AccountType::CreditCard));
        $otherLiabilities = $this->sum(Account::query()->active()->ofType(\App\Enums\AccountType::OtherLiability));
        $loanDebt = $this->loanDebt();

        $liabilities = bcadd(
            $this->sum(Account::query()->active()->liabilities()),
            $loanDebt,
            self::SCALE,
        );

        return [
            'bank_cash' => $bankCash,
            'investments' => $investments,
            'other_assets' => $otherAssets,
            'assets' => $assets,

            'card_debt' => $cardDebt,
            'other_liabilities' => $otherLiabilities,
            'loan_debt' => $loanDebt,
            'liabilities' => $liabilities,

            'net_worth' => bcsub($assets, $liabilities, self::SCALE),
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

    private function sum($query): string
    {
        return bcadd((string) $query->sum('cached_balance'), '0', self::SCALE);
    }
}
