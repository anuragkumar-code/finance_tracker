@extends('layouts.app')

@section('title', 'Transaction')
@section('heading', $transaction->description ?: $transaction->merchant?->name ?: $transaction->type->label())
@section('subheading', $transaction->transaction_date->format('d M Y'))

@section('actions')
    @if (! $transaction->trashed())
        <div class="d-flex gap-2">
            <a href="{{ route('transactions.edit', $transaction) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#voidModal">Void</button>
        </div>
    @endif
@endsection

@section('content')

@if ($transaction->trashed())
    <div class="alert alert-secondary d-flex justify-content-between align-items-center">
        <div>
            <strong>Voided.</strong>
            This entry no longer affects any balance.
            @if ($transaction->void_reason)
                <div class="small">Reason: {{ $transaction->void_reason }}</div>
            @endif
        </div>
        <form method="POST" action="{{ route('transactions.restore', $transaction->id) }}">
            @csrf
            <button class="btn btn-sm btn-outline-secondary">Restore</button>
        </form>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <div class="stat-label">{{ $transaction->type->label() }}</div>
                        <div class="display-6 money">@inr($transaction->amount)</div>
                    </div>
                    <div class="text-end">
                        <div class="stat-label">Account</div>
                        <a href="{{ route('accounts.show', $transaction->account) }}" class="text-decoration-none">
                            {{ $transaction->account->name }}
                        </a>
                        <div class="small text-body-secondary">
                            {{ $transaction->balance_effect === \App\Enums\BalanceEffect::Increase ? 'increased' : 'decreased' }}
                            by @inr($transaction->amount)
                        </div>
                    </div>
                </div>

                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-body-secondary fw-normal">Category</dt>
                    <dd class="col-sm-8">
                        {{ $transaction->category?->name ?: '—' }}
                        @if ($transaction->subcategory) &rsaquo; {{ $transaction->subcategory->name }} @endif
                    </dd>

                    <dt class="col-sm-4 text-body-secondary fw-normal">Merchant</dt>
                    <dd class="col-sm-8">{{ $transaction->merchant?->name ?: '—' }}</dd>

                    <dt class="col-sm-4 text-body-secondary fw-normal">Paid by</dt>
                    <dd class="col-sm-8">{{ $transaction->payer?->name ?: '—' }}</dd>

                    <dt class="col-sm-4 text-body-secondary fw-normal">For</dt>
                    <dd class="col-sm-8">{{ $transaction->beneficiary?->name ?: '—' }}</dd>

                    <dt class="col-sm-4 text-body-secondary fw-normal">Planned</dt>
                    <dd class="col-sm-8">
                        @if ($transaction->planned_status)
                            <span class="badge text-bg-{{ $transaction->planned_status->badgeClass() }}">
                                {{ $transaction->planned_status->label() }}
                            </span>
                        @else — @endif
                    </dd>

                    <dt class="col-sm-4 text-body-secondary fw-normal">Purpose</dt>
                    <dd class="col-sm-8">{{ $transaction->purpose?->label() ?: '—' }}</dd>

                    @if ($transaction->notes)
                        <dt class="col-sm-4 text-body-secondary fw-normal">Notes</dt>
                        <dd class="col-sm-8">{{ $transaction->notes }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        @if ($counterpart)
            <div class="card mb-3">
                <div class="card-header">The other side of this move</div>
                <div class="card-body">
                    <p class="small text-body-secondary">
                        This is a {{ strtolower($transaction->type->label()) }} between your own accounts, recorded as
                        two linked entries. It is not counted as spending.
                    </p>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <a href="{{ route('accounts.show', $counterpart->account) }}" class="text-decoration-none">
                                {{ $counterpart->account->name }}
                            </a>
                            <div class="small text-body-secondary">{{ $counterpart->leg_role->label() }}</div>
                        </div>
                        <div class="money">@inr($counterpart->amount)</div>
                    </div>
                </div>
            </div>
        @endif

        @if ($transaction->splits->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header">Split</div>
                <table class="table table-sm mb-0">
                    <tbody>
                    @foreach ($transaction->splits as $split)
                        <tr>
                            <td>{{ $split->category?->name ?: ($split->notes ?: 'Part') }}</td>
                            <td class="text-end money">@inr($split->amount)</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="card">
            <div class="card-body small text-body-secondary">
                <div>Recorded {{ $transaction->created_at->diffForHumans() }}</div>
                @if ($transaction->updated_at->ne($transaction->created_at))
                    <div>Last edited {{ $transaction->updated_at->diffForHumans() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

@unless ($transaction->trashed())
<div class="modal fade" id="voidModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('transactions.void', $transaction) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Void this entry?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small">
                    The entry will stop affecting balances but stays on record, so the history
                    remains complete.
                    @if ($counterpart)
                        Both sides of this move will be voided together.
                    @endif
                </p>
                <label for="void_reason" class="form-label">Why?</label>
                <input type="text" name="void_reason" id="void_reason" class="form-control"
                       placeholder="Entered twice, wrong amount, …" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger">Void entry</button>
            </div>
        </form>
    </div>
</div>
@endunless

@endsection
