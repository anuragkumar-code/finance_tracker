@extends('layouts.app')

@section('title', 'Assets')
@section('heading', 'What you own')
@section('subheading', 'Land, vehicles and anything else outside your accounts')

@section('content')

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Valued assets</div>
            <div class="stat-value money money-pos">@inr($netWorth['asset_value'])</div>
            @if ($netWorth['unvalued_assets'] > 0)
                <div class="small text-body-secondary mt-1">
                    {{ $netWorth['unvalued_assets'] }} asset(s) have no value recorded
                </div>
            @endif
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">In accounts</div>
            <div class="stat-value money">@inr($netWorth['account_assets'])</div>
            <div class="small text-body-secondary mt-1">bank, cash &amp; investments</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Net worth</div>
            <div class="stat-value money {{ bccomp($netWorth['net_worth'], '0', 2) === -1 ? 'money-neg' : '' }}">
                @inr($netWorth['net_worth'])
            </div>
            <div class="small text-body-secondary mt-1">
                after @inr($netWorth['liabilities']) owed
            </div>
        </div></div>
    </div>
</div>

@if ($netWorth['unvalued_assets'] > 0)
    {{-- An incomplete picture is only safe if it says so. --}}
    <div class="alert alert-secondary small">
        Net worth counts every rupee you owe, but only assets you have put a value on.
        With {{ $netWorth['unvalued_assets'] }} asset(s) unvalued, the figure above is
        more pessimistic than your real position. Add a value whenever you want it to
        count — leaving it blank is fine too.
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Assets</div>
            <div class="card-body p-0">
                @forelse ($assets as $asset)
                    <div class="border-bottom p-3 {{ $asset->is_active ? '' : 'opacity-50' }}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="fw-medium">{{ $asset->name }}</span>
                                <span class="badge text-bg-light border">{{ $asset->typeLabel() }}</span>
                                @if ($asset->owner)
                                    <span class="badge text-bg-light border">{{ $asset->owner->name }}</span>
                                @endif
                                <div class="small text-body-secondary">
                                    @if ($asset->linkedLoan)
                                        Bought with {{ $asset->linkedLoan->name }} —
                                        <span class="money">@inr($asset->linkedLoan->remainingAmount())</span> still owed
                                    @elseif ($asset->acquired_on)
                                        Acquired {{ $asset->acquired_on->format('M Y') }}
                                    @else
                                        No loan linked
                                    @endif
                                </div>
                            </div>
                            <div class="text-end">
                                @if ($asset->hasValue())
                                    <div class="money fw-semibold">@inr($asset->current_value)</div>
                                    @if ($asset->valued_on)
                                        <div class="small text-body-secondary">
                                            valued {{ $asset->valued_on->format('M Y') }}
                                        </div>
                                    @endif
                                @else
                                    <div class="text-body-secondary small">no value set</div>
                                @endif
                                <button class="btn btn-sm btn-link text-decoration-none p-0"
                                        data-bs-toggle="collapse" data-bs-target="#asset{{ $asset->id }}">
                                    Edit
                                </button>
                            </div>
                        </div>

                        <div class="collapse mt-3" id="asset{{ $asset->id }}">
                            <form method="POST" action="{{ route('assets.update', $asset) }}"
                                  class="row g-2 border-top pt-3">
                                @csrf @method('PUT')
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Name</label>
                                    <input type="text" name="name" value="{{ $asset->name }}"
                                           class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Type</label>
                                    <select name="type" class="form-select form-select-sm">
                                        @foreach ($types as $value => $label)
                                            <option value="{{ $value }}" @selected($asset->type === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Value (optional)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">₹</span>
                                        <input type="text" inputmode="decimal" name="current_value"
                                               value="{{ $asset->current_value }}" class="form-control money">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Whose</label>
                                    <select name="owner_id" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        @foreach ($owners as $owner)
                                            <option value="{{ $owner->id }}" @selected($asset->owner_id == $owner->id)>
                                                {{ $owner->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Bought with loan</label>
                                    <select name="linked_loan_id" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        @foreach ($loans as $loan)
                                            <option value="{{ $loan->id }}" @selected($asset->linked_loan_id == $loan->id)>
                                                {{ $loan->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Acquired</label>
                                    <input type="date" name="acquired_on"
                                           value="{{ optional($asset->acquired_on)->toDateString() }}"
                                           class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                               id="aactive{{ $asset->id }}" @checked($asset->is_active)>
                                        <label class="form-check-label small" for="aactive{{ $asset->id }}">Active</label>
                                    </div>
                                </div>
                                <div class="col-md-3 d-flex align-items-end gap-1">
                                    <button class="btn btn-sm btn-primary">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        Nothing recorded yet. If you have a loan against something — land, a
                        vehicle — adding it here shows what the debt bought.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Add an asset</div>
            <form method="POST" action="{{ route('assets.store') }}" class="card-body">
                @csrf
                <div class="mb-3">
                    <label for="a_name" class="form-label">What is it</label>
                    <input type="text" name="name" id="a_name" class="form-control"
                           placeholder="Land at ..." required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="a_type" class="form-label">Type</label>
                        <select name="type" id="a_type" class="form-select">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label for="a_owner" class="form-label">Whose</label>
                        <select name="owner_id" id="a_owner" class="form-select">
                            <option value="">—</option>
                            @foreach ($owners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="a_value" class="form-label">
                        Value <span class="text-body-secondary">(optional)</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="text" inputmode="decimal" name="current_value" id="a_value"
                               class="form-control money">
                    </div>
                    <div class="form-text">
                        Leave blank if you would rather not put a number on it. The asset is
                        still recorded; it just will not count towards net worth.
                    </div>
                </div>
                <div class="mb-3">
                    <label for="a_loan" class="form-label">Bought with loan <span class="text-body-secondary">(optional)</span></label>
                    <select name="linked_loan_id" id="a_loan" class="form-select">
                        <option value="">—</option>
                        @foreach ($loans as $loan)
                            <option value="{{ $loan->id }}">{{ $loan->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="a_acquired" class="form-label">Acquired <span class="text-body-secondary">(optional)</span></label>
                    <input type="date" name="acquired_on" id="a_acquired" class="form-control">
                </div>
                <button class="btn btn-primary w-100">Add asset</button>
            </form>
        </div>
    </div>
</div>

@endsection
