@extends('layouts.app')

@section('title', 'Credit Cards')
@section('heading', 'Credit Cards')
@section('subheading', 'What you owe, and what is due next')

@section('actions')
    <a href="{{ route('credit-cards.create') }}" class="btn btn-sm btn-primary text-nowrap">+ Add card</a>
@endsection

@section('content')

@if ($cards->isEmpty())
    <div class="card">
        <div class="card-body empty-state">
            <h2 class="h5">No cards yet</h2>
            <p class="mb-3">
                Add a card with its limit, billing cycle and what you currently owe.
                Purchases get recorded against the card as they happen; paying the bill
                later is a separate step that will not be counted as spending twice.
            </p>
            <a href="{{ route('credit-cards.create') }}" class="btn btn-primary">Add your first card</a>
        </div>
    </div>
@else

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body">
                <div class="stat-label">Total owed across cards</div>
                <div class="stat-value money money-neg">@inr($totalOwed)</div>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body">
                <div class="stat-label">Due in the next 30 days</div>
                @if ($upcomingDues->isEmpty())
                    <div class="stat-value text-body-secondary">—</div>
                    <div class="small text-body-secondary">No statements awaiting payment.</div>
                @else
                    <div class="stat-value money">
                        @inr($upcomingDues->reduce(fn ($c, $s) => bcadd($c, $s->balanceRemaining(), 2), '0.00'))
                    </div>
                    <div class="small text-body-secondary">
                        @foreach ($upcomingDues as $due)
                            {{ $due->creditCard->card_name }} by {{ $due->due_date->format('d M') }}@if(! $loop->last), @endif
                        @endforeach
                    </div>
                @endif
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        @foreach ($cards as $card)
            @php($util = $card->utilisation())
            <div class="col-lg-6">
                <div class="card h-100 {{ $card->is_active ? '' : 'opacity-50' }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <a href="{{ route('credit-cards.show', $card) }}"
                                   class="h6 mb-0 d-block text-decoration-none">
                                    {{ $card->card_name }}
                                    @if ($card->account->owner)
                                        <span class="badge text-bg-light border fw-normal">
                                            {{ $card->account->owner->name }}
                                        </span>
                                    @endif
                                </a>
                                <div class="small text-body-secondary">
                                    {{ $card->account->institution ?: 'Credit card' }} ·
                                    bills on the {{ $card->statement_day }}, due on the {{ $card->payment_due_day }}
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="stat-label">Owed</div>
                                <div class="h5 mb-0 money money-neg">@inr($card->outstanding())</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-body-secondary">{{ $util }}% of limit used</span>
                            <span class="money text-body-secondary">
                                @inr($card->availableCredit()) available
                            </span>
                        </div>
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar {{ $util >= 70 ? 'bg-danger' : ($util >= 40 ? 'bg-warning' : 'bg-success') }}"
                                 style="width: {{ min($util, 100) }}%"></div>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <a href="{{ route('credit-cards.payments.create', $card) }}"
                               class="btn btn-sm btn-outline-primary">Pay bill</a>
                            <a href="{{ route('credit-cards.statements.create', $card) }}"
                               class="btn btn-sm btn-outline-secondary">Add statement</a>
                            <a href="{{ route('credit-cards.show', $card) }}"
                               class="btn btn-sm btn-link ms-auto text-decoration-none">Details</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@endsection
