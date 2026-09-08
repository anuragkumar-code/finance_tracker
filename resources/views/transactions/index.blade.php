@extends('layouts.app')

@section('title', 'Transactions')
@section('heading', 'Transactions')
@section('subheading', $transactions->total() . ' entries · ' . \App\Support\Money::inr($filteredSpending) . ' of spending')

@section('actions')
    <div class="d-flex gap-2">
        <a href="{{ route('transactions.create', ['type' => 'income']) }}" class="btn btn-sm btn-outline-secondary">+ Income</a>
        <a href="{{ route('transactions.create', ['type' => 'transfer']) }}" class="btn btn-sm btn-outline-secondary">+ Transfer</a>
        <a href="{{ route('quick-entry') }}" class="btn btn-sm btn-primary text-nowrap">+ Spend</a>
    </div>
@endsection

@section('content')

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="start" value="{{ $filters['start'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="end" value="{{ $filters['end'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Account</label>
                <select name="account_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected(($filters['account_id'] ?? '') == $account->id)>
                            {{ $account->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>
                            {{ $category->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Search</label>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Description, merchant…">
            </div>

            <div class="col-12">
                <a class="small text-decoration-none" data-bs-toggle="collapse" href="#moreFilters" role="button">
                    More filters
                </a>
            </div>

            <div class="collapse {{ collect($filters)->only(['payer_id','beneficiary_id','planned_status','purpose','merchant_id','min_amount','max_amount'])->filter()->isNotEmpty() ? 'show' : '' }}"
                 id="moreFilters">
                <div class="row g-2 mt-0">
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Paid by</label>
                        <select name="payer_id" class="form-select form-select-sm">
                            <option value="">Anyone</option>
                            @foreach ($people as $person)
                                <option value="{{ $person->id }}" @selected(($filters['payer_id'] ?? '') == $person->id)>
                                    {{ $person->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">For</label>
                        <select name="beneficiary_id" class="form-select form-select-sm">
                            <option value="">Anyone</option>
                            @foreach ($people as $person)
                                <option value="{{ $person->id }}" @selected(($filters['beneficiary_id'] ?? '') == $person->id)>
                                    {{ $person->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Planned</label>
                        <select name="planned_status" class="form-select form-select-sm">
                            <option value="">Any</option>
                            @foreach ($plannedStatuses as $status)
                                <option value="{{ $status->value }}" @selected(($filters['planned_status'] ?? '') === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Purpose</label>
                        <select name="purpose" class="form-select form-select-sm">
                            <option value="">Any</option>
                            @foreach ($purposes as $purpose)
                                <option value="{{ $purpose->value }}" @selected(($filters['purpose'] ?? '') === $purpose->value)>
                                    {{ $purpose->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Min ₹</label>
                        <input type="number" step="0.01" name="min_amount" value="{{ $filters['min_amount'] ?? '' }}"
                               class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Max ₹</label>
                        <input type="number" step="0.01" name="max_amount" value="{{ $filters['max_amount'] ?? '' }}"
                               class="form-control form-control-sm">
                    </div>
                </div>
            </div>

            <div class="col-12 d-flex gap-2 mt-2">
                <button class="btn btn-sm btn-primary">Apply</button>
                <a href="{{ route('transactions.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Details</th>
                    <th>Category</th>
                    <th>Account</th>
                    <th>Who</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($transactions as $t)
                <tr>
                    <td class="text-nowrap">{{ $t->transaction_date->format('d M Y') }}</td>
                    <td>
                        <a href="{{ route('transactions.show', $t) }}" class="text-decoration-none fw-medium">
                            {{ $t->description ?: $t->merchant?->name ?: $t->type->label() }}
                        </a>
                        <div class="small">
                            <span class="badge text-bg-light border">{{ $t->type->label() }}</span>
                            @if ($t->planned_status)
                                <span class="badge text-bg-{{ $t->planned_status->badgeClass() }}">
                                    {{ $t->planned_status->label() }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="text-body-secondary">
                        {{ $t->category?->name ?: '—' }}
                        @if ($t->subcategory)
                            <div class="small">{{ $t->subcategory->name }}</div>
                        @endif
                    </td>
                    <td class="text-body-secondary">{{ $t->account->name }}</td>
                    <td class="text-body-secondary small">
                        @if ($t->payer)<div>by {{ $t->payer->name }}</div>@endif
                        @if ($t->beneficiary)<div>for {{ $t->beneficiary->name }}</div>@endif
                    </td>
                    <td class="text-end money {{ $t->type->countsAsSpending() ? '' : 'text-body-secondary' }}">
                        @inr($t->amount)
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">
                        No transactions match these filters.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($transactions->hasPages())
        <div class="card-footer bg-white">{{ $transactions->links() }}</div>
    @endif
</div>

@endsection
