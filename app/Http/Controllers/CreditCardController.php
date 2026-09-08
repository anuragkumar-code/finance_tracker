<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\StoreCreditCardRequest;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Person;
use App\Services\AccountBalanceService;
use App\Services\CreditCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CreditCardController extends Controller
{
    public function __construct(
        private readonly CreditCardService $cards,
        private readonly AccountBalanceService $balances,
    ) {}

    public function index(): View
    {
        $cards = CreditCard::with('account.owner')->orderBy('card_name')->get();

        return view('credit-cards.index', [
            'cards' => $cards,
            'totalOwed' => $cards->reduce(
                fn (string $carry, CreditCard $card) => bcadd($carry, $card->outstanding(), 2),
                '0.00'
            ),
            'upcomingDues' => $this->cards->upcomingDues(30),
        ]);
    }

    public function create(): View
    {
        return view('credit-cards.create', [
            'owners' => Person::query()->active()->payers()->ordered()->get(),
        ]);
    }

    /**
     * A card is an account plus card details, created together so the household
     * never has to understand that distinction.
     */
    public function store(StoreCreditCardRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $card = DB::transaction(function () use ($data) {
            $account = Account::create([
                'name' => $data['card_name'],
                'type' => AccountType::CreditCard,
                'institution' => $data['institution'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
                'opening_balance' => $data['opening_balance'],
                'opening_balance_date' => $data['opening_balance_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->balances->recalculate($account);

            return CreditCard::create([
                'account_id' => $account->id,
                'card_name' => $data['card_name'],
                'credit_limit' => $data['credit_limit'],
                'statement_day' => $data['statement_day'],
                'payment_due_day' => $data['payment_due_day'],
                'annual_fee' => $data['annual_fee'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return redirect()
            ->route('credit-cards.show', $card)
            ->with('status', "\"{$card->card_name}\" added. Record purchases against it as they happen.");
    }

    public function show(CreditCard $creditCard): View
    {
        $creditCard->load(['account', 'statements.payments']);

        $cycleStart = $creditCard->currentCycleStart();

        return view('credit-cards.show', [
            'card' => $creditCard,
            'cycleStart' => $cycleStart,
            'unbilled' => $this->cards->unbilledTransactions($creditCard),
            'statements' => $creditCard->statements()->with('payments')->limit(12)->get(),
            'payments' => $creditCard->payments()->with('sourceAccount')->limit(10)->get(),
            'monthSpending' => $this->cards->spendingInPeriod(
                $creditCard, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()
            ),
            'monthPayments' => $this->cards->paymentsInPeriod(
                $creditCard, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()
            ),
        ]);
    }

    public function edit(CreditCard $creditCard): View
    {
        return view('credit-cards.edit', [
            'card' => $creditCard->load('account.owner'),
            'owners' => Person::query()->active()->payers()->ordered()->get(),
        ]);
    }

    public function update(StoreCreditCardRequest $request, CreditCard $creditCard): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($creditCard, $data, $request) {
            $creditCard->account->update([
                'name' => $data['card_name'],
                'institution' => $data['institution'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
                'opening_balance' => $data['opening_balance'],
                'opening_balance_date' => $data['opening_balance_date'],
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->balances->recalculate($creditCard->account);

            $creditCard->update([
                'card_name' => $data['card_name'],
                'credit_limit' => $data['credit_limit'],
                'statement_day' => $data['statement_day'],
                'payment_due_day' => $data['payment_due_day'],
                'annual_fee' => $data['annual_fee'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return redirect()
            ->route('credit-cards.show', $creditCard)
            ->with('status', 'Card updated.');
    }
}
