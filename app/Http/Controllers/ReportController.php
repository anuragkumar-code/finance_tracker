<?php

namespace App\Http\Controllers;

use App\Models\CreditCard;
use App\Models\Loan;
use App\Services\CreditCardService;
use App\Services\Reporting\NetWorthService;
use App\Services\Reporting\SpendingReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The reports layer (spec section 19).
 *
 * Every figure here comes from SpendingReportService or NetWorthService, never
 * from a query written inline — that is what stops a report disagreeing with the
 * dashboard. Every summary number links into the transaction list filtered the
 * same way, so any figure can be opened up and checked (spec section 27).
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly SpendingReportService $reports,
        private readonly NetWorthService $netWorth,
        private readonly CreditCardService $cards,
    ) {}

    /** One month, cut every way the household asked for. */
    public function index(Request $request): View
    {
        $month = $request->date('month') ?? now()->startOfMonth();
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $spending = $this->reports->totalSpending($start, $end);

        return view('reports.index', [
            'month' => $month,
            'start' => $start,
            'end' => $end,

            'spending' => $spending,
            'invested' => $this->reports->totalInvested($start, $end),
            'debtRepayment' => $this->reports->debtRepayment($start, $end),
            'cardSpending' => $this->reports->creditCardSpending($start, $end),

            'weekly' => $this->reports->weekly($month),
            'byCategory' => $this->reports->byCategory($start, $end),
            'bySubcategory' => $this->reports->bySubcategory($start, $end),
            'byMerchant' => $this->reports->byMerchant($start, $end),
            'byMerchantGroup' => $this->reports->byMerchantGroup($start, $end),
            'byPayer' => $this->reports->groupedBy('payer_id', $start, $end),
            'byBeneficiary' => $this->reports->groupedBy('beneficiary_id', $start, $end),
            'byAccount' => $this->reports->groupedBy('account_id', $start, $end),
            'byPlanned' => $this->reports->groupedBy('planned_status', $start, $end),
            'byPurpose' => $this->reports->groupedBy('purpose', $start, $end),
            'byChannel' => $this->reports->byChannel($start, $end),
            'byPaymentMode' => $this->reports->byPaymentMode($start, $end),
            'online' => $this->reports->onlineSpending($start, $end),
        ]);
    }

    /**
     * Two months, and what actually changed between them.
     *
     * A month still in progress is compared over the same number of days by
     * default — eight days of one month against a whole other one is not a
     * comparison, it is an illusion.
     */
    public function compare(Request $request): View
    {
        $left = $request->date('left') ?? now()->startOfMonth();
        $right = $request->date('right') ?? $left->copy()->subMonthNoOverflow();

        $left = $left->startOfMonth();
        $right = $right->startOfMonth();

        $isLeftRunning = $left->isSameMonth(now());
        $window = $request->query('window', $isLeftRunning ? 'aligned' : 'full');
        $window = $window === 'aligned' ? 'aligned' : 'full';

        // Day-aligned means "the first N days of each", where N is how far the
        // running month has got.
        $alignedDays = $isLeftRunning ? now()->day : $left->daysInMonth;

        $periods = [
            'left' => $this->windowFor($left, $window, $alignedDays),
            'right' => $this->windowFor($right, $window, $alignedDays),
        ];

        $ledgerStart = $this->reports->ledgerStartsOn();

        return view('reports.compare', [
            'left' => $left,
            'right' => $right,
            'window' => $window,
            'alignedDays' => $alignedDays,
            'periods' => $periods,
            'ledgerStart' => $ledgerStart,
            'comparable' => $ledgerStart !== null && $ledgerStart <= $periods['right'][0],

            'totals' => [
                'left' => $this->reports->totalSpending(...$periods['left']),
                'right' => $this->reports->totalSpending(...$periods['right']),
            ],
            'splits' => [
                'left' => $this->reports->committedVsDiscretionary(...$periods['left']),
                'right' => $this->reports->committedVsDiscretionary(...$periods['right']),
            ],
            'rows' => $this->reports->categoryMovers(
                ...[...$periods['left'], ...$periods['right']], limit: 100
            ),
            'modes' => $this->paymentModeMovers($periods),
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function windowFor(\Carbon\CarbonInterface $month, string $window, int $alignedDays): array
    {
        $start = $month->copy()->startOfMonth();

        $end = $window === 'aligned'
            ? $start->copy()->addDays(min($alignedDays, $start->daysInMonth) - 1)
            : $start->copy()->endOfMonth();

        return [$start->toDateString(), $end->toDateString()];
    }

    /** The same movement view, for how the spending was paid for. */
    private function paymentModeMovers(array $periods): \Illuminate\Support\Collection
    {
        $now = $this->reports->byPaymentMode(...$periods['left'])->keyBy('key');
        $before = $this->reports->byPaymentMode(...$periods['right'])->keyBy('key');

        return $now->keys()->merge($before->keys())->unique()
            ->map(fn ($key) => (object) [
                'key' => $key,
                'label' => $now[$key]->label ?? $before[$key]->label,
                'now' => $now[$key]->amount ?? '0.00',
                'before' => $before[$key]->amount ?? '0.00',
                'delta' => bcsub($now[$key]->amount ?? '0', $before[$key]->amount ?? '0', 2),
            ])
            ->sortByDesc(fn ($row) => (float) $row->now)
            ->values();
    }

    /** Several months side by side, to show behaviour rather than a snapshot (spec section 20). */
    public function trends(Request $request): View
    {
        $months = (int) $request->integer('months', 6);
        $months = in_array($months, [3, 6, 12], true) ? $months : 6;

        return view('reports.trends', [
            'months' => $months,
            'series' => $this->reports->monthlySeries($months),
            'grid' => $this->reports->categoryByMonth($months),
        ]);
    }

    /** Balance-sheet view: what is owned, what is owed (spec section 19J). */
    public function netWorth(): View
    {
        return view('reports.net-worth', [
            'summary' => $this->netWorth->summary(),
            'accounts' => \App\Models\Account::query()->active()->counted()->with('owner')
                ->orderBy('type')->orderBy('name')->get(),
            'assets' => \App\Models\Asset::query()->active()->with('linkedLoan')->orderBy('name')->get(),
            'loans' => Loan::query()->active()->with('owner')->orderBy('name')->get(),
        ]);
    }

    /** Card behaviour: what was charged versus what was paid (spec section 19H). */
    public function creditCards(Request $request): View
    {
        $month = $request->date('month') ?? now()->startOfMonth();
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $cards = CreditCard::with('account.owner')->orderBy('card_name')->get()
            ->map(fn (CreditCard $card) => (object) [
                'card' => $card,
                'spending' => $this->cards->spendingInPeriod($card, $start, $end),
                'payments' => $this->cards->paymentsInPeriod($card, $start, $end),
                'outstanding' => $card->outstanding(),
                'available' => $card->availableCredit(),
                'utilisation' => $card->utilisation(),
            ]);

        return view('reports.credit-cards', [
            'month' => $month,
            'start' => $start,
            'end' => $end,
            'rows' => $cards,
            'series' => $this->reports->monthlySeries(6),
        ]);
    }
}
