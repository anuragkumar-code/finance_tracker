@extends('layouts.app')

@section('title', 'Accounts')
@section('heading', 'Accounts')
@section('subheading', 'What you hold and what you owe')

@section('actions')
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('accounts.recalculate') }}">
            @csrf
            <button class="btn btn-sm btn-outline-secondary"
                    title="Re-derive every balance from the underlying transactions">
                Check balances
            </button>
        </form>
        <a href="{{ route('accounts.create') }}" class="btn btn-sm btn-primary text-nowrap">+ Add account</a>
    </div>
@endsection

@section('content')

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">You own</div>
            <div class="stat-value money money-pos">@inr($netWorth['assets'])</div>
            <div class="small text-body-secondary mt-1">
                @inr($netWorth['bank_cash']) available
                @if (bccomp($netWorth['set_aside'], '0', 2) === 1)
                    · @inr($netWorth['set_aside']) set aside
                @endif
            </div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">You owe</div>
            <div class="stat-value money money-neg">@inr($netWorth['liabilities'])</div>
            <div class="small text-body-secondary mt-1">
                @inr($netWorth['card_debt']) cards ·
                @inr($netWorth['loan_debt']) loans
            </div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Net worth</div>
            <div class="stat-value money {{ bccomp($netWorth['net_worth'], '0', 2) === -1 ? 'money-neg' : '' }}">
                @inr($netWorth['net_worth'])
            </div>
            {{-- Loan debt is remaining EMIs, so it includes future interest
                 (design doc D11). Say so rather than let the figure mislead. --}}
            <div class="small text-body-secondary mt-1">
                loan debt counts all remaining EMIs, interest included
            </div>
        </div></div>
    </div>
</div>

<div class="mb-3 d-flex flex-wrap gap-1 align-items-center">
    <span class="stat-label me-2">Whose</span>
    <a href="{{ route('accounts.index') }}"
       class="btn btn-sm {{ $selectedOwner ? 'btn-outline-secondary' : 'btn-secondary' }}">Everyone</a>
    @foreach ($owners as $owner)
        <a href="{{ route('accounts.index', ['owner' => $owner->id]) }}"
           class="btn btn-sm {{ $selectedOwner === $owner->id ? 'btn-secondary' : 'btn-outline-secondary' }}">
            {{ $owner->name }}
        </a>
    @endforeach
</div>

@forelse ($accounts as $typeLabel => $group)
    <div class="card mb-3">
        <div class="card-header">{{ $typeLabel }}</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Whose</th>
                        <th>Institution</th>
                        <th class="text-end">Opening</th>
                        <th class="text-end">Current</th>
                        <th style="width:1%"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($group as $account)
                    <tr class="{{ $account->is_active ? '' : 'opacity-50' }}">
                        <td>
                            <a href="{{ route('accounts.show', $account) }}" class="text-decoration-none fw-medium">
                                {{ $account->name }}
                            </a>
                            @if ($account->is_set_aside)
                                <span class="badge text-bg-info ms-1"
                                      title="Not counted as money available to spend">
                                    {{ $account->set_aside_reason ?: 'set aside' }}
                                </span>
                            @endif
                            @unless ($account->is_active)
                                <span class="badge text-bg-secondary ms-1">inactive</span>
                            @endunless
                            <div class="small text-body-secondary">
                                since {{ $account->opening_balance_date->format('d M Y') }}
                            </div>
                        </td>
                        <td>
                            @if ($account->owner)
                                <span class="badge text-bg-light border">{{ $account->owner->name }}</span>
                            @else
                                <span class="text-body-secondary small">—</span>
                            @endif
                        </td>
                        <td class="text-body-secondary">{{ $account->institution ?: '—' }}</td>
                        <td class="text-end money text-body-secondary">@inr($account->opening_balance)</td>
                        <td class="text-end money {{ $account->isLiability() ? 'money-neg' : '' }}">
                            @inr($account->cached_balance)
                            @if ($account->isLiability())
                                <div class="small text-body-secondary">owed</div>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('accounts.edit', $account) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="card">
        <div class="card-body empty-state">
            @if ($selectedOwner)
                <h2 class="h5">No accounts for this person</h2>
                <p class="mb-3">
                    <a href="{{ route('accounts.index') }}">Show everyone's accounts</a>
                </p>
            @else
            <h2 class="h5">No accounts yet</h2>
            <p class="mb-3">
                Add each bank account, cash holding and credit card, along with what it holds today.
                Opening balances record your starting position without inventing fake transactions.
            </p>
            <a href="{{ route('accounts.create') }}" class="btn btn-primary">Add your first account</a>
            @endif
        </div>
    </div>
@endforelse

@endsection
