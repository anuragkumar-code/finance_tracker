@extends('layouts.app')

@section('title', 'Statement')
@section('heading', $card->card_name . ' statement')
@section('subheading', $statement->period_start->format('d M') . ' – ' . $statement->period_end->format('d M Y'))

@section('actions')
    <div class="d-flex gap-2">
        @unless ($statement->status->isSettled())
            <a href="{{ route('credit-cards.payments.create', ['creditCard' => $card, 'statement' => $statement->id]) }}"
               class="btn btn-sm btn-primary">Pay this bill</a>
        @endunless
        <a href="{{ route('credit-cards.show', $card) }}" class="btn btn-sm btn-outline-secondary">Back to card</a>
    </div>
@endsection

@section('content')

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Statement amount</div>
            <div class="stat-value money">@inr($statement->statement_amount)</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Paid</div>
            <div class="stat-value money money-pos">@inr($statement->amountPaid())</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Remaining</div>
            <div class="stat-value money">@inr($statement->balanceRemaining())</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Due by</div>
            <div class="stat-value {{ $statement->isOverdue() ? 'text-danger' : '' }}">
                {{ $statement->due_date->format('d M') }}
            </div>
            <span class="badge text-bg-{{ $statement->status->badgeClass() }}">
                {{ $statement->status->label() }}
            </span>
        </div></div>
    </div>
</div>

@if (bccomp($discrepancy, '0', 2) !== 0)
    {{-- Never silently reconciled away: a gap here is real information. --}}
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong>This statement is @inr($discrepancy) away from the purchases recorded for the period.</strong>
            <div class="small">
                Either a purchase is missing from the app, or something posted outside these dates.
                Compare the list below against your real statement.
            </div>
        </div>
        <form method="POST" action="{{ route('credit-cards.statements.regenerate', [$card, $statement]) }}">
            @csrf
            <button class="btn btn-sm btn-outline-dark text-nowrap">Re-link transactions</button>
        </form>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Purchases on this statement</span>
                <span class="small text-body-secondary">{{ $statement->items->count() }} items</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Details</th>
                            <th>Category</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($statement->items as $item)
                        @php($t = $item->transaction)
                        <tr>
                            <td class="text-nowrap">{{ $t?->transaction_date?->format('d M') ?? '—' }}</td>
                            <td>
                                @if ($t)
                                    <a href="{{ route('transactions.show', $t) }}" class="text-decoration-none">
                                        {{ $t->description ?: $t->merchant?->name ?: 'Purchase' }}
                                    </a>
                                @else
                                    <span class="text-body-secondary">Transaction removed</span>
                                @endif
                            </td>
                            <td class="text-body-secondary">{{ $t?->category?->name ?: '—' }}</td>
                            <td class="text-end money">
                                @inr($item->amount_snapshot)
                                @if ($t && bccomp((string) $item->amount_snapshot, (string) $t->amount, 2) !== 0)
                                    <div class="small text-warning-emphasis">now @inr($t->amount)</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">No purchases were grouped into this statement.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot class="border-top">
                        <tr>
                            <th colspan="3">Grouped total</th>
                            <th class="text-end money">@inr($statement->items->sum('amount_snapshot'))</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Payments against this bill</div>
            <div class="card-body p-0">
                @if ($statement->payments->isEmpty())
                    <div class="empty-state">Not paid yet.</div>
                @else
                    <table class="table table-sm mb-0">
                        <tbody>
                        @foreach ($statement->payments as $payment)
                            <tr>
                                <td class="text-nowrap text-body-secondary small">
                                    {{ $payment->payment_date->format('d M') }}
                                </td>
                                <td class="small">{{ $payment->sourceAccount->name }}</td>
                                <td class="text-end money">@inr($payment->amount)</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body small text-body-secondary">
                This statement created no new expense. Every purchase listed here was already
                counted as spending on the day it happened.
                @if ($statement->notes)
                    <div class="mt-2 text-body">{{ $statement->notes }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
