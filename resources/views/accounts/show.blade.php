@extends('layouts.app')

@section('title', $account->name)
@section('heading', $account->name)
@section('subheading', $account->type->label()
    . ($account->institution ? ' · ' . $account->institution : '')
    . ($account->owner ? ' · ' . $account->owner->name : ''))

@section('actions')
    <a href="{{ route('accounts.edit', $account) }}" class="btn btn-sm btn-outline-secondary">Edit account</a>
@endsection

@section('content')

{{-- Rule 8: a balance must be explainable as opening balance plus its ledger. --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-sm-4">
                <div class="stat-label">Opening balance</div>
                <div class="h5 mb-0 money">@inr($account->opening_balance)</div>
                <div class="small text-body-secondary">on {{ $account->opening_balance_date->format('d M Y') }}</div>
            </div>
            <div class="col-sm-4">
                <div class="stat-label">Since then</div>
                <div class="h5 mb-0 money">
                    @inr(bcsub($derivedBalance, $account->opening_balance, 2))
                </div>
                <div class="small text-body-secondary">net of all entries</div>
            </div>
            <div class="col-sm-4">
                <div class="stat-label">{{ $account->isLiability() ? 'Currently owed' : 'Current balance' }}</div>
                <div class="h5 mb-0 money {{ $account->isLiability() ? 'money-neg' : '' }}">@inr($derivedBalance)</div>
                @if (bccomp($derivedBalance, (string) $account->cached_balance, 2) !== 0)
                    <div class="small text-danger">
                        Stored balance says @inr($account->cached_balance) — run "Check balances".
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Ledger</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Details</th>
                    <th>Type</th>
                    <th class="text-end">In</th>
                    <th class="text-end">Out</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($transactions as $t)
                <tr>
                    <td class="text-nowrap">{{ $t->transaction_date->format('d M') }}</td>
                    <td>
                        <a href="{{ route('transactions.show', $t) }}" class="text-decoration-none">
                            {{ $t->description ?: $t->merchant?->name ?: $t->type->label() }}
                        </a>
                        @if ($t->category)
                            <div class="small text-body-secondary">{{ $t->category->name }}</div>
                        @endif
                    </td>
                    <td><span class="badge text-bg-light border">{{ $t->type->label() }}</span></td>
                    <td class="text-end money money-pos">
                        {{ $t->balance_effect === \App\Enums\BalanceEffect::Increase ? \App\Support\Money::inr($t->amount) : '' }}
                    </td>
                    <td class="text-end money money-neg">
                        {{ $t->balance_effect === \App\Enums\BalanceEffect::Decrease ? \App\Support\Money::inr($t->amount) : '' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-state">No entries on this account yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($transactions->hasPages())
        <div class="card-footer bg-white">{{ $transactions->links() }}</div>
    @endif
</div>

@endsection
