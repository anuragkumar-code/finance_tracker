<?php

namespace App\Http\Controllers;

use App\Models\CreditCard;
use App\Models\CreditCardStatement;
use App\Services\CreditCardService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CreditCardStatementController extends Controller
{
    public function __construct(
        private readonly CreditCardService $cards,
    ) {}

    /**
     * Statement generation is a deliberate act, not automatic: the household
     * enters the figure their bank actually issued, and the app shows what it
     * grouped, so a mismatch surfaces instead of hiding.
     */
    public function create(Request $request, CreditCard $creditCard): View
    {
        $periodEnd = $request->filled('period_end')
            ? Carbon::parse($request->date('period_end'))
            : $creditCard->currentCycleStart()->copy()->subDay()->addMonthNoOverflow();

        $periodStart = $request->filled('period_start')
            ? Carbon::parse($request->date('period_start'))
            : $periodEnd->copy()->subMonthNoOverflow()->addDay();

        return view('credit-cards.statements.create', [
            'card' => $creditCard,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'dueDate' => $creditCard->dueDateFor($periodEnd),
            'preview' => $this->cards->previewStatementAmount($creditCard, $periodStart, $periodEnd),
            'transactions' => $this->cards->unbilledTransactions($creditCard, $periodEnd),
        ]);
    }

    public function store(Request $request, CreditCard $creditCard): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'statement_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'statement_amount' => ['required', 'numeric', 'min:0'],
            'minimum_due' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $statement = $this->cards->generateStatement(
                $creditCard,
                Carbon::parse($data['period_start']),
                Carbon::parse($data['period_end']),
                Carbon::parse($data['statement_date']),
                Carbon::parse($data['due_date']),
                $data['statement_amount'],
                $data['minimum_due'] ?? null,
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['period_start' => $e->getMessage()]);
        }

        $discrepancy = $this->cards->statementDiscrepancy($statement);

        $message = 'Statement recorded. It groups purchases already entered — nothing was added to your spending.';

        if (bccomp($discrepancy, '0', 2) !== 0) {
            return redirect()->route('credit-cards.statements.show', [$creditCard, $statement])
                ->with('warning', 'Statement recorded, but it is '.\App\Support\Money::inr($discrepancy)
                    .' away from the purchases recorded for that period. Something may be missing.');
        }

        return redirect()
            ->route('credit-cards.statements.show', [$creditCard, $statement])
            ->with('status', $message);
    }

    public function show(CreditCard $creditCard, CreditCardStatement $statement): View
    {
        abort_unless($statement->credit_card_id === $creditCard->id, 404);

        $statement->load(['items.transaction.category', 'items.transaction.merchant', 'payments.sourceAccount']);

        return view('credit-cards.statements.show', [
            'card' => $creditCard,
            'statement' => $statement,
            'discrepancy' => $this->cards->statementDiscrepancy($statement),
        ]);
    }

    /** Rebuild the link between a statement and its transactions after an edit. */
    public function regenerate(CreditCard $creditCard, CreditCardStatement $statement): RedirectResponse
    {
        abort_unless($statement->credit_card_id === $creditCard->id, 404);

        $this->cards->regenerateItems($statement);

        return back()->with('status', 'Statement re-linked to its transactions. The statement amount is unchanged.');
    }
}
