@extends('layouts.app')

@section('title', $card->card_name)
@section('heading', $card->card_name)
@section('subheading', ($card->account->institution ?: 'Credit card')
    . ($card->account->owner ? ' · ' . $card->account->owner->name . "'s card" : ''))

@section('actions')
    <div class="d-flex gap-2">
        <a href="{{ route('credit-cards.payments.create', $card) }}" class="btn btn-sm btn-primary">Pay bill</a>
        <a href="{{ route('credit-cards.statements.create', $card) }}" class="btn btn-sm btn-outline-secondary">Add statement</a>
        <a href="{{ route('credit-cards.edit', $card) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
    </div>
@endsection

@section('content')
@php($util = $card->utilisation())

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Owed now</div>
            <div class="stat-value money money-neg">@inr($card->outstanding())</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Available</div>
            <div class="stat-value money">@inr($card->availableCredit())</div>
            <div class="small text-body-secondary">of @inr($card->credit_limit) limit</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Spent this month</div>
            <div class="stat-value money">@inr($monthSpending)</div>
            <div class="small text-body-secondary">purchases</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Paid this month</div>
            <div class="stat-value money">@inr($monthPayments)</div>
            {{-- Kept deliberately separate from spending, per spec 19F. --}}
            <div class="small text-body-secondary">bill payments, not spending</div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Not yet billed</span>
                <span class="small text-body-secondary">since {{ $cycleStart->format('d M') }}</span>
            </div>
            <div class="card-body p-0">
                @if ($unbilled->isEmpty())
                    <div class="empty-state">
                        Nothing outstanding to bill.
                        <div class="small mt-1">Purchases appear here until a statement covers them.</div>
                    </div>
                @else
                    <table class="table table-sm mb-0 align-middle">
                        <tbody>
                        @foreach ($unbilled as $t)
                            <tr>
                                <td class="text-nowrap text-body-secondary">{{ $t->transaction_date->format('d M') }}</td>
                                <td>
                                    <a href="{{ route('transactions.show', $t) }}" class="text-decoration-none">
                                        {{ $t->description ?: $t->merchant?->name ?: 'Purchase' }}
                                    </a>
                                </td>
                                <td class="text-end money">
                                    {{ $t->balance_effect === \App\Enums\BalanceEffect::Decrease ? '−' : '' }}@inr($t->amount)
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="border-top">
                            <tr>
                                <th colspan="2" class="small">Unbilled total</th>
                                <th class="text-end money">
                                    @inr($unbilled->reduce(fn ($c, $t) => $t->balance_effect === \App\Enums\BalanceEffect::Increase
                                        ? bcadd($c, $t->amount, 2) : bcsub($c, $t->amount, 2), '0.00'))
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">Statements</div>
            <div class="card-body p-0">
                @if ($statements->isEmpty())
                    <div class="empty-state">
                        No statements recorded yet.
                        <div class="mt-2">
                            <a href="{{ route('credit-cards.statements.create', $card) }}"
                               class="btn btn-sm btn-outline-primary">Add your first statement</a>
                        </div>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Due</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Remaining</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($statements as $statement)
                                <tr>
                                    <td>
                                        <a href="{{ route('credit-cards.statements.show', [$card, $statement]) }}"
                                           class="text-decoration-none">
                                            {{ $statement->period_start->format('d M') }} –
                                            {{ $statement->period_end->format('d M Y') }}
                                        </a>
                                    </td>
                                    <td class="{{ $statement->isOverdue() ? 'text-danger fw-medium' : '' }}">
                                        {{ $statement->due_date->format('d M') }}
                                    </td>
                                    <td class="text-end money">@inr($statement->statement_amount)</td>
                                    <td class="text-end money">@inr($statement->balanceRemaining())</td>
                                    <td>
                                        <span class="badge text-bg-{{ $statement->status->badgeClass() }}">
                                            {{ $statement->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-body-secondary">{{ $util }}% of limit used</span>
                    <span class="money text-body-secondary">@inr($card->credit_limit) limit</span>
                </div>
                <div class="progress" style="height:8px;">
                    <div class="progress-bar {{ $util >= 70 ? 'bg-danger' : ($util >= 40 ? 'bg-warning' : 'bg-success') }}"
                         style="width: {{ min($util, 100) }}%"></div>
                </div>
                <div class="small text-body-secondary mt-2">
                    Bills on the {{ $card->statement_day }} of each month, payment due on the
                    {{ $card->payment_due_day }}.
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Recent payments</div>
            <div class="card-body p-0">
                @if ($payments->isEmpty())
                    <div class="empty-state">No bill payments recorded yet.</div>
                @else
                    <table class="table table-sm mb-0 align-middle">
                        <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="text-nowrap text-body-secondary">{{ $payment->payment_date->format('d M') }}</td>
                                <td class="small">from {{ $payment->sourceAccount->name }}</td>
                                <td class="text-end money">@inr($payment->amount)</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-link text-danger text-decoration-none p-0"
                                            data-bs-toggle="modal" data-bs-target="#voidPay{{ $payment->id }}">
                                        Void
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body small text-body-secondary">
                <div class="fw-semibold text-body mb-1">How this card is accounted for</div>
                Purchases count as spending on the day you make them. A statement just groups
                those purchases — it adds nothing. Paying the bill moves money out of your bank
                and reduces what you owe, and is never counted as spending again.
            </div>
        </div>
    </div>
</div>

@foreach ($payments as $payment)
    <div class="modal fade" id="voidPay{{ $payment->id }}" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('credit-cards.payments.void', [$card, $payment]) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Void this payment?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small">
                        The @inr($payment->amount) payment made on {{ $payment->payment_date->format('d M Y') }}
                        will be reversed: your bank balance goes back up and the amount owed is restored.
                    </p>
                    <label for="void_reason{{ $payment->id }}" class="form-label">Why?</label>
                    <input type="text" name="void_reason" id="void_reason{{ $payment->id }}"
                           class="form-control" placeholder="Payment bounced, entered twice, …" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger">Void payment</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

@endsection
