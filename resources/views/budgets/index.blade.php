@extends('layouts.app')

@section('title', 'Budgets')
@section('heading', 'Budgets')
@section('subheading', $month->format('F Y'))

@section('actions')
    <form method="GET" class="d-flex gap-2">
        <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm">
        <button class="btn btn-sm btn-outline-secondary">Go</button>
    </form>
@endsection

@section('content')

@unless ($hasEnoughHistory)
    {{-- Spec section 22: budgets set before behaviour is understood are
         arbitrary. Say so rather than quietly offering made-up targets. --}}
    <div class="alert alert-info">
        <strong>You have {{ $monthsOfHistory }} month(s) of spending recorded.</strong>
        <div class="small mt-1">
            You can set any budget you already know — rent does not need a study.
            But the app will not <em>suggest</em> amounts until it has about
            {{ $monthsNeeded }} months to work from, because a target invented
            from a few entries is a guess wearing a number's clothes.
        </div>
    </div>
@endunless

@if ($anomalies->isNotEmpty())
    {{-- Judged against each category's own history, so a household that always
         spends heavily on rent is not warned about rent every month. --}}
    <div class="alert alert-warning">
        <strong>Spending more than usual this month</strong>
        <div class="small mt-1">
            @foreach ($anomalies as $a)
                <div>
                    <strong>{{ $a->category?->name ?? 'Uncategorised' }}</strong> —
                    <span class="money">@inr($a->spent)</span> against a usual
                    <span class="money">@inr($a->usual)</span>
                    ({{ $a->ratio }}×, from {{ $a->months }} month(s) of history)
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Budgeted</div>
            <div class="stat-value money">@inr($totals['budgeted'])</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Spent against it</div>
            <div class="stat-value money">@inr($totals['spent'])</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Left</div>
            <div class="stat-value money {{ bccomp($totals['remaining'], '0', 2) === -1 ? 'money-neg' : 'money-pos' }}">
                @inr($totals['remaining'])
            </div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Outside any budget</div>
            <div class="stat-value money">@inr($totals['unbudgeted'])</div>
            <div class="small text-body-secondary mt-1">spending with no target set</div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Budget vs actual</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-end">Budget</th>
                            <th class="text-end">Spent</th>
                            <th class="text-end">Left</th>
                            <th style="width:22%">Progress</th>
                            <th style="width:1%"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($rows as $row)
                        @php
                            $barClass = match ($row->status) {
                                'over' => 'bg-danger',
                                'ahead_of_pace' => 'bg-warning',
                                'close' => 'bg-warning',
                                default => 'bg-success',
                            };
                        @endphp
                        <tr>
                            <td>
                                <a class="text-decoration-none"
                                   href="{{ route('transactions.index', [
                                       'start' => $month->copy()->startOfMonth()->toDateString(),
                                       'end' => $month->copy()->endOfMonth()->toDateString(),
                                       'type' => 'expense',
                                       'category_id' => $row->category_id,
                                   ]) }}">
                                    {{ $row->category?->name ?? 'Uncategorised' }}
                                </a>
                                @if ($row->status === 'over')
                                    <span class="badge text-bg-danger">over</span>
                                @elseif ($row->status === 'ahead_of_pace')
                                    <span class="badge text-bg-warning">fast</span>
                                @elseif ($row->status === 'unbudgeted')
                                    <span class="badge text-bg-light border">no budget</span>
                                @endif
                            </td>
                            <td class="text-end money">
                                {{ $row->budget !== null ? \App\Support\Money::inr($row->budget) : '—' }}
                            </td>
                            <td class="text-end money">@inr($row->spent)</td>
                            <td class="text-end money {{ $row->remaining !== null && bccomp($row->remaining, '0', 2) === -1 ? 'money-neg' : '' }}">
                                {{ $row->remaining !== null ? \App\Support\Money::inr($row->remaining) : '—' }}
                            </td>
                            <td>
                                @if ($row->used_percent !== null)
                                    <div class="progress" style="height:5px;">
                                        <div class="progress-bar {{ $barClass }}"
                                             style="width: {{ min($row->used_percent, 100) }}%"></div>
                                    </div>
                                    <div class="small text-body-secondary">
                                        {{ $row->used_percent }}% used ·
                                        {{ $row->month_elapsed_percent }}% of month gone
                                    </div>
                                @else
                                    <span class="text-body-secondary small">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($row->budget !== null)
                                    <form method="POST" action="{{ route('budgets.destroy', $row->category_id) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-link text-danger text-decoration-none p-0">
                                            Remove
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">
                                No budgets set and nothing spent this month yet.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-body-secondary">
                "% of month gone" sits next to "% used" on purpose: spending 70% of a budget
                on the 5th is a very different thing from the same figure on the 25th.
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Set a budget</div>
            <form method="POST" action="{{ route('budgets.store') }}" class="card-body">
                @csrf
                <div class="mb-3">
                    <label for="category_id" class="form-label">Category</label>
                    <select name="category_id" id="category_id" class="form-select" required>
                        <option value="">Choose…</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="amount" class="form-label">Monthly amount</label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="text" inputmode="decimal" name="amount" id="amount"
                               class="form-control money @error('amount') is-invalid @enderror"
                               value="{{ old('amount') }}" required>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text" id="suggestionHint">
                        Applies from this month onwards. Earlier months keep whatever they
                        were judged against.
                    </div>
                </div>
                <input type="hidden" name="month" value="{{ $month->copy()->startOfMonth()->toDateString() }}">
                <button class="btn btn-primary w-100">Save budget</button>
            </form>
        </div>

        <div class="card">
            <div class="card-body small text-body-secondary">
                <div class="fw-semibold text-body mb-1">How these are judged</div>
                A budget covers ordinary spending in that category. Transfers, credit-card
                bill payments and money moved to your emergency fund are not spending, so
                they never count against a budget. Loan EMIs do, because this household
                chose to count them as spending.
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    // Offer the household's own average when there is enough history to mean
    // something — and say plainly when there is not.
    $('#category_id').on('change', function () {
        const id = $(this).val();
        const $hint = $('#suggestionHint');

        if (!id) return;

        $.getJSON('{{ url('budgets') }}/' + id + '/suggestion', function (data) {
            if (data.amount) {
                $hint.html('Your average over the last ' + data.months + ' months is <strong>₹'
                    + Number(data.amount).toLocaleString('en-IN') + '</strong>. '
                    + '<a href="#" id="useSuggestion">Use it</a>');
            } else {
                $hint.text(data.reason);
            }
        });
    });

    $(document).on('click', '#useSuggestion', function (e) {
        e.preventDefault();
        const match = $('#suggestionHint').text().match(/₹([\d,]+(\.\d+)?)/);
        if (match) $('#amount').val(match[1].replace(/,/g, ''));
    });
});
</script>
@endpush
