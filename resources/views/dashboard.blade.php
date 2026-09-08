@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', $month->format('F Y'))
@section('subheading', 'Where the household money went this month')

@section('actions')
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm">
            <button class="btn btn-sm btn-outline-secondary">Go</button>
        </form>
        <a href="{{ route('quick-entry') }}" class="btn btn-sm btn-primary text-nowrap">+ Quick Entry</a>
    </div>
@endsection

@section('content')

@if ($accounts->isEmpty())
    <div class="card mb-4">
        <div class="card-body empty-state">
            <h2 class="h5">Start with your accounts</h2>
            <p class="mb-3">
                Add your bank accounts, cash and credit cards with the balance each one holds today.
                That starting position is recorded separately from your spending, so this month's
                numbers stay clean.
            </p>
            <a href="{{ route('accounts.create') }}" class="btn btn-primary">Add an account</a>
        </div>
    </div>
@endif

{{-- Headline figures. Each is defined once in SpendingReportService. --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="stat-label">Received</div>
                <div class="stat-value money money-pos">@inr($income)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="stat-label">Spent</div>
                <div class="stat-value money">@inr($spending)</div>
                <div class="small text-body-secondary mt-1">Excludes transfers &amp; card bill payments</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="stat-label">On credit cards</div>
                <div class="stat-value money">@inr($cardSpending)</div>
                <div class="small text-body-secondary mt-1">Purchases, not bill payments</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="stat-label">Bank + cash now</div>
                <div class="stat-value money">@inr($spendableCash)</div>
                <div class="small text-body-secondary mt-1">Before upcoming commitments</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Where it went</span>
                <a href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'type' => 'expense']) }}"
                   class="small text-decoration-none">View all</a>
            </div>
            <div class="card-body">
                @if ($byCategory->isEmpty())
                    <div class="empty-state mb-0">No spending recorded for {{ $month->format('F') }} yet.</div>
                @else
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <canvas id="categoryChart" height="220"></canvas>
                        </div>
                        <div class="col-md-7">
                            {{-- Every chart has a table beside it: the numbers must be
                                 readable and clickable, not just coloured. --}}
                            <table class="table table-sm mb-0">
                                <tbody>
                                @foreach ($byCategory as $row)
                                    <tr>
                                        <td>
                                            <a class="text-decoration-none"
                                               href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'category_id' => $row->category_id, 'type' => 'expense']) }}">
                                                {{ $row->label }}
                                            </a>
                                        </td>
                                        <td class="text-end money">@inr($row->amount)</td>
                                        <td class="text-end text-body-secondary small" style="width:3.5rem;">
                                            {{ bccomp($spending, '0', 2) > 0 ? round($row->amount / $spending * 100) : 0 }}%
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">Today</div>
            <div class="card-body p-0">
                @if ($today->isEmpty())
                    <div class="empty-state">
                        Nothing recorded today.
                        <div class="mt-2"><a href="{{ route('quick-entry') }}" class="btn btn-sm btn-outline-primary">Add a spend</a></div>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <tbody>
                            @foreach ($today as $t)
                                <tr>
                                    <td>
                                        <a href="{{ route('transactions.show', $t) }}" class="text-decoration-none">
                                            {{ $t->description ?: $t->merchant?->name ?: $t->type->label() }}
                                        </a>
                                        <div class="small text-body-secondary">
                                            {{ $t->category?->name ?: 'Uncategorised' }} · {{ $t->account->name }}
                                        </div>
                                    </td>
                                    <td class="text-end money {{ $t->type->countsAsSpending() ? '' : 'text-body-secondary' }}">
                                        @inr($t->amount)
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

    </div>

    <div class="col-lg-5">

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Balances</span>
                <a href="{{ route('accounts.index') }}" class="small text-decoration-none">Manage</a>
            </div>
            <div class="card-body p-0">
                @if ($accounts->isEmpty())
                    <div class="empty-state">No accounts yet.</div>
                @else
                    <table class="table table-sm mb-0 align-middle">
                        <tbody>
                        @foreach ($accounts as $account)
                            <tr>
                                <td>
                                    <a href="{{ route('accounts.show', $account) }}" class="text-decoration-none">
                                        {{ $account->name }}
                                    </a>
                                    <div class="small text-body-secondary">{{ $account->type->label() }}</div>
                                </td>
                                <td class="text-end money {{ $account->isLiability() ? 'money-neg' : '' }}">
                                    @inr($account->cached_balance)
                                    @if ($account->isLiability())
                                        <div class="small text-body-secondary">owed</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="border-top">
                            <tr>
                                <th class="small">Net worth</th>
                                <th class="text-end money">@inr($netWorth['net_worth'])</th>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Planned vs unplanned</div>
            <div class="card-body p-0">
                @if ($byPlanned->isEmpty())
                    <div class="empty-state">Nothing to compare yet.</div>
                @else
                    <table class="table table-sm mb-0">
                        <tbody>
                        @foreach ($byPlanned as $row)
                            <tr>
                                <td>
                                    <a class="text-decoration-none"
                                       href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'planned_status' => $row->key, 'type' => 'expense']) }}">
                                        {{ $row->label }}
                                    </a>
                                </td>
                                <td class="text-end money">@inr($row->amount)</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">Who paid</div>
            <div class="card-body p-0">
                @if ($byPayer->isEmpty())
                    <div class="empty-state">Not recorded yet.</div>
                @else
                    <table class="table table-sm mb-0">
                        <tbody>
                        @foreach ($byPayer as $row)
                            <tr>
                                <td>
                                    <a class="text-decoration-none"
                                       href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'payer_id' => $row->key, 'type' => 'expense']) }}">
                                        {{ $row->label }}
                                    </a>
                                </td>
                                <td class="text-end money">@inr($row->amount)</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
@if ($byCategory->isNotEmpty())
<script>
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: @json($byCategory->pluck('label')),
        datasets: [{
            data: @json($byCategory->pluck('amount')->map(fn ($a) => (float) $a)),
            borderWidth: 0,
            backgroundColor: [
                '#4e79a7', '#f28e2c', '#e15759', '#76b7b2', '#59a14f',
                '#edc949', '#af7aa1', '#ff9da7', '#9c755f', '#bab0ab'
            ],
        }],
    },
    options: {
        plugins: { legend: { display: false } },
        cutout: '62%',
        responsive: true,
        maintainAspectRatio: false,
    },
});
</script>
@endif
@endpush
