@extends('layouts.app')

@section('title', 'Add loan')
@section('heading', 'Add a loan')
@section('subheading', 'Just the EMI, the tenure and when it started')

@section('content')
<div class="row">
    <div class="col-lg-9 col-xl-7">
        <form method="POST" action="{{ route('loans.store') }}">
            @csrf
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-info small">
                        No interest rate needed. The EMI and the number of months are all that is
                        required — what you have paid, what is left and every future due date are
                        worked out from those.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Loan name</label>
                            <input type="text" name="name" id="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" placeholder="Land Loan" required autofocus>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="lender" class="form-label">Lender <span class="text-body-secondary">(optional)</span></label>
                            <input type="text" name="lender" id="lender" class="form-control"
                                   value="{{ old('lender') }}" placeholder="HDFC">
                        </div>

                        <div class="col-md-4">
                            <label for="emi_amount" class="form-label">Monthly EMI</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="text" inputmode="decimal" name="emi_amount" id="emi_amount"
                                       class="form-control money @error('emi_amount') is-invalid @enderror"
                                       value="{{ old('emi_amount') }}" required>
                                @error('emi_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="total_months" class="form-label">Total months</label>
                            <input type="number" min="1" max="600" name="total_months" id="total_months"
                                   class="form-control @error('total_months') is-invalid @enderror"
                                   value="{{ old('total_months') }}" required>
                            @error('total_months')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="due_day" class="form-label">Deducted on day</label>
                            <input type="number" min="1" max="31" name="due_day" id="due_day"
                                   class="form-control @error('due_day') is-invalid @enderror"
                                   value="{{ old('due_day') }}" required>
                            @error('due_day')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Loan started</label>
                            <input type="date" name="start_date" id="start_date"
                                   class="form-control @error('start_date') is-invalid @enderror"
                                   value="{{ old('start_date') }}" required>
                            @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Ends</label>
                            <input type="text" class="form-control" id="endPreview" value="—" readonly disabled>
                            <div class="form-text">Worked out from the start date and tenure.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="payment_account_id" class="form-label">Usually paid from</label>
                            <select name="payment_account_id" id="payment_account_id" class="form-select">
                                <option value="">—</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}" @selected(old('payment_account_id') == $account->id)>
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
                                    <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                                        {{ $category->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input type="hidden" name="mark_past_as_paid" value="0">
                                <input type="checkbox" name="mark_past_as_paid" id="mark_past_as_paid" value="1"
                                       class="form-check-input" @checked(old('mark_past_as_paid', true))>
                                <label class="form-check-label" for="mark_past_as_paid">
                                    EMIs before today have already been paid
                                </label>
                            </div>
                            <div class="form-text">
                                Marks past instalments as paid so the loan shows the right progress.
                                No bank entries are created for them — those payments happened before
                                you started tracking, so inventing them would throw your balances off.
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">Notes <span class="text-body-secondary">(optional)</span></label>
                            <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button class="btn btn-primary">Save loan</button>
                    <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    // Show the end date as it is typed, so the tenure can be sanity-checked
    // before saving.
    function updateEnd() {
        const start = $('#start_date').val();
        const months = parseInt($('#total_months').val(), 10);
        const day = parseInt($('#due_day').val(), 10);

        if (!start || !months || !day) {
            $('#endPreview').val('—');
            return;
        }

        const startDate = new Date(start + 'T00:00:00');
        let first = new Date(startDate.getFullYear(), startDate.getMonth(), 1);

        // First instalment is this month if the day has not passed, else next.
        const dayThisMonth = Math.min(day, new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0).getDate());
        if (dayThisMonth < startDate.getDate()) {
            first = new Date(startDate.getFullYear(), startDate.getMonth() + 1, 1);
        }

        const lastMonth = new Date(first.getFullYear(), first.getMonth() + months - 1, 1);
        const lastDay = Math.min(day, new Date(lastMonth.getFullYear(), lastMonth.getMonth() + 1, 0).getDate());
        const end = new Date(lastMonth.getFullYear(), lastMonth.getMonth(), lastDay);

        $('#endPreview').val(end.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }));
    }

    $('#start_date, #total_months, #due_day').on('input change', updateEnd);
    updateEnd();
});
</script>
@endpush
