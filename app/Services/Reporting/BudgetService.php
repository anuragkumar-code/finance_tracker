<?php

namespace App\Services\Reporting;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Budgets, variance, and spotting a month that is behaving oddly
 * (spec sections 22 and 33 phase 6).
 *
 * The spec is emphatic that budgets come last and only after two or three
 * months of real data, because a target invented before you know your own
 * behaviour is just a number to feel bad about. This service therefore refuses
 * to SUGGEST an amount until it has enough history to base one on, and says so
 * rather than guessing. Setting a budget by hand is always allowed — the
 * household may well know what their rent is.
 */
class BudgetService
{
    private const SCALE = 2;

    /** Months of history needed before a suggestion means anything (spec section 22). */
    public const MONTHS_FOR_SUGGESTION = 3;

    public function __construct(
        private readonly SpendingReportService $reports,
    ) {}

    /**
     * Set or change a category's monthly target.
     *
     * Supersedes rather than overwrites: the previous budget is closed off at
     * the end of the preceding month so historical comparisons keep judging
     * each month by the target that was actually in force.
     */
    public function setBudget(Category $category, string $amount, ?CarbonInterface $from = null): Budget
    {
        if (! is_numeric($amount) || bccomp($amount, '0', self::SCALE) !== 1) {
            throw new InvalidArgumentException('A budget needs an amount greater than zero.');
        }

        $from = ($from ?? now())->copy()->startOfMonth();

        return DB::transaction(function () use ($category, $amount, $from) {
            $current = Budget::where('category_id', $category->id)
                ->whereNull('effective_to')
                ->orderByDesc('effective_from')
                ->first();

            if ($current !== null) {
                // Replacing a budget that started this same month is a
                // correction, not a change of plan — no history worth keeping.
                if ($current->effective_from->equalTo($from)) {
                    $current->update(['amount' => $amount]);

                    return $current->refresh();
                }

                $current->update(['effective_to' => $from->copy()->subDay()->toDateString()]);
            }

            return Budget::create([
                'category_id' => $category->id,
                'amount' => $amount,
                'effective_from' => $from->toDateString(),
            ]);
        });
    }

    public function removeBudget(Category $category, ?CarbonInterface $from = null): void
    {
        $from = ($from ?? now())->copy()->startOfMonth();

        Budget::where('category_id', $category->id)
            ->whereNull('effective_to')
            ->update(['effective_to' => $from->copy()->subDay()->toDateString()]);
    }

    /**
     * Budget against actual for one month (spec section 22).
     *
     * Categories with no budget are still listed, with their actual spend, so
     * an unbudgeted category that is quietly eating money cannot hide by simply
     * never having had a target set.
     *
     * @return Collection<int, object>
     */
    public function comparison(CarbonInterface $month): Collection
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $budgets = Budget::query()->coveringMonth($month)->with('category')->get()
            ->keyBy('category_id');

        $actuals = Transaction::query()
            ->spending()
            ->inPeriod($start, $end)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, SUM(amount) AS spent')
            ->groupBy('category_id')
            ->pluck('spent', 'category_id');

        $categoryIds = $budgets->keys()->merge($actuals->keys())->unique();

        if ($categoryIds->isEmpty()) {
            return collect();
        }

        $categories = Category::whereIn('id', $categoryIds)->get()->keyBy('id');
        $elapsed = $this->monthProgress($month);

        return $categoryIds
            ->map(function ($categoryId) use ($budgets, $actuals, $categories, $elapsed) {
                $budget = $budgets->get($categoryId);
                $limit = $budget ? (string) $budget->amount : null;
                $spent = $this->decimal($actuals->get($categoryId) ?? 0);

                $remaining = $limit !== null ? bcsub($limit, $spent, self::SCALE) : null;
                $usedPercent = ($limit !== null && bccomp($limit, '0', self::SCALE) === 1)
                    ? round((float) $spent / (float) $limit * 100, 1)
                    : null;

                return (object) [
                    'category' => $categories->get($categoryId),
                    'category_id' => $categoryId,
                    'budget' => $limit,
                    'spent' => $spent,
                    'remaining' => $remaining,
                    'used_percent' => $usedPercent,
                    'status' => $this->status($limit, $spent, $usedPercent, $elapsed),
                    // How far through the month we are, so "80% spent" can be
                    // read against "60% of the month gone" rather than in a vacuum.
                    'month_elapsed_percent' => $elapsed,
                ];
            })
            ->sortBy(fn (object $row) => $row->budget === null ? 1 : 0)
            ->values();
    }

    /**
     * @return array{budgeted: string, spent: string, remaining: string, unbudgeted: string}
     */
    public function totals(CarbonInterface $month): array
    {
        $rows = $this->comparison($month);

        $budgeted = $rows->reduce(
            fn (string $c, object $r) => $r->budget !== null ? bcadd($c, $r->budget, self::SCALE) : $c,
            '0.00'
        );

        $spentAgainstBudget = $rows->reduce(
            fn (string $c, object $r) => $r->budget !== null ? bcadd($c, $r->spent, self::SCALE) : $c,
            '0.00'
        );

        $unbudgeted = $rows->reduce(
            fn (string $c, object $r) => $r->budget === null ? bcadd($c, $r->spent, self::SCALE) : $c,
            '0.00'
        );

        return [
            'budgeted' => $budgeted,
            'spent' => $spentAgainstBudget,
            'remaining' => bcsub($budgeted, $spentAgainstBudget, self::SCALE),
            'unbudgeted' => $unbudgeted,
        ];
    }

    /**
     * A suggested monthly figure from what the household actually spends.
     *
     * Returns null when there is not enough history — deliberately. Inventing a
     * target from one month of data would dress a guess up as insight, and the
     * spec warns against imposing budgets before behaviour is understood.
     *
     * @return array{amount: ?string, months: int, average: ?string, reason: ?string}
     */
    public function suggest(Category $category, ?CarbonInterface $upTo = null): array
    {
        $upTo = ($upTo ?? now())->copy()->startOfMonth();
        $amounts = [];

        // Look at whole months only; the current partial month would drag any
        // average down for no good reason.
        for ($i = 1; $i <= 6; $i++) {
            $month = $upTo->copy()->subMonthsNoOverflow($i);

            $spent = $this->decimal(
                Transaction::query()
                    ->spending()
                    ->inPeriod($month->toDateString(), $month->copy()->endOfMonth()->toDateString())
                    ->where('category_id', $category->id)
                    ->sum('amount')
            );

            if (bccomp($spent, '0', self::SCALE) === 1) {
                $amounts[] = $spent;
            }
        }

        $months = count($amounts);

        if ($months < self::MONTHS_FOR_SUGGESTION) {
            return [
                'amount' => null,
                'months' => $months,
                'average' => null,
                'reason' => "Only {$months} month(s) of spending recorded for this category. "
                    ."A suggestion needs at least ".self::MONTHS_FOR_SUGGESTION
                    .' so it reflects a habit rather than one unusual month.',
            ];
        }

        $total = array_reduce($amounts, fn ($c, $a) => bcadd($c, $a, self::SCALE), '0.00');
        $average = bcdiv($total, (string) $months, self::SCALE);

        return [
            'amount' => $average,
            'months' => $months,
            'average' => $average,
            'reason' => null,
        ];
    }

    /**
     * Categories spending far more this month than they usually do.
     *
     * Compares against the same category's own recent average rather than any
     * fixed threshold, so a household that always spends heavily on rent is not
     * warned about rent every month. Needs history to mean anything, so it
     * returns nothing until there is some.
     *
     * @return Collection<int, object>
     */
    public function anomalies(CarbonInterface $month, float $sensitivity = 1.5): Collection
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $thisMonth = Transaction::query()
            ->spending()
            ->inPeriod($start, $end)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, SUM(amount) AS spent')
            ->groupBy('category_id')
            ->pluck('spent', 'category_id');

        if ($thisMonth->isEmpty()) {
            return collect();
        }

        $categories = Category::whereIn('id', $thisMonth->keys())->get()->keyBy('id');

        return $thisMonth
            ->map(function ($spent, $categoryId) use ($month, $categories, $sensitivity) {
                $history = $this->recentAverage($categoryId, $month);

                if ($history['months'] < 2) {
                    return null;
                }

                $spent = $this->decimal($spent);
                $average = $history['average'];

                if (bccomp($average, '0', self::SCALE) !== 1) {
                    return null;
                }

                $ratio = (float) $spent / (float) $average;

                if ($ratio < $sensitivity) {
                    return null;
                }

                return (object) [
                    'category' => $categories->get($categoryId),
                    'category_id' => $categoryId,
                    'spent' => $spent,
                    'usual' => $average,
                    'ratio' => round($ratio, 1),
                    'extra' => bcsub($spent, $average, self::SCALE),
                    'months' => $history['months'],
                ];
            })
            ->filter()
            ->sortByDesc('ratio')
            ->values();
    }

    /** @return array{average: string, months: int} */
    private function recentAverage(int $categoryId, CarbonInterface $before, int $lookback = 3): array
    {
        $amounts = [];

        for ($i = 1; $i <= $lookback; $i++) {
            $month = $before->copy()->startOfMonth()->subMonthsNoOverflow($i);

            $spent = $this->decimal(
                Transaction::query()
                    ->spending()
                    ->inPeriod($month->toDateString(), $month->copy()->endOfMonth()->toDateString())
                    ->where('category_id', $categoryId)
                    ->sum('amount')
            );

            if (bccomp($spent, '0', self::SCALE) === 1) {
                $amounts[] = $spent;
            }
        }

        if ($amounts === []) {
            return ['average' => '0.00', 'months' => 0];
        }

        $total = array_reduce($amounts, fn ($c, $a) => bcadd($c, $a, self::SCALE), '0.00');

        return [
            'average' => bcdiv($total, (string) count($amounts), self::SCALE),
            'months' => count($amounts),
        ];
    }

    /** How many whole months of spending history exist at all. */
    public function monthsOfHistory(): int
    {
        $first = Transaction::query()->spending()->min('transaction_date');

        if ($first === null) {
            return 0;
        }

        return (int) Carbon::parse($first)->startOfMonth()->diffInMonths(now()->startOfMonth());
    }

    public function hasEnoughHistory(): bool
    {
        return $this->monthsOfHistory() >= self::MONTHS_FOR_SUGGESTION;
    }

    /** Percentage of the month elapsed, so pace can be judged fairly. */
    private function monthProgress(CarbonInterface $month): float
    {
        $start = $month->copy()->startOfMonth();

        if (! now()->between($start, $month->copy()->endOfMonth())) {
            return now()->greaterThan($month->copy()->endOfMonth()) ? 100.0 : 0.0;
        }

        return round((now()->day / $start->daysInMonth) * 100, 1);
    }

    /**
     * Over, close, or on track — judged against how much of the month is left,
     * so spending 70% of a budget on the 5th is flagged while the same figure on
     * the 25th is not.
     */
    private function status(?string $limit, string $spent, ?float $usedPercent, float $elapsed): string
    {
        if ($limit === null) {
            return 'unbudgeted';
        }

        if (bccomp($spent, $limit, self::SCALE) === 1) {
            return 'over';
        }

        if ($usedPercent === null) {
            return 'on_track';
        }

        // Ahead of the calendar by a clear margin, not just a rounding wobble.
        if ($elapsed > 0 && $usedPercent > $elapsed + 20) {
            return 'ahead_of_pace';
        }

        return $usedPercent >= 90 ? 'close' : 'on_track';
    }

    private function decimal(mixed $value): string
    {
        return bcadd((string) ($value ?? '0'), '0', self::SCALE);
    }
}
