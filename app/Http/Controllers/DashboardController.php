<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Services\CreditCardService;
use App\Services\LoanService;
use App\Services\Reporting\SpendingReportService;
use App\Services\Reporting\UpcomingObligationsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly SpendingReportService $reports,
        private readonly CreditCardService $cards,
        private readonly UpcomingObligationsService $upcoming,
        private readonly LoanService $loans,
        private readonly \App\Services\Reporting\NetWorthService $netWorth,
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

        $income = $this->reports->totalIncome($start, $end);
        $spending = $this->reports->totalSpending($start, $end);
        $cardSpending = $this->reports->creditCardSpending($start, $end);

        return view('dashboard', [
            'month' => $month,
            'start' => $start,
            'end' => $end,

            'series' => $series,
            'previousLabel' => $previous->format('M'),

            // Percentage change against last month, or null where last month
            // was zero — "up from nothing" is not a percentage.
            'incomeDelta' => $this->change($this->reports->totalIncome($prevStart, $prevEnd), $income),
            'spendingDelta' => $this->change($this->reports->totalSpending($prevStart, $prevEnd), $spending),
            'cardDelta' => $this->change($this->reports->creditCardSpending($prevStart, $prevEnd), $cardSpending),

            'income' => $income,
            'spending' => $spending,
            'cardSpending' => $cardSpending,
            'netCashMovement' => $this->reports->netCashMovement($start, $end),
            'spendableCash' => $this->reports->spendableCash(),
            'netWorth' => $this->netWorth->summary(),

            'byCategory' => $this->reports->byCategory($start, $end),
            'byPlanned' => $this->reports->groupedBy('planned_status', $start, $end),
            'byPayer' => $this->reports->groupedBy('payer_id', $start, $end),

            'accounts' => Account::query()->active()->counted()->orderBy('type')->orderBy('name')->get(),

            // Card bills coming due are the household's most immediate
            // commitment, so they sit on the dashboard rather than behind a tab.
            'cardDues' => $this->cards->upcomingDues(30),
            'cardsOwed' => CreditCard::with('account')->get()->reduce(
                fn (string $carry, CreditCard $card) => bcadd($carry, $card->outstanding(), 2),
                '0.00'
            ),

            // What is left after everything already committed (spec section 21).
            'reality' => $this->upcoming->financialReality(30),

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
