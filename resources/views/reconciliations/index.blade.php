@extends('layouts.app')

@section('title', 'Reconcile')
@section('heading', 'Check against reality')
@section('subheading', 'Compare what the app says with what your bank actually shows')

@section('actions')
    <a href="{{ route('export.transactions') }}" class="btn btn-sm btn-outline-secondary">Export CSV</a>
@endsection

@section('content')

@if ($openGaps->isNotEmpty())
    <div class="alert alert-warning">
        <strong>{{ $openGaps->count() }} account(s) did not match when last checked.</strong>
        <div class="small mt-1">
            A gap almost always means an entry is missing, not that the app is wrong.
            Look for the missing transaction first — adjusting should be the last resort.
        </div>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">Where each account stands</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th class="text-end">App says</th>
                            <th>Last checked</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>
                                <a href="{{ route('accounts.show', $row->account) }}" class="text-decoration-none">
                                    {{ $row->account->name }}
                                </a>
                                <div class="small text-body-secondary">{{ $row->account->type->label() }}</div>
                            </td>
                            <td class="text-end money {{ $row->account->isLiability() ? 'money-neg' : '' }}">
                                @inr($row->system_balance)
                            </td>
                            <td class="small">
                                @if ($row->last)
                                    {{ $row->last->reconciliation_date->format('d M Y') }}
                                    <div class="text-body-secondary">
                                        {{ $row->days_since === 0 ? 'today' : $row->days_since . ' days ago' }}
                                    </div>
                                @else
                                    <span class="text-body-secondary">never</span>
                                @endif
                            </td>
                            <td>
                                @if ($row->last)
                                    <span class="badge text-bg-{{ $row->last->badgeClass() }}">
                                        {{ $row->last->statusLabel() }}
                                    </span>
                                @else
                                    <span class="badge text-bg-secondary">not checked</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">History</div>
            <div class="card-body p-0">
                @forelse ($recent as $r)
                    <div class="border-bottom p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="fw-medium">{{ $r->account->name }}</span>
                                <span class="badge text-bg-{{ $r->badgeClass() }}">{{ $r->statusLabel() }}</span>
                                <div class="small text-body-secondary">
                                    {{ $r->reconciliation_date->format('d M Y') }} ·
                                    bank said <span class="money">@inr($r->actual_balance)</span>,
                                    app said <span class="money">@inr($r->system_balance)</span>
                                </div>
                                @if ($r->note)
                                    <div class="small mt-1">{{ $r->note }}</div>
                                @endif
                            </div>
                            <div class="text-end">
                                <div class="money {{ $r->matched() ? 'text-body-secondary' : 'money-neg' }}">
                                    {{ $r->matched() ? 'matched' : \App\Support\Money::inr($r->difference) }}
                                </div>
                                @if ($r->needsAttention())
                                    <button class="btn btn-sm btn-outline-warning mt-1"
                                            data-bs-toggle="modal" data-bs-target="#adjust{{ $r->id }}">
                                        Adjust
                                    </button>
                                @elseif ($r->adjustment)
                                    <a href="{{ route('transactions.show', $r->adjustment) }}"
                                       class="small text-decoration-none">view adjustment</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        Nothing checked yet. Pick an account, type in the balance your bank shows,
                        and the app will tell you whether it agrees.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Check an account</div>
            <form method="POST" action="{{ route('reconciliations.store') }}" class="card-body">
                @csrf
                <div class="mb-3">
                    <label for="account_id" class="form-label">Account</label>
                    <select name="account_id" id="account_id" class="form-select" required>
                        <option value="">Choose…</option>
                        @foreach ($rows as $row)
                            <option value="{{ $row->account->id }}">
                                {{ $row->account->name }} (app: {{ \App\Support\Money::inr($row->system_balance) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="actual_balance" class="form-label">What your bank shows</label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="text" inputmode="decimal" name="actual_balance" id="actual_balance"
                               class="form-control money @error('actual_balance') is-invalid @enderror"
                               value="{{ old('actual_balance') }}" required>
                        @error('actual_balance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text">
                        For a credit card, enter what you owe as a positive number.
                    </div>
                </div>
                <div class="mb-3">
                    <label for="reconciliation_date" class="form-label">As of</label>
                    <input type="date" name="reconciliation_date" id="reconciliation_date" class="form-control"
                           value="{{ old('reconciliation_date', now()->toDateString()) }}" required>
                </div>
                <div class="mb-3">
                    <label for="note" class="form-label">Note <span class="text-body-secondary">(optional)</span></label>
                    <input type="text" name="note" id="note" class="form-control" value="{{ old('note') }}">
                </div>
                <button class="btn btn-primary w-100">Compare</button>
            </form>
        </div>

        <div class="card mb-3">
            <div class="card-header">Backup</div>
            <div class="card-body">
                <p class="small text-body-secondary">
                    Everything stays on this machine. These files are for your own backup, or for
                    opening the ledger in a spreadsheet.
                </p>
                <div class="d-grid gap-2">
                    <a href="{{ route('export.transactions') }}" class="btn btn-outline-secondary btn-sm">
                        Export all transactions (CSV)
                    </a>
                    <a href="{{ route('export.accounts') }}" class="btn btn-outline-secondary btn-sm">
                        Export account balances (CSV)
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body small text-body-secondary">
                <strong class="text-body">Why the app will not just fix itself.</strong>
                When the numbers disagree, it records the gap instead of quietly changing its own
                balance. A balance you can trace back to real entries is worth more than one that
                always looks right. Adjust only after you have looked for the missing entry.
            </div>
        </div>
    </div>
</div>

@foreach ($recent as $r)
    @continue(! $r->needsAttention())
    <div class="modal fade" id="adjust{{ $r->id }}" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('reconciliations.adjust', $r) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Adjust {{ $r->account->name }}?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small">
                        This posts a visible entry of
                        <strong class="money">@inr($r->difference)</strong>
                        so the app matches your bank. It appears in the account history like any
                        other transaction, and is not counted as spending.
                    </p>
                    <div class="alert alert-warning small mb-3">
                        Before adjusting: is there an entry you forgot to record? That is the usual
                        cause, and recording it is better than papering over it.
                    </div>
                    <label for="reason{{ $r->id }}" class="form-label">Why do they differ?</label>
                    <input type="text" name="reason" id="reason{{ $r->id }}" class="form-control"
                           placeholder="Bank charge I had not recorded" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-warning">Post adjustment</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

@endsection
