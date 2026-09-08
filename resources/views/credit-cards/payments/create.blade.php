@extends('layouts.app')

@section('title', 'Pay card bill')
@section('heading', 'Pay ' . $card->card_name)
@section('subheading', 'Clears what you owe — this is not counted as spending')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('credit-cards.payments.store', $card) }}">
            @csrf
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-info small">
                        Paying this bill moves money out of your bank account and reduces what you
                        owe on the card. The purchases were already counted as spending when you
                        made them, so this payment will <strong>not</strong> be counted again.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="text" inputmode="decimal" name="amount" id="amount"
                                       class="form-control money @error('amount') is-invalid @enderror"
                                       value="{{ old('amount', $suggestedAmount) }}" required autofocus>
                                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-text">
                                Currently owed on this card: <span class="money">@inr($card->outstanding())</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="payment_date" class="form-label">Paid on</label>
                            <input type="date" name="payment_date" id="payment_date" class="form-control"
                                   value="{{ old('payment_date', now()->toDateString()) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label for="source_account_id" class="form-label">Paid from</label>
                            <select name="source_account_id" id="source_account_id"
                                    class="form-select @error('source_account_id') is-invalid @enderror" required>
                                <option value="">Choose…</option>
                                @foreach ($sourceAccounts as $account)
                                    <option value="{{ $account->id }}" @selected(old('source_account_id') == $account->id)>
                                        {{ $account->name }} (@inr($account->cached_balance))
                                    </option>
                                @endforeach
                            </select>
                            @error('source_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="statement_id" class="form-label">
                                Against statement <span class="text-body-secondary">(optional)</span>
                            </label>
                            <select name="statement_id" id="statement_id" class="form-select">
                                <option value="">Not tied to a statement</option>
                                @foreach ($statements as $s)
                                    <option value="{{ $s->id }}"
                                            @selected(old('statement_id', $statement?->id) == $s->id)>
                                        {{ $s->period_end->format('d M Y') }} —
                                        {{ \App\Support\Money::inr($s->balanceRemaining()) }} left
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Links the payment so the bill is marked paid.</div>
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">Notes <span class="text-body-secondary">(optional)</span></label>
                            <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button class="btn btn-primary">Record payment</button>
                    <a href="{{ route('credit-cards.show', $card) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">What this will do</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td>Your bank balance</td>
                            <td class="text-end money money-neg">goes down</td>
                        </tr>
                        <tr>
                            <td>Amount owed on {{ $card->card_name }}</td>
                            <td class="text-end money money-pos">goes down</td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-semibold">Your spending total</td>
                            <td class="text-end fw-semibold">unchanged</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
