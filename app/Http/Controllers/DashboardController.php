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
    ) {}

    public function index(Request $request): View
    {
        $month = $request->date('month') ?? now()->startOfMonth();
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        return view('dashboard', [
            'month' => $month,
            'start' => $start,
            'end' => $end,

            'income' => $this->reports->totalIncome($start, $end),
            'spending' => $this->reports->totalSpending($start, $end),
            'cardSpending' => $this->reports->creditCardSpending($start, $end),
            'netCashMovement' => $this->reports->netCashMovement($start, $end),
            'spendableCash' => $this->reports->spendableCash(),
            'netWorth' => $this->netWorth->summary(),

            'byCategory' => $this->reports->byCategory($start, $end),
            'byPlanned' => $this->reports->groupedBy('planned_status', $start, $end),
            'byPayer' => $this->reports->groupedBy('payer_id', $start, $end),

            'accounts' => Account::query()->active()->orderBy('type')->orderBy('name')->get(),

            // Card bills coming due are the household's most immediate
            // commitment, so they sit on the dashboard rather than behind a tab.
            'cardDues' => $this->cards->upcomingDues(30),
            'cardsOwed' => CreditCard::with('account')->get()->reduce(
                fn (string $carry, CreditCard $card) => bcadd($carry, $card->outstanding(), 2),
                '0.00'
            ),

            // What is left after everything already committed (spec section 21).
            'reality' => $this->upcoming->financialReality(30),
            'obligations' => $this->upcoming->forNextDays(30)->take(6),

            // EMIs are counted inside "spent" by household choice, so the
            // debt-repayment portion is shown alongside it rather than hidden.
            'debtRepaid' => $this->loans->paidBetween($start, $end),

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
}
