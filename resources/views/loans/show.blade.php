@extends('layouts.app')

@section('title', $loan->name)
@section('heading', $loan->name)
@section('subheading', ($loan->lender ?: 'Loan')
    . ($loan->owner ? ' · ' . $loan->owner->name : '')
    . ' · ' . $loan->total_months . ' months from ' . $loan->start_date->format('M Y'))

@section('actions')
    <a href="{{ route('loans.edit', $loan) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
@endsection

@section('content')

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Monthly EMI</div>
            <div class="stat-value money">@inr($loan->emi_amount)</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Paid so far</div>
            <div class="stat-value money money-pos">@inrc($loan->paidAmount())</div>
            <div class="small text-body-secondary">{{ $loan->paidCount() }} of {{ $loan->total_months }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Still to pay</div>
            <div class="stat-value money money-neg">@inrc($loan->remainingAmount())</div>
            <div class="small text-body-secondary">{{ $loan->remainingCount() }} EMIs left</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Finishes</div>
            <div class="stat-value">{{ $loan->end_date->format('M Y') }}</div>
            <span class="badge text-bg-{{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
        </div></div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between small mb-1">
            <span class="text-body-secondary">{{ $loan->progressPercent() }}% of the tenure done</span>
            <span class="text-body-secondary money">
                @inr($loan->totalPayable()) total over {{ $loan->total_months }} months
            </span>
        </div>
        <div class="progress" style="height:8px;">
            <div class="progress-bar bg-success" style="width: {{ $loan->progressPercent() }}%"></div>
        </div>
        <div class="small text-body-secondary mt-2">
            "Still to pay" is cash across the remaining EMIs, which includes future interest —
            it is not the principal outstanding your lender would quote for a foreclosure.
        </div>
    </div>
</div>

@if ($overdue->isNotEmpty())
    <div class="alert alert-warning">
        <strong>{{ $overdue->count() }} EMI(s) are past their due date and not yet recorded.</strong>
        <div class="small">Confirm them below once the money has actually left your account.</div>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Coming up</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Due</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Record</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($upcoming as $instalment)
                        <tr class="{{ $instalment->isOverdue() ? 'table-warning' : '' }}">
                            <td class="text-body-secondary">{{ $instalment->period_number }}</td>
                            <td>
                                {{ $instalment->due_date->format('d M Y') }}
                                @if ($instalment->isOverdue())
                                    <span class="badge text-bg-danger ms-1">overdue</span>
                                @endif
                            </td>
                            <td class="text-end money">@inr($instalment->amount)</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal" data-bs-target="#pay{{ $instalment->id }}">
                                    Mark paid
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">Every instalment has been recorded.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Recorded</div>
            <div class="card-body p-0">
                @if ($recent->isEmpty())
                    <div class="empty-state">Nothing recorded yet.</div>
                @else
                    <table class="table table-sm mb-0 align-middle">
                        <tbody>
                        @foreach ($recent as $instalment)
                            <tr>
                                <td class="text-body-secondary">{{ $instalment->period_number }}</td>
                                <td>
                                    {{ $instalment->due_date->format('M Y') }}
                                    @unless ($instalment->transaction_id)
                                        <div class="small text-body-secondary">before tracking</div>
                                    @endunless
                                </td>
                                <td class="text-end money">@inr($instalment->amount)</td>
                                <td class="text-end">
                                    @if ($instalment->transaction_id)
                                        <button class="btn btn-sm btn-link text-danger text-decoration-none p-0"
                                                data-bs-toggle="modal" data-bs-target="#unpay{{ $instalment->id }}">
                                            Undo
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>

@foreach ($upcoming as $instalment)
    <div class="modal fade" id="pay{{ $instalment->id }}" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('loans.instalments.pay', [$loan, $instalment]) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Record EMI {{ $instalment->period_number }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-body-secondary">
                        This will record the payment leaving your account and count it in this
                        month's spending.
                    </p>
                    <div class="mb-3">
                        <label for="acc{{ $instalment->id }}" class="form-label">Paid from</label>
                        <select name="account_id" id="acc{{ $instalment->id }}" class="form-select" required>
                            <option value="">Choose…</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected($loan->payment_account_id == $account->id)>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label for="date{{ $instalment->id }}" class="form-label">Paid on</label>
                            <input type="date" name="payment_date" id="date{{ $instalment->id }}"
                                   class="form-control" value="{{ $instalment->due_date->toDateString() }}" required>
                        </div>
                        <div class="col-6">
                            <label for="amt{{ $instalment->id }}" class="form-label">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="text" inputmode="decimal" name="amount" id="amt{{ $instalment->id }}"
                                       class="form-control money" value="{{ $instalment->amount }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Record EMI</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

@foreach ($recent->where('transaction_id', '!=', null) as $instalment)
    <div class="modal fade" id="unpay{{ $instalment->id }}" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('loans.instalments.unpay', [$loan, $instalment]) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Undo EMI {{ $instalment->period_number }}?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small">
                        The entry will be voided and your balance restored. The instalment goes back
                        to being due.
                    </p>
                    <label for="ureason{{ $instalment->id }}" class="form-label">Why?</label>
                    <input type="text" name="void_reason" id="ureason{{ $instalment->id }}"
                           class="form-control" placeholder="Recorded by mistake" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger">Undo</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

@endsection
