@extends('layouts.app')

@section('title', 'Edit entry')
@section('heading', 'Edit entry')
@section('subheading', 'Balances are recalculated automatically when you save')

@section('content')
@php($isTransfer = $transaction->type === \App\Enums\TransactionType::Transfer)

<div class="row">
    <div class="col-lg-8 col-xl-6">
        <form method="POST" action="{{ route('transactions.update', $transaction) }}">
            @csrf
            @method('PUT')
            <div class="card">
                <div class="card-body">

                    @if ($isTransfer)
                        <div class="alert alert-info small">
                            Both sides of this transfer are updated together, so they can never disagree.
                        </div>

                        @php($fromLeg = $transaction->leg_role->isSource() ? $transaction : $counterpart)
                        @php($toLeg = $transaction->leg_role->isSource() ? $counterpart : $transaction)

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="from_account_id" class="form-label">From</label>
                                <select name="from_account_id" id="from_account_id" class="form-select" required>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}"
                                                @selected(old('from_account_id', $fromLeg?->account_id) == $account->id)>
                                            {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="to_account_id" class="form-label">To</label>
                                <select name="to_account_id" id="to_account_id" class="form-select" required>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}"
                                                @selected(old('to_account_id', $toLeg?->account_id) == $account->id)>
                                            {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @else
                        <div class="mb-3">
                            <label for="account_id" class="form-label">Account</label>
                            <select name="account_id" id="account_id" class="form-select" required>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}"
                                            @selected(old('account_id', $transaction->account_id) == $account->id)>
                                        {{ $account->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Switching between a bank account and a credit card flips how this entry
                                affects the balance — that is handled for you.
                            </div>
                        </div>
                    @endif

                    <div class="row g-3 mt-0">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="text" inputmode="decimal" name="amount" id="amount"
                                       class="form-control money"
                                       value="{{ old('amount', $transaction->amount) }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="transaction_date" class="form-label">Date</label>
                            <input type="date" name="transaction_date" id="transaction_date" class="form-control"
                                   value="{{ old('transaction_date', $transaction->transaction_date->toDateString()) }}"
                                   required>
                        </div>
                    </div>

                    @unless ($isTransfer)
                        <div class="row g-3 mt-0">
                            <div class="col-md-6">
                                <label for="category_id" class="form-label">Category</label>
                                <select name="category_id" id="category_id" class="form-select">
                                    <option value="">—</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}"
                                                @selected(old('category_id', $transaction->category_id) == $category->id)>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="merchant_id" class="form-label">Merchant</label>
                                <select name="merchant_id" id="merchant_id" class="form-select">
                                    <option value="">—</option>
                                    @foreach ($merchants as $merchant)
                                        <option value="{{ $merchant->id }}"
                                                @selected(old('merchant_id', $transaction->merchant_id) == $merchant->id)>
                                            {{ $merchant->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="payer_id" class="form-label">Paid by</label>
                                <select name="payer_id" id="payer_id" class="form-select">
                                    <option value="">—</option>
                                    @foreach ($payers as $person)
                                        <option value="{{ $person->id }}"
                                                @selected(old('payer_id', $transaction->payer_id) == $person->id)>
                                            {{ $person->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="beneficiary_id" class="form-label">For</label>
                                <select name="beneficiary_id" id="beneficiary_id" class="form-select">
                                    <option value="">—</option>
                                    @foreach ($beneficiaries as $person)
                                        <option value="{{ $person->id }}"
                                                @selected(old('beneficiary_id', $transaction->beneficiary_id) == $person->id)>
                                            {{ $person->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="planned_status" class="form-label">Planned</label>
                                <select name="planned_status" id="planned_status" class="form-select">
                                    <option value="">—</option>
                                    @foreach ($plannedStatuses as $status)
                                        <option value="{{ $status->value }}"
                                                @selected(old('planned_status', $transaction->planned_status?->value) === $status->value)>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="purpose" class="form-label">Purpose</label>
                                <select name="purpose" id="purpose" class="form-select">
                                    <option value="">—</option>
                                    @foreach ($purposes as $purpose)
                                        <option value="{{ $purpose->value }}"
                                                @selected(old('purpose', $transaction->purpose?->value) === $purpose->value)>
                                            {{ $purpose->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endunless

                    <div class="mt-3">
                        <label for="description" class="form-label">Note</label>
                        <input type="text" name="description" id="description" class="form-control"
                               value="{{ old('description', $transaction->description) }}">
                    </div>

                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button class="btn btn-primary">Save changes</button>
                    <a href="{{ route('transactions.show', $transaction) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
