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
                @if (bccomp($debtRepaid, '0', 2) === 1)
                    {{-- EMIs are included in "spent" by household choice, so the
                         debt share is shown rather than buried. --}}
                    <div class="small text-body-secondary mt-1">
                        incl. @inr($debtRepaid) loan EMIs
                    </div>
                @else
                    <div class="small text-body-secondary mt-1">Excludes transfers &amp; card bill payments</div>
                @endif
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
                <div class="stat-label">Realistically available</div>
                <div class="stat-value money {{ $reality['is_negative'] ? 'money-neg' : '' }}">
                    @inr($reality['realistic'])
                </div>
                <div class="small text-body-secondary mt-1">
                    @inr($spendableCash) less @inr($reality['committed']) committed
                </div>
            </div>
        </div>
    </div>
</div>

@if ($budgetAlerts->isNotEmpty() || $spendingAnomalies->isNotEmpty())
    {{-- Only what needs attention. A full budget table lives on its own page;
         the dashboard should not nag about categories that are fine. --}}
    <div class="card mb-4 border-warning">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span>Worth a look</span>
            <a href="{{ route('budgets.index') }}" class="small text-decoration-none">Budgets</a>
        </div>
        <div class="card-body">
            @foreach ($budgetAlerts as $alert)
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <div>
                        <span class="fw-medium">{{ $alert->category?->name ?? 'Uncategorised' }}</span>
                        @if ($alert->status === 'over')
                            <span class="badge text-bg-danger">over budget</span>
                        @else
                            <span class="badge text-bg-warning">spending fast</span>
                        @endif
                        <div class="small text-body-secondary">
                            {{ $alert->used_percent }}% of budget used,
                            {{ $alert->month_elapsed_percent }}% of the month gone
                        </div>
                    </div>
                    <div class="money">@inr($alert->spent) <span class="text-body-secondary">/ @inr($alert->budget)</span></div>
                </div>
            @endforeach

            @foreach ($spendingAnomalies as $a)
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <div>
                        <span class="fw-medium">{{ $a->category?->name ?? 'Uncategorised' }}</span>
                        <span class="badge text-bg-secondary">{{ $a->ratio }}× usual</span>
                        <div class="small text-body-secondary">
                            normally around @inr($a->usual) a month
                        </div>
                    </div>
                    <div class="money">@inr($a->spent)</div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if ($obligations->isNotEmpty())
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Coming up</span>
            <a href="{{ route('upcoming.index') }}" class="small text-decoration-none">See all</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <tbody>
                @foreach ($obligations as $item)
                    <tr>
                        <td class="text-nowrap text-body-secondary" style="width:6rem;">
                            {{ $item->due_date->format('d M') }}
                        </td>
                        <td>
                            <a href="{{ $item->url }}" class="text-decoration-none">{{ $item->label }}</a>
                            @if ($item->is_estimated)
                                <span class="badge text-bg-secondary">estimate</span>
                            @endif
                        </td>
                        <td class="text-end money">@inr($item->amount)</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

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

        @if (bccomp($cardsOwed, '0', 2) !== 0 || $cardDues->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Cards</span>
                    <a href="{{ route('credit-cards.index') }}" class="small text-decoration-none">View all</a>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                        <span class="text-body-secondary">Owed across cards</span>
                        <span class="h5 mb-0 money money-neg">@inr($cardsOwed)</span>
                    </div>

                    @if ($cardDues->isEmpty())
                        <div class="small text-body-secondary">No bills due in the next 30 days.</div>
                    @else
                        <div class="small text-body-secondary mb-1">Due in the next 30 days</div>
                        <table class="table table-sm mb-0">
                            <tbody>
                            @foreach ($cardDues as $due)
                                <tr>
                                    <td>
                                        <a class="text-decoration-none"
                                           href="{{ route('credit-cards.statements.show', [$due->creditCard, $due]) }}">
                                            {{ $due->creditCard->card_name }}
                                        </a>
                                        <div class="small text-body-secondary">
                                            by {{ $due->due_date->format('d M') }}
                                        </div>
                                    </td>
                                    <td class="text-end money">@inr($due->balanceRemaining())</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif

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
