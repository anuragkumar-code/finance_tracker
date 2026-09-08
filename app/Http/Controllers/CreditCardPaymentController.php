<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCardPaymentRequest;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Models\CreditCardStatement;
use App\Services\CreditCardService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CreditCardPaymentController extends Controller
{
    public function __construct(
        private readonly CreditCardService $cards,
    ) {}

    public function create(Request $request, CreditCard $creditCard): View
    {
        $statement = $request->filled('statement')
            ? CreditCardStatement::where('credit_card_id', $creditCard->id)->find($request->integer('statement'))
            : $creditCard->statements()->whereNot('status', 'paid')->orderBy('due_date')->first();

        return view('credit-cards.payments.create', [
            'card' => $creditCard->load('account'),
            'statement' => $statement,
            'statements' => $creditCard->statements()->whereNot('status', 'paid')->get(),
            'sourceAccounts' => Account::query()->active()->assets()->orderBy('name')->get(),
            'suggestedAmount' => $statement?->balanceRemaining() ?? $creditCard->outstanding(),
        ]);
    }

    public function store(StoreCardPaymentRequest $request, CreditCard $creditCard): RedirectResponse
    {
        $data = $request->validated();

        $statement = isset($data['statement_id'])
            ? CreditCardStatement::find($data['statement_id'])
            : null;

        try {
            $this->cards->pay(
                $creditCard,
                Account::findOrFail($data['source_account_id']),
                $data['amount'],
                Carbon::parse($data['payment_date']),
                $statement,
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['source_account_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('credit-cards.show', $creditCard)
            ->with('status', 'Payment recorded. Your bank balance and the amount owed both went down — '
                .'this was not counted as new spending.');
    }

    public function void(Request $request, CreditCard $creditCard, CreditCardPayment $payment): RedirectResponse
    {
        abort_unless($payment->credit_card_id === $creditCard->id, 404);

        $data = $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
        ], [
            'void_reason.required' => 'Please say why this payment is being removed.',
        ]);

        $this->cards->voidPayment($payment, $data['void_reason']);

        return redirect()
            ->route('credit-cards.show', $creditCard)
            ->with('status', 'Payment voided. The amount owed has been restored.');
    }
}
