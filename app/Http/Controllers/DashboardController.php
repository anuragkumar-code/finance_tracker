<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Services\LoanService;
use App\Services\Reporting\SpendingReportService;
use App\Services\Reporting\UpcomingObligationsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly SpendingReportService $reports,
        private readonly UpcomingObligationsService $upcoming,
        private readonly LoanService $loans,
        private readonly \App\Services\Reporting\BudgetService $budgets,
        private readonly \App\Services\PersonBalanceService $friends,
    ) {}

    public function index(Request $request): View
    {
        $month = $request->date('month') ?? now()->startOfMonth();
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        // Six months ending on the month being viewed. This drives both the
        // cash-flow chart and the sparkline behind each headline figure, so
        // they are guaranteed to be telling the same story.
        $series = $this->reports->monthlySeries(6, $month);

        // The month before the one on screen, for the "vs last month" deltas.
        // A figure with nothing to compare it to cannot be judged.
        $previous = $month->copy()->subMonthNoOverflow();
        $prevStart = $previous->copy()->startOfMonth()->toDateString();
        $prevEnd = $previous->copy()->endOfMonth()->toDateString();

        // A month in progress is compared day-for-day: eight days of October
        // against a whole September would read as a collapse in spending.
        // Past months are compared in full, where that fairness is automatic.
        $isCurrentMonth = $month->isSameMonth(now());
        $dayOfMonth = $isCurrentMonth ? now()->day : $month->copy()->endOfMonth()->day;
        $comparedEnd = $previous->copy()->startOfMonth()
            ->addDays(min($dayOfMonth, $previous->copy()->endOfMonth()->day) - 1)
            ->toDateString();

        $spentSoFar = $this->reports->totalSpending($start, $end);
        $spentByNowLastMonth = $this->reports->totalSpending($prevStart, $comparedEnd);

        // Comparing against a window the household was not yet recording in
        // would report every category as new. Say so rather than draw it.
        $ledgerStart = $this->reports->ledgerStartsOn();
        $comparable = $ledgerStart !== null && $ledgerStart <= $prevStart;

        $spending = $this->reports->totalSpending($start, $end);
        $invested = $this->reports->totalInvested($start, $end);
        $cardSpending = $this->reports->creditCardSpending($start, $end);

        return view('dashboard', [
            'month' => $month,
            'start' => $start,
            'end' => $end,

            'series' => $series,
            'previousLabel' => $previous->format('M'),

            // Percentage change against last month, or null where last month
            // was zero — "up from nothing" is not a percentage.
            'spendingDelta' => $this->change($this->reports->totalSpending($prevStart, $prevEnd), $spending),
            'cardDelta' => $this->change($this->reports->creditCardSpending($prevStart, $prevEnd), $cardSpending),

            'spending' => $spending,
            'invested' => $invested,
            'cardSpending' => $cardSpending,

            // Month on month, day-aligned while the month is still running.
            'isCurrentMonth' => $isCurrentMonth,
            'dayOfMonth' => $dayOfMonth,
            'comparable' => $comparable,
            'previousMonthLabel' => $previous->format('F'),
            'spentByNowLastMonth' => $spentByNowLastMonth,
            // Silent rather than wrong: with nothing recorded in the window
            // being compared against, a percentage would be invented.
            'spentSoFarDelta' => $comparable ? $this->change($spentByNowLastMonth, $spentSoFar) : null,
            'split' => $this->reports->committedVsDiscretionary($start, $end),
            'movers' => $comparable
                ? $this->reports->categoryMovers($start, $end, $prevStart, $comparedEnd)
                : collect(),

            // What is still promised to leave before the month is out. Shown
            // instead of a projection: extrapolating a run rate from a month
            // whose EMIs all land on the 1st invents a number.
            'committedRemaining' => $this->upcoming->totalFor(
                (int) max(0, now()->diffInDays($month->copy()->endOfMonth(), false))
            ),

            'byCategory' => $this->reports->byCategory($start, $end),
            'byPlanned' => $this->reports->groupedBy('planned_status', $start, $end),
            'byPayer' => $this->reports->groupedBy('payer_id', $start, $end),

            'hasAccounts' => Account::query()->active()->counted()->exists(),

            // How the month's spending was paid for. Not a balance — the split
            // between bank and card is the mode-of-payment question, which is
            // the one the household does want answered.
            'byPaymentMode' => $this->reports->byPaymentMode($start, $end),


            // What is already promised to leave over the next 30 days: EMIs,
            // card bills, rent, broadband. The outflow side of "what is coming".
            'committed' => $this->upcoming->totalFor(30),

            // Money friends still owe, or the household owes them. On the
            // dashboard because a repayment nobody is reminded of is one that
            // quietly never happens.
            'friendBalances' => $this->friends->summary()->take(5),
            'obligations' => $this->upcoming->forNextDays(30)->take(6),

            // EMIs are counted inside "spent" by household choice, so the
            // debt-repayment portion is shown alongside it rather than hidden.
            'debtRepaid' => $this->loans->paidBetween($start, $end),

            // Only the budgets actually in trouble; a full list belongs on its
            // own page rather than crowding the daily view.
            'budgetAlerts' => $this->budgets->comparison($month)
                ->filter(fn ($r) => in_array($r->status, ['over', 'ahead_of_pace'], true))
                ->take(4),
            'spendingAnomalies' => $this->budgets->anomalies($month)->take(3),

            'recent' => Transaction::query()
                ->with(['account', 'category', 'merchant', 'payer', 'beneficiary'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),

            'today' => Transaction::query()
                ->with(['account', 'category', 'merchant'])
                ->whereDate('transaction_date', today())
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    /**
     * Percentage change between two money figures.
     *
     * Returns null when there is nothing to compare against: a month that went
     * from ₹0 to ₹5,000 has not risen by any percentage, and showing "+100%"
     * or "+∞" there would be inventing a comparison the data cannot support.
     *
     * The division is deliberately the only place a money value becomes a
     * float — the result is a display percentage, never an amount, and never
     * feeds back into a balance.
     */
    private function change(string $before, string $after): ?float
    {
        if (bccomp($before, '0', 2) !== 1) {
            return null;
        }

        return round((((float) $after - (float) $before) / (float) $before) * 100, 1);
    }
}
