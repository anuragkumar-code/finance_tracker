@extends('layouts.app')

@section('title', 'New entry')
@section('heading', $type === \App\Enums\TransactionType::Transfer ? 'Move money' : 'Record income')
@section('subheading', $type === \App\Enums\TransactionType::Transfer
    ? 'Between accounts you already own — this is not spending'
    : 'Money coming in')

@section('content')
<div class="row">
    <div class="col-lg-8 col-xl-6">

        <ul class="nav nav-pills mb-3">
            <li class="nav-item">
                <a class="nav-link {{ $type === \App\Enums\TransactionType::Income ? 'active' : '' }}"
                   href="{{ route('transactions.create', ['type' => 'income']) }}">Income</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $type === \App\Enums\TransactionType::Transfer ? 'active' : '' }}"
                   href="{{ route('transactions.create', ['type' => 'transfer']) }}">Transfer</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('quick-entry') }}">Spending</a>
            </li>
        </ul>

        @if ($type === \App\Enums\TransactionType::Transfer)
            <form method="POST" action="{{ route('transactions.transfer.store') }}">
                @csrf
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-info small">
                            Moving money between your own accounts does not make the household poorer,
                            so transfers never appear in spending totals.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="from_account_id" class="form-label">From</label>
                                <select name="from_account_id" id="from_account_id" class="form-select" required>
                                    <option value="">Choose…</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}" @selected(old('from_account_id') == $account->id)>
                                            {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="to_account_id" class="form-label">To</label>
                                <select name="to_account_id" id="to_account_id" class="form-select" required>
                                    <option value="">Choose…</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}" @selected(old('to_account_id') == $account->id)>
                                            {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="amount" class="form-label">Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="text" inputmode="decimal" name="amount" id="amount"
                                           class="form-control money" value="{{ old('amount') }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="transaction_date" class="form-label">Date</label>
                                <input type="date" name="transaction_date" id="transaction_date" class="form-control"
                                       value="{{ old('transaction_date', now()->toDateString()) }}" required>
                            </div>
                            <div class="col-12">
                                <label for="description" class="form-label">Note <span class="text-body-secondary">(optional)</span></label>
                                <input type="text" name="description" id="description" class="form-control"
                                       value="{{ old('description') }}" placeholder="Moved savings across">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex gap-2">
                        <button class="btn btn-primary">Record transfer</button>
                        <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div>
            </form>
        @else
            <form method="POST" action="{{ route('transactions.income.store') }}">
                @csrf
                <div class="card">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="amount" class="form-label">Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="text" inputmode="decimal" name="amount" id="amount"
                                           class="form-control money" value="{{ old('amount') }}" required autofocus>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="account_id" class="form-label">Into</label>
                                <select name="account_id" id="account_id" class="form-select" required>
                                    <option value="">Choose…</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}" @selected(old('account_id') == $account->id)>
                                            {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="category_id" class="form-label">Kind</label>
                                <select name="category_id" id="category_id" class="form-select">
                                    <option value="">—</option>
                                    @foreach ($incomeCategories as $category)
                                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="transaction_date" class="form-label">Date</label>
                                <input type="date" name="transaction_date" id="transaction_date" class="form-control"
                                       value="{{ old('transaction_date', now()->toDateString()) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="beneficiary_id" class="form-label">Earned by <span class="text-body-secondary">(optional)</span></label>
                                <select name="beneficiary_id" id="beneficiary_id" class="form-select">
                                    <option value="">—</option>
                                    @foreach ($beneficiaries as $person)
                                        <option value="{{ $person->id }}" @selected(old('beneficiary_id') == $person->id)>
                                            {{ $person->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="description" class="form-label">Note <span class="text-body-secondary">(optional)</span></label>
                                <input type="text" name="description" id="description" class="form-control"
                                       value="{{ old('description') }}" placeholder="September salary">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex gap-2">
                        <button class="btn btn-primary">Record income</button>
                        <a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div>
            </form>
        @endif

    </div>
</div>
@endsection
