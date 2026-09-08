@extends('layouts.app')

@section('title', 'Recurring')
@section('heading', 'Recurring commitments')
@section('subheading', 'Rent, bills, subscriptions, family support — expected, not assumed')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">

        @if ($due->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header">Due now and soon</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Due</th>
                                <th>What</th>
                                <th class="text-end">Expected</th>
                                <th class="text-end">Did it happen?</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($due as $occurrence)
                            <tr class="{{ $occurrence->isOverdue() ? 'table-warning' : '' }}">
                                <td class="text-nowrap">
                                    {{ $occurrence->due_date->format('d M') }}
                                    @if ($occurrence->isOverdue())
                                        <div class="small text-danger">overdue</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $occurrence->recurringTransaction->name }}
                                    <div class="small text-body-secondary">
                                        {{ $occurrence->recurringTransaction->account->name }}
                                    </div>
                                </td>
                                <td class="text-end money">@inr($occurrence->amount)</td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#confirm{{ $occurrence->id }}">
                                            Confirm
                                        </button>
                                        <form method="POST"
                                              action="{{ route('recurring.occurrences.skip', $occurrence) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary">Skip</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white small text-body-secondary">
                    Nothing is recorded until you confirm it — the app never assumes a payment went out.
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">All commitments</div>
            <div class="card-body p-0">
                @forelse ($commitments as $commitment)
                    <div class="border-bottom p-3 {{ $commitment->is_active ? '' : 'opacity-50' }}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="fw-medium">{{ $commitment->name }}</span>
                                @unless ($commitment->is_active)
                                    <span class="badge text-bg-secondary">stopped</span>
                                @endunless
                                <div class="small text-body-secondary">
                                    {{ $commitment->frequency->label() }} ·
                                    {{ $commitment->account->name }}
                                    @if ($commitment->category) · {{ $commitment->category->name }} @endif
                                </div>
                                <div class="small text-body-secondary">
                                    next {{ $commitment->next_due_date->format('d M Y') }}
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="money fw-semibold">@inr($commitment->amount)</div>
                                <button class="btn btn-sm btn-link text-decoration-none p-0"
                                        data-bs-toggle="collapse" data-bs-target="#edit{{ $commitment->id }}">
                                    Edit
                                </button>
                            </div>
                        </div>

                        <div class="collapse mt-3" id="edit{{ $commitment->id }}">
                            <form method="POST" action="{{ route('recurring.update', $commitment) }}"
                                  class="row g-2 border-top pt-3">
                                @csrf @method('PUT')
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Name</label>
                                    <input type="text" name="name" value="{{ $commitment->name }}"
                                           class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Amount</label>
                                    <input type="text" name="amount" value="{{ $commitment->amount }}"
                                           class="form-control form-control-sm money" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">How often</label>
                                    <select name="frequency" class="form-select form-select-sm">
                                        @foreach ($frequencies as $frequency)
                                            <option value="{{ $frequency->value }}"
                                                    @selected($commitment->frequency === $frequency)>
                                                {{ $frequency->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Next due</label>
                                    <input type="date" name="next_due_date"
                                           value="{{ $commitment->next_due_date->toDateString() }}"
                                           class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Account</label>
                                    <select name="account_id" class="form-select form-select-sm" required>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}"
                                                    @selected($commitment->account_id == $account->id)>
                                                {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Category</label>
                                    <select name="category_id" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                    @selected($commitment->category_id == $category->id)>
                                                {{ $category->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Type</label>
                                    <select name="type" class="form-select form-select-sm">
                                        <option value="expense" @selected($commitment->type->value === 'expense')>Expense</option>
                                        <option value="income" @selected($commitment->type->value === 'income')>Income</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                               id="active{{ $commitment->id }}" @checked($commitment->is_active)>
                                        <label class="form-check-label small" for="active{{ $commitment->id }}">Active</label>
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end gap-1">
                                    <button class="btn btn-sm btn-primary">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        No commitments yet. Add rent, utilities, subscriptions or regular family
                        support so they show up in Upcoming before they hit your account.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Add a commitment</div>
            <form method="POST" action="{{ route('recurring.store') }}" class="card-body">
                @csrf
                <div class="mb-3">
                    <label for="r_name" class="form-label">What is it</label>
                    <input type="text" name="name" id="r_name" class="form-control"
                           placeholder="Rent" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="r_amount" class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="text" inputmode="decimal" name="amount" id="r_amount"
                                   class="form-control money" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <label for="r_type" class="form-label">Type</label>
                        <select name="type" id="r_type" class="form-select">
                            <option value="expense">Money out</option>
                            <option value="income">Money in</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="r_freq" class="form-label">How often</label>
                        <select name="frequency" id="r_freq" class="form-select">
                            @foreach ($frequencies as $frequency)
                                <option value="{{ $frequency->value }}" @selected($frequency->value === 'monthly')>
                                    {{ $frequency->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label for="r_next" class="form-label">Next due</label>
                        <input type="date" name="next_due_date" id="r_next" class="form-control"
                               value="{{ now()->toDateString() }}" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="r_account" class="form-label">Account</label>
                    <select name="account_id" id="r_account" class="form-select" required>
                        <option value="">Choose…</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="r_category" class="form-label">Category</label>
                    <select name="category_id" id="r_category" class="form-select">
                        <option value="">—</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="r_benef" class="form-label">For</label>
                        <select name="beneficiary_id" id="r_benef" class="form-select">
                            <option value="">—</option>
                            @foreach ($beneficiaries as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label for="r_end" class="form-label">Ends <span class="text-body-secondary">(optional)</span></label>
                        <input type="date" name="end_date" id="r_end" class="form-control">
                    </div>
                </div>
                <button class="btn btn-primary w-100">Add commitment</button>
            </form>
        </div>
    </div>
</div>

@foreach ($due as $occurrence)
    <div class="modal fade" id="confirm{{ $occurrence->id }}" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('recurring.occurrences.confirm', $occurrence) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Confirm {{ $occurrence->recurringTransaction->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-body-secondary">
                        This records the real transaction against
                        {{ $occurrence->recurringTransaction->account->name }}.
                        Adjust the amount if the actual bill differed.
                    </p>
                    <div class="row g-2">
                        <div class="col-6">
                            <label for="camt{{ $occurrence->id }}" class="form-label">Actual amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="text" inputmode="decimal" name="amount" id="camt{{ $occurrence->id }}"
                                       class="form-control money" value="{{ $occurrence->amount }}">
                            </div>
                        </div>
                        <div class="col-6">
                            <label for="cdate{{ $occurrence->id }}" class="form-label">Paid on</label>
                            <input type="date" name="paid_on" id="cdate{{ $occurrence->id }}" class="form-control"
                                   value="{{ $occurrence->due_date->toDateString() }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Record it</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

@endsection
