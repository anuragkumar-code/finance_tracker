<?php

namespace App\Services\Reporting;

use App\Enums\AccountType;
use App\Enums\BalanceEffect;
use App\Enums\NormalBalance;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonInterface;
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

    /** Money the household can actually reach right now: bank + cash, minus anything set aside. */
    public function spendableCash(): string
    {
        return $this->decimal(Account::query()->active()->spendableCash()->sum('cached_balance'));
    }

    /**
     * Spending grouped by subcategory within a period.
     *
     * @return Collection<int, object{label: string, parent: string, amount: string, category_id: ?int}>
     */
    public function bySubcategory(string $start, string $end): Collection
    {
        return Transaction::query()
            ->spending()
            ->inPeriod($start, $end)
            ->whereNotNull('subcategory_id')
            ->join('categories as sub', 'sub.id', '=', 'transactions.subcategory_id')
            ->leftJoin('categories as parent', 'parent.id', '=', 'sub.parent_id')
            ->selectRaw('sub.name AS label, COALESCE(parent.name, "") AS parent,
                         transactions.subcategory_id, SUM(transactions.amount) AS amount')
            ->groupBy('transactions.subcategory_id', 'sub.name', 'parent.name')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => (object) [
                'label' => $row->label,
                'parent' => $row->parent,
                'category_id' => $row->subcategory_id,
                'amount' => $this->decimal($row->amount),
            ]);
    }

    /**
     * Where the money went by merchant — the "who are we actually paying" cut.
     *
     * @return Collection<int, object{label: string, amount: string, count: int, merchant_id: ?int}>
     */
    public function byMerchant(string $start, string $end, int $limit = 15): Collection
    {
        return Transaction::query()
            ->spending()
            ->inPeriod($start, $end)
            ->whereNotNull('merchant_id')
            ->join('merchants', 'merchants.id', '=', 'transactions.merchant_id')
            ->selectRaw('merchants.name AS label, transactions.merchant_id,
                         SUM(transactions.amount) AS amount, COUNT(*) AS entries')
            ->groupBy('transactions.merchant_id', 'merchants.name')
            ->orderByDesc('amount')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (object) [
                'label' => $row->label,
                'merchant_id' => $row->merchant_id,
                'amount' => $this->decimal($row->amount),
                'count' => (int) $row->entries,
            ]);
    }

    /**
     * Spending split into weeks inside one month (spec section 19B).
     *
     * Calendar weeks (1st–7th, 8th–14th, …) rather than ISO weeks, so "Week 1"
     * means the start of the month the way the household reads a statement.
     *
     * @return Collection<int, object{label: string, start: string, end: string, spending: string, income: string}>
     */
    public function weekly(CarbonInterface $month): Collection
    {
        $monthStart = $month->copy()->startOfMonth();
        $daysInMonth = $monthStart->daysInMonth;
        $weeks = collect();

        for ($startDay = 1; $startDay <= $daysInMonth; $startDay += 7) {
            $endDay = min($startDay + 6, $daysInMonth);

            $start = $monthStart->copy()->setDay($startDay)->toDateString();
            $end = $monthStart->copy()->setDay($endDay)->toDateString();

            $weeks->push((object) [
                'label' => 'Week '.$weeks->count() + 1,
                'range' => $startDay.'–'.$endDay,
                'start' => $start,
                'end' => $end,
                'spending' => $this->totalSpending($start, $end),
                'income' => $this->totalIncome($start, $end),
            ]);
        }

        return $weeks;
    }

    /**
     * Month-by-month totals for the trend view (spec section 20).
     *
     * @return Collection<int, object{
     *     label: string, month: string, start: string, end: string,
     *     income: string, spending: string, card_spending: string, net: string
     * }>
     */
    public function monthlySeries(int $months = 6, ?CarbonInterface $endingAt = null): Collection
    {
        $endingAt ??= now();
        $series = collect();

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = $endingAt->copy()->startOfMonth()->subMonthsNoOverflow($i);
            $start = $month->toDateString();
            $end = $month->copy()->endOfMonth()->toDateString();

            $income = $this->totalIncome($start, $end);
            $spending = $this->totalSpending($start, $end);

            $series->push((object) [
                'label' => $month->format('M Y'),
                'short' => $month->format('M'),
                'month' => $month->format('Y-m'),
                'start' => $start,
                'end' => $end,
                'income' => $income,
                'spending' => $spending,
                'card_spending' => $this->creditCardSpending($start, $end),
                'net' => bcsub($income, $spending, self::SCALE),
            ]);
        }

        return $series;
    }

    /**
     * Category totals across several months, shaped as a grid (spec section 20).
     *
     * Pivoted in PHP rather than SQL so every cell comes from the same
     * totalSpending row set — a month column here can never disagree with the
     * same month on the dashboard.
     *
     * @return array{months: array<int, string>, rows: array<int, array{label: string, values: array<string, string>, total: string}>}
     */
    public function categoryByMonth(int $months = 6, ?CarbonInterface $endingAt = null): array
    {
        $series = $this->monthlySeries($months, $endingAt);
        $first = $series->first();
        $last = $series->last();

        if ($first === null) {
            return ['months' => [], 'rows' => []];
        }

        $rows = Transaction::query()
            ->spending()
            ->inPeriod($first->start, $last->end)
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw('COALESCE(categories.name, "Uncategorised") AS label,
                         DATE_FORMAT(transactions.transaction_date, "%Y-%m") AS ym,
                         SUM(transactions.amount) AS amount')
            ->groupBy('label', 'ym')
            ->get();

        $months_ = $series->pluck('month')->all();
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row->label][$row->ym] = $this->decimal($row->amount);
        }

        $out = [];

        foreach ($grouped as $label => $values) {
            $total = '0.00';
            $cells = [];

            foreach ($months_ as $ym) {
                $cells[$ym] = $values[$ym] ?? '0.00';
                $total = bcadd($total, $cells[$ym], self::SCALE);
            }

            $out[] = ['label' => $label, 'values' => $cells, 'total' => $total];
        }

        usort($out, fn ($a, $b) => bccomp($b['total'], $a['total'], self::SCALE));

        return ['months' => $series->all(), 'rows' => $out];
    }

    /** Spending that came from loan EMIs — a subset of the headline figure (design doc D12). */
    public function debtRepayment(string $start, string $end): string
    {
        return $this->decimal(
            Transaction::query()
                ->spending()
                ->inPeriod($start, $end)
                ->where('purpose', \App\Enums\Purpose::Debt->value)
                ->sum('amount')
        );
    }

    private function assetLegs(string $start, string $end, BalanceEffect $effect)
    {
        return Transaction::query()
            ->inPeriod($start, $end)
            ->where('balance_effect', $effect->value)
            ->whereHas('account', fn ($q) => $q
                ->where('normal_balance', NormalBalance::Asset->value)
                // A transfer into the emergency fund should read as money
                // leaving, not as an internal move that nets to zero.
                ->where('is_set_aside', false));
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
