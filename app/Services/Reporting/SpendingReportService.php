<?php

namespace App\Services\Reporting;

use App\Enums\AccountType;
use App\Enums\BalanceEffect;
use App\Enums\NormalBalance;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * The single place the spec's section 26 metric definitions are implemented.
 *
 * Every figure on the dashboard and in reports comes from a method here, so a
 * monthly total and a dashboard total for the same period can never quietly
 * disagree. Each method is one filtered variant of the same base row set, which
 * is also what makes drill-down honest: clicking a number re-runs the ungrouped
 * query behind it.
 *
 * Phase 1 covers spending, income and cash movement. Loan interest joins
 * totalSpending() in Phase 3; credit-card metrics arrive in Phase 2.
 */
class SpendingReportService
{
    private const SCALE = 2;

    /**
     * Total Spending — value consumed in the period.
     *
     * Excludes transfers, credit-card bill payments, loan principal and opening
     * balances, none of which are type='expense'. That exclusion is structural,
     * not a filter someone has to remember.
     */
    public function totalSpending(string $start, string $end): string
    {
        return $this->decimal(
            Transaction::query()->spending()->inPeriod($start, $end)->sum('amount')
        );
    }

    /** Money received in the period. */
    public function totalIncome(string $start, string $end): string
    {
        return $this->decimal(
            Transaction::query()->ofType(TransactionType::Income)->inPeriod($start, $end)->sum('amount')
        );
    }

    /**
     * Cash Outflow — actual money leaving bank/cash/investment accounts.
     *
     * Deliberately broader than spending: it includes transfers out and
     * (from Phase 2) card bill payments, so it answers "what left our accounts"
     * rather than "what did we consume".
     */
    public function cashOutflow(string $start, string $end): string
    {
        return $this->decimal($this->assetLegs($start, $end, BalanceEffect::Decrease)->sum('amount'));
    }

    public function cashInflow(string $start, string $end): string
    {
        return $this->decimal($this->assetLegs($start, $end, BalanceEffect::Increase)->sum('amount'));
    }

    /** Net Cash Movement — inflow minus outflow across asset accounts. */
    public function netCashMovement(string $start, string $end): string
    {
        return bcsub($this->cashInflow($start, $end), $this->cashOutflow($start, $end), self::SCALE);
    }

    /** Spending charged to credit cards in the period (purchases, not payments). */
    public function creditCardSpending(string $start, string $end): string
    {
        return $this->decimal(
            Transaction::query()
                ->spending()
                ->inPeriod($start, $end)
                ->whereHas('account', fn ($q) => $q->where('type', AccountType::CreditCard->value))
                ->sum('amount')
        );
    }

    /**
     * Spending grouped by top-level category.
     *
     * @return Collection<int, object{label: string, amount: string, category_id: ?int}>
     */
    public function byCategory(string $start, string $end): Collection
    {
        return Transaction::query()
            ->spending()
            ->inPeriod($start, $end)
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw('COALESCE(categories.name, "Uncategorised") AS label,
                         transactions.category_id,
                         SUM(transactions.amount) AS amount')
            ->groupBy('transactions.category_id', 'categories.name')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => (object) [
                'label' => $row->label,
                'category_id' => $row->category_id,
                'amount' => $this->decimal($row->amount),
            ]);
    }

    /**
     * Spending grouped by a simple transaction column — payer, beneficiary,
     * planned status, purpose or account.
     *
     * @return Collection<int, object{label: string, amount: string, key: mixed}>
     */
    public function groupedBy(string $column, string $start, string $end): Collection
    {
        $allowed = ['payer_id', 'beneficiary_id', 'planned_status', 'purpose', 'account_id'];

        if (! in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException("Cannot group spending by \"{$column}\".");
        }

        $rows = Transaction::query()
            ->spending()
            ->inPeriod($start, $end)
            ->selectRaw("{$column} AS group_key, SUM(amount) AS amount")
            ->groupBy($column)
            ->orderByDesc('amount')
            ->get();

        $labels = $this->labelsFor($column, $rows->pluck('group_key')->filter()->all());

        return $rows->map(fn ($row) => (object) [
            'key' => $row->group_key,
            'label' => $row->group_key === null
                ? 'Not recorded'
                : ($labels[$row->group_key] ?? (string) $row->group_key),
            'amount' => $this->decimal($row->amount),
        ]);
    }

    /** Money the household can actually reach right now: bank + cash. */
    public function spendableCash(): string
    {
        return $this->decimal(Account::query()->active()->spendableCash()->sum('cached_balance'));
    }

    /** @return array{assets: string, liabilities: string, net_worth: string} */
    public function netWorth(): array
    {
        $assets = $this->decimal(Account::query()->active()->assets()->sum('cached_balance'));
        $liabilities = $this->decimal(Account::query()->active()->liabilities()->sum('cached_balance'));

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'net_worth' => bcsub($assets, $liabilities, self::SCALE),
        ];
    }

    private function assetLegs(string $start, string $end, BalanceEffect $effect)
    {
        return Transaction::query()
            ->inPeriod($start, $end)
            ->where('balance_effect', $effect->value)
            ->whereHas('account', fn ($q) => $q->where('normal_balance', NormalBalance::Asset->value));
    }

    /** @return array<int|string, string> */
    private function labelsFor(string $column, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        return match ($column) {
            'payer_id', 'beneficiary_id' => \App\Models\Person::whereIn('id', $keys)
                ->pluck('name', 'id')->all(),
            'account_id' => Account::whereIn('id', $keys)->pluck('name', 'id')->all(),
            'planned_status' => collect($keys)
                ->mapWithKeys(fn ($k) => [$k => \App\Enums\PlannedStatus::from($k)->label()])->all(),
            'purpose' => collect($keys)
                ->mapWithKeys(fn ($k) => [$k => \App\Enums\Purpose::from($k)->label()])->all(),
            default => [],
        };
    }

    private function decimal(mixed $value): string
    {
        return bcadd((string) ($value ?? '0'), '0', self::SCALE);
    }
}
