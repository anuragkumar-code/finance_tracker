@extends('layouts.app')

@section('title', 'Edit loan')
@section('heading', 'Edit ' . $loan->name)

@section('content')
<div class="row">
    <div class="col-lg-9 col-xl-7">
        <form method="POST" action="{{ route('loans.update', $loan) }}">
            @csrf
            @method('PUT')
            <div class="card">
                <div class="card-body">
                    {{-- EMI and tenure are intentionally not editable: instalments
                         already confirmed are anchored to them. --}}
                    <div class="alert alert-secondary small">
                        The EMI ({{ \App\Support\Money::inr($loan->emi_amount) }}) and tenure
                        ({{ $loan->total_months }} months) cannot be changed, because instalments have
                        already been recorded against this schedule. If the loan itself was
                        restructured, add it as a new loan.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Loan name</label>
                            <input type="text" name="name" id="name" class="form-control"
                                   value="{{ old('name', $loan->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="lender" class="form-label">Lender</label>
                            <input type="text" name="lender" id="lender" class="form-control"
                                   value="{{ old('lender', $loan->lender) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="payment_account_id" class="form-label">Usually paid from</label>
                            <select name="payment_account_id" id="payment_account_id" class="form-select">
                                <option value="">—</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}"
                                            @selected(old('payment_account_id', $loan->payment_account_id) == $account->id)>
                                        {{ $account->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="category_id" class="form-label">File EMIs under</label>
                            <select name="category_id" id="category_id" class="form-select">
                                <option value="">—</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}"
                                            @selected(old('category_id', $loan->category_id) == $category->id)>
                                        {{ $category->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes', $loan->notes) }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button class="btn btn-primary">Save changes</button>
                    <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
