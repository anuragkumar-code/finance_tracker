@extends('layouts.app')

@section('title', 'Net worth')
@section('heading', 'What you own, what you owe')
@section('subheading', 'As of today')

@section('actions')
    <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary">Back to reports</a>
@endsection

@section('content')

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-4 align-items-center text-center text-md-start">
            <div class="col-md-4">
                <div class="stat-label">Own</div>
                <div class="h3 mb-0 money money-pos">@inr($summary['assets'])</div>
            </div>
            <div class="col-md-4">
                <div class="stat-label">Owe</div>
                <div class="h3 mb-0 money money-neg">@inr($summary['liabilities'])</div>
            </div>
            <div class="col-md-4">
                <div class="stat-label">Net worth</div>
                <div class="h2 mb-0 money {{ bccomp($summary['net_worth'], '0', 2) === -1 ? 'money-neg' : 'money-pos' }}">
                    @inr($summary['net_worth'])
                </div>
            </div>
        </div>
    </div>
</div>

@if ($summary['unvalued_assets'] > 0)
    {{-- The figure counts every rupee owed but only assets someone valued.
         Saying so is the difference between a caveat and a wrong number. --}}
    <div class="alert alert-warning">
        <strong>This figure is more pessimistic than your real position.</strong>
        <div class="small mt-1">
            Every rupee you owe is counted, but {{ $summary['unvalued_assets'] }} asset(s)
            have no value recorded, so they contribute nothing.
            <a href="{{ route('assets.index') }}">Add values</a> if you want them counted —
            leaving them blank is a fine choice too.
        </div>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">What you own</div>
            <table class="table table-sm mb-0 align-middle">
                <tbody>
                @foreach ($accounts->where('normal_balance', \App\Enums\NormalBalance::Asset) as $account)
                    <tr>
                        <td>
                            <a href="{{ route('accounts.show', $account) }}" class="text-decoration-none">
                                {{ $account->name }}
                            </a>
                            @if ($account->is_set_aside)
                                <span class="badge text-bg-info">{{ $account->set_aside_reason ?: 'set aside' }}</span>
                            @endif
                            <div class="small text-body-secondary">
                                {{ $account->type->label() }}@if ($account->owner) · {{ $account->owner->name }}@endif
                            </div>
                        </td>
                        <td class="text-end money">@inr($account->cached_balance)</td>
                    </tr>
                @endforeach

                @foreach ($assets as $asset)
                    <tr>
                        <td>
                            <a href="{{ route('assets.index') }}" class="text-decoration-none">{{ $asset->name }}</a>
                            <div class="small text-body-secondary">
                                {{ $asset->typeLabel() }}
                                @if (! $asset->hasValue()) · not valued @endif
                            </div>
                        </td>
                        <td class="text-end money {{ $asset->hasValue() ? '' : 'text-body-secondary' }}">
                            {{ $asset->hasValue() ? \App\Support\Money::inr($asset->current_value) : '—' }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="border-top">
                    <tr>
                        <th>Total</th>
                        <th class="text-end money">@inr($summary['assets'])</th>
                    </tr>
                    @if (bccomp($summary['set_aside'], '0', 2) === 1)
                        <tr>
                            <td class="small text-body-secondary border-0">
                                of which set aside, not available to spend
                            </td>
                            <td class="text-end money small text-body-secondary border-0">
                                @inr($summary['set_aside'])
                            </td>
                        </tr>
                    @endif
                </tfoot>
            </table>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">What you owe</div>
            <table class="table table-sm mb-0 align-middle">
                <tbody>
                @foreach ($accounts->where('normal_balance', \App\Enums\NormalBalance::Liability) as $account)
                    @continue(bccomp((string) $account->cached_balance, '0', 2) === 0)
                    <tr>
                        <td>
                            <a href="{{ route('accounts.show', $account) }}" class="text-decoration-none">
                                {{ $account->name }}
                            </a>
                            <div class="small text-body-secondary">
                                Credit card{{ $account->owner ? ' · '.$account->owner->name : '' }}
                            </div>
                        </td>
                        <td class="text-end money money-neg">@inr($account->cached_balance)</td>
                    </tr>
                @endforeach

                @foreach ($loans as $loan)
                    <tr>
                        <td>
                            <a href="{{ route('loans.show', $loan) }}" class="text-decoration-none">{{ $loan->name }}</a>
                            <div class="small text-body-secondary">
                                {{ $loan->remainingCount() }} EMIs left{{ $loan->owner ? ' · '.$loan->owner->name : '' }}
                            </div>
                        </td>
                        <td class="text-end money money-neg">@inr($loan->remainingAmount())</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="border-top">
                    <tr>
                        <th>Total</th>
                        <th class="text-end money money-neg">@inr($summary['liabilities'])</th>
                    </tr>
                    <tr>
                        <td class="small text-body-secondary border-0">
                            cards @inr($summary['card_debt']) · loans @inr($summary['loan_debt'])
                        </td>
                        <td class="border-0"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body small text-body-secondary">
        <strong class="text-body">How loan debt is counted here.</strong>
        Loans show the cash still to pay — remaining EMIs multiplied by the EMI amount — so the
        figure includes future interest. That is deliberately conservative: it is larger than the
        principal your lender would quote to close the loan early. Set-aside money is included in
        what you own, because you do own it; it is simply never treated as available to spend.
    </div>
</div>

@endsection
