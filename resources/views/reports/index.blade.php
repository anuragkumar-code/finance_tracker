@extends('layouts.app')

@section('title', 'Reports')
@section('heading', 'Where the money went')
@section('subheading', $month->format('F Y'))

@section('actions')
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm">
            <button class="btn btn-sm btn-outline-secondary">Go</button>
        </form>
        <a href="{{ route('reports.trends') }}" class="btn btn-sm btn-outline-secondary text-nowrap">Trends</a>
        <a href="{{ route('reports.net-worth') }}" class="btn btn-sm btn-outline-secondary text-nowrap">Net worth</a>
    </div>
@endsection

@section('content')
@php($base = ['start' => $start, 'end' => $end])

{{-- Section 19A: income versus spending, and what it left. --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Received</div>
            <div class="stat-value money money-pos">@inr($income)</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Spent</div>
            <div class="stat-value money">@inr($spending)</div>
            @if (bccomp($debtRepayment, '0', 2) === 1)
                <div class="small text-body-secondary mt-1">incl. @inr($debtRepayment) loan EMIs</div>
            @endif
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Left over</div>
            <div class="stat-value money {{ bccomp($surplus, '0', 2) === -1 ? 'money-neg' : 'money-pos' }}">
                @inr($surplus)
            </div>
            @if ($savingsRate !== null)
                <div class="small text-body-secondary mt-1">{{ $savingsRate }}% of what came in</div>
            @endif
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="stat-label">Left your accounts</div>
            <div class="stat-value money">@inr($cashOutflow)</div>
            {{-- Cash outflow is deliberately not the same as spending. --}}
            <div class="small text-body-secondary mt-1">incl. transfers &amp; card bills</div>
        </div></div>
    </div>
</div>

@if (bccomp($spending, '0', 2) !== 1 && bccomp($income, '0', 2) !== 1)
    <div class="card">
        <div class="card-body empty-state">
            <h2 class="h5">Nothing recorded for {{ $month->format('F Y') }}</h2>
            <p class="mb-3">Once you start entering spending, this page breaks it down every way.</p>
            <a href="{{ route('quick-entry') }}" class="btn btn-primary">Record a spend</a>
        </div>
    </div>
@else

{{-- Section 19B: weekly rhythm within the month. --}}
<div class="card mb-4">
    <div class="card-header">Week by week</div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-7">
                <canvas id="weeklyChart" height="200"></canvas>
            </div>
            <div class="col-lg-5">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Week</th>
                            <th class="text-end">In</th>
                            <th class="text-end">Out</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($weekly as $week)
                        <tr>
                            <td>
                                <a class="text-decoration-none"
                                   href="{{ route('transactions.index', ['start' => $week->start, 'end' => $week->end]) }}">
                                    {{ $week->label }}
                                </a>
                                <div class="small text-body-secondary">{{ $week->range }}</div>
                            </td>
                            <td class="text-end money money-pos">@inr($week->income)</td>
                            <td class="text-end money">@inr($week->spending)</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Section 19C: category analysis. --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">By category</div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <canvas id="categoryChart" height="220"></canvas>
                    </div>
                    <div class="col-md-7">
                        <table class="table table-sm mb-0">
                            <tbody>
                            @foreach ($byCategory as $row)
                                @php($share = bccomp($spending, '0', 2) === 1 ? round($row->amount / $spending * 100) : 0)
                                <tr>
                                    <td>
                                        <a class="text-decoration-none"
                                           href="{{ route('transactions.index', $base + ['type' => 'expense', 'category_id' => $row->category_id]) }}">
                                            {{ $row->label }}
                                        </a>
                                    </td>
                                    <td class="text-end money">@inr($row->amount)</td>
                                    <td class="text-end text-body-secondary small" style="width:3.2rem;">{{ $share }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Section 19G: the household's stated priority. --}}
    <div class="col-lg-5">
        @include('reports._breakdown', [
            'title' => 'Planned vs unplanned',
            'rows' => $byPlanned,
            'total' => $spending,
            'filterKey' => 'planned_status',
            'baseFilters' => $base,
            'empty' => 'No spending has been tagged planned or unplanned yet.',
        ])
    </div>
</div>

{{-- How it was bought, not what was bought. Quick-commerce spending hides
     inside category totals otherwise. --}}
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>How you bought it</span>
        <span class="small text-body-secondary">
            online &amp; delivery: <span class="money">@inr($online['total_online'])</span>
        </span>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-sm-4">
                <div class="stat-label">Quick commerce</div>
                <div class="h5 mb-0 money">@inr($online['quick_commerce'])</div>
                <div class="small text-body-secondary">Blinkit, Zepto, Instamart</div>
            </div>
            <div class="col-sm-4">
                <div class="stat-label">Online shopping</div>
                <div class="h5 mb-0 money">@inr($online['ecommerce'])</div>
                <div class="small text-body-secondary">Amazon, Flipkart, Myntra</div>
            </div>
            <div class="col-sm-4">
                <div class="stat-label">Food delivery</div>
                <div class="h5 mb-0 money">@inr($online['food_delivery'])</div>
                <div class="small text-body-secondary">Swiggy, Zomato</div>
            </div>
        </div>

        @if ($byChannel->isNotEmpty())
            <table class="table table-sm mb-0">
                <tbody>
                @foreach ($byChannel as $row)
                    @php($share = bccomp($spending, '0', 2) === 1 ? round($row->amount / $spending * 100) : 0)
                    <tr>
                        <td>
                            <span class="badge text-bg-{{ $row->badge }}">{{ $row->label }}</span>
                            @if ($row->hint)
                                <div class="small text-body-secondary">{{ $row->hint }}</div>
                            @endif
                        </td>
                        <td class="text-end text-body-secondary small">{{ $row->count }} entries</td>
                        <td class="text-end money">@inr($row->amount)</td>
                        <td class="text-end text-body-secondary small" style="width:3.2rem;">{{ $share }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="card-footer bg-white small text-body-secondary">
        Channels come from the merchant. Well-known names classify themselves; the rest
        default to "in person" and can be corrected under
        <a href="{{ route('settings.merchants.index') }}">Settings &rarr; Merchants</a>.
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Sections 19D and 19E: who paid, and who it was for. --}}
    <div class="col-lg-6">
        @include('reports._breakdown', [
            'title' => 'Who paid',
            'rows' => $byPayer,
            'total' => $spending,
            'filterKey' => 'payer_id',
            'baseFilters' => $base,
        ])
    </div>
    <div class="col-lg-6">
        @include('reports._breakdown', [
            'title' => 'Who it was for',
            'rows' => $byBeneficiary,
            'total' => $spending,
            'filterKey' => 'beneficiary_id',
            'baseFilters' => $base,
        ])
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Section 19F: payment method. Card purchases are shown here; card BILL
         payments are a separate figure on the credit-card report. --}}
    <div class="col-lg-6">
        @include('reports._breakdown', [
            'title' => 'Paid with',
            'rows' => $byAccount,
            'total' => $spending,
            'filterKey' => 'account_id',
            'baseFilters' => $base,
        ])
    </div>
    <div class="col-lg-6">
        @include('reports._breakdown', [
            'title' => 'What it was for',
            'rows' => $byPurpose,
            'total' => $spending,
            'filterKey' => 'purpose',
            'baseFilters' => $base,
        ])
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        @include('reports._breakdown', [
            'title' => 'By subcategory',
            'rows' => $bySubcategory,
            'total' => $spending,
            'filterKey' => 'category_id',
            'baseFilters' => $base,
            'empty' => 'No subcategories used this month.',
        ])
    </div>
    <div class="col-lg-6">
        @include('reports._breakdown', [
            'title' => 'Top merchants',
            'rows' => $byMerchant,
            'total' => $spending,
            'filterKey' => 'merchant_id',
            'baseFilters' => $base,
            'empty' => 'No merchants recorded this month.',
        ])
    </div>
</div>

<div class="card mt-3">
    <div class="card-body small text-body-secondary">
        <strong class="text-body">Reading these numbers.</strong>
        "Spent" is what you consumed. "Left your accounts" is broader — it includes moving
        money between your own accounts and paying card bills, neither of which is spending.
        Card purchases count as spending on the day you buy; paying that card's bill later is
        not counted again.
    </div>
</div>

@endif
@endsection

@push('scripts')
<script>
const chartFont = { family: getComputedStyle(document.body).fontFamily, size: 11 };
Chart.defaults.font = chartFont;

new Chart(document.getElementById('weeklyChart'), {
    type: 'bar',
    data: {
        labels: @json($weekly->pluck('label')),
        datasets: [
            {
                label: 'Received',
                data: @json($weekly->pluck('income')->map(fn ($v) => (float) $v)),
                backgroundColor: '#59a14f',
            },
            {
                label: 'Spent',
                data: @json($weekly->pluck('spending')->map(fn ($v) => (float) $v)),
                backgroundColor: '#4e79a7',
            },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, ticks: { callback: v => '₹' + v.toLocaleString('en-IN') } } },
        plugins: { legend: { position: 'bottom' } },
    },
});

@if ($byCategory->isNotEmpty())
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: @json($byCategory->pluck('label')),
        datasets: [{
            data: @json($byCategory->pluck('amount')->map(fn ($v) => (float) $v)),
            borderWidth: 0,
            backgroundColor: [
                '#4e79a7', '#f28e2c', '#e15759', '#76b7b2', '#59a14f',
                '#edc949', '#af7aa1', '#ff9da7', '#9c755f', '#bab0ab',
            ],
        }],
    },
    options: {
        cutout: '62%',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
    },
});
@endif
</script>
@endpush
