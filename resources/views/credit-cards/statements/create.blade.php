@extends('layouts.app')

@section('title', 'Add statement')
@section('heading', 'Add a statement')
@section('subheading', $card->card_name . ' — group the purchases your bank has billed')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('credit-cards.statements.store', $card) }}">
            @csrf
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-info small">
                        A statement does not add to your spending — those purchases were already
                        recorded when you made them. This just groups them and records what the
                        bank says you owe.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="period_start" class="form-label">Period from</label>
                            <input type="date" name="period_start" id="period_start" class="form-control"
                                   value="{{ old('period_start', $periodStart->toDateString()) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="period_end" class="form-label">Period to</label>
                            <input type="date" name="period_end" id="period_end" class="form-control"
                                   value="{{ old('period_end', $periodEnd->toDateString()) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="statement_date" class="form-label">Statement date</label>
                            <input type="date" name="statement_date" id="statement_date" class="form-control"
                                   value="{{ old('statement_date', $periodEnd->toDateString()) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="due_date" class="form-label">Payment due by</label>
                            <input type="date" name="due_date" id="due_date" class="form-control"
                                   value="{{ old('due_date', $dueDate->toDateString()) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label for="statement_amount" class="form-label">Statement amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="text" inputmode="decimal" name="statement_amount" id="statement_amount"
                                       class="form-control money"
                                       value="{{ old('statement_amount', $preview) }}" required>
                            </div>
                            <div class="form-text">
                                Pre-filled from your recorded purchases. Replace it with the figure on
                                your real statement if they differ — the gap is worth knowing about.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="minimum_due" class="form-label">Minimum due <span class="text-body-secondary">(optional)</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="text" inputmode="decimal" name="minimum_due" id="minimum_due"
                                       class="form-control money" value="{{ old('minimum_due') }}">
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">Notes <span class="text-body-secondary">(optional)</span></label>
                            <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button class="btn btn-primary">Record statement</button>
                    <a href="{{ route('credit-cards.show', $card) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>What will be grouped</span>
                <span class="money fw-semibold">@inr($preview)</span>
            </div>
            <div class="card-body p-0">
                @if ($transactions->isEmpty())
                    <div class="empty-state">
                        No unbilled purchases in this period.
                    </div>
                @else
                    <table class="table table-sm mb-0">
                        <tbody>
                        @foreach ($transactions as $t)
                            <tr>
                                <td class="text-nowrap text-body-secondary small">{{ $t->transaction_date->format('d M') }}</td>
                                <td class="small">{{ $t->description ?: $t->merchant?->name ?: 'Purchase' }}</td>
                                <td class="text-end money small">
                                    {{ $t->balance_effect === \App\Enums\BalanceEffect::Decrease ? '−' : '' }}@inr($t->amount)
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="card-footer bg-white small text-body-secondary">
                Adjust the dates above and reload to change what falls inside the period.
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    // Changing the period re-queries which purchases fall inside it, so the
    // preview always matches what will actually be grouped.
    $('#period_start, #period_end').on('change', function () {
        const start = $('#period_start').val();
        const end = $('#period_end').val();
        if (start && end) {
            window.location = '{{ route('credit-cards.statements.create', $card) }}'
                + '?period_start=' + start + '&period_end=' + end;
        }
    });
});
</script>
@endpush
