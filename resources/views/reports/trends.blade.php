@extends('layouts.app')

@section('title', 'Trends')
@section('heading', 'How things are changing')
@section('subheading', 'Last ' . $months . ' months side by side')

@section('actions')
    <div class="d-flex gap-2">
        <div class="btn-group btn-group-sm">
            @foreach ([3, 6, 12] as $option)
                <a href="{{ route('reports.trends', ['months' => $option]) }}"
                   class="btn btn-outline-secondary {{ $months === $option ? 'active' : '' }}">{{ $option }}m</a>
            @endforeach
        </div>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary">This month</a>
    </div>
@endsection

@section('content')

@php($hasData = $series->contains(fn ($m) => bccomp($m->spending, '0', 2) === 1 || bccomp($m->income, '0', 2) === 1))

@unless ($hasData)
    <div class="card">
        <div class="card-body empty-state">
            <h2 class="h5">Not enough history yet</h2>
            <p class="mb-0">
                Trends need a few months of entries before they say anything useful.
                Keep recording and this page will start showing patterns.
            </p>
        </div>
    </div>
@else

<div class="card mb-4">
    <div class="card-header">Money in vs money out</div>
    <div class="card-body">
        <canvas id="trendChart" height="90"></canvas>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">Month by month</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="text-end">Received</th>
                    <th class="text-end">Spent</th>
                    <th class="text-end">On cards</th>
                    <th class="text-end">Left over</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($series as $m)
                <tr>
                    <td>
                        <a class="text-decoration-none"
                           href="{{ route('reports.index', ['month' => $m->month]) }}">{{ $m->label }}</a>
                    </td>
                    <td class="text-end money money-pos">@inr($m->income)</td>
                    <td class="text-end money">@inr($m->spending)</td>
                    <td class="text-end money text-body-secondary">@inr($m->card_spending)</td>
                    <td class="text-end money {{ bccomp($m->net, '0', 2) === -1 ? 'money-neg' : 'money-pos' }}">
                        @inr($m->net)
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Spec section 20: the grid that reveals behaviour over time. --}}
<div class="card">
    <div class="card-header">Spending by category</div>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 align-middle">
            <thead>
                <tr>
                    <th>Category</th>
                    @foreach ($grid['months'] as $m)
                        <th class="text-end">{{ $m->short }}</th>
                    @endforeach
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($grid['rows'] as $row)
                <tr>
                    <td class="fw-medium">{{ $row['label'] }}</td>
                    @foreach ($grid['months'] as $m)
                        @php($value = $row['values'][$m->month] ?? '0.00')
                        <td class="text-end money {{ bccomp($value, '0', 2) === 0 ? 'text-body-secondary' : '' }}">
                            {{ bccomp($value, '0', 2) === 0 ? '—' : \App\Support\Money::compact($value) }}
                        </td>
                    @endforeach
                    <td class="text-end money fw-semibold">@inrc($row['total'])</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($grid['months']) + 2 }}" class="empty-state">
                        No categorised spending in this period.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white small text-body-secondary">
        Amounts are shortened (K = thousand, L = lakh). Click a month above for its full breakdown.
    </div>
</div>

@endunless
@endsection

@push('scripts')
@if ($hasData)
<script>
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: @json($series->pluck('label')),
        datasets: [
            {
                label: 'Received',
                data: @json($series->pluck('income')->map(fn ($v) => (float) $v)),
                borderColor: '#59a14f',
                backgroundColor: 'rgba(89,161,79,.08)',
                fill: true,
                tension: .3,
            },
            {
                label: 'Spent',
                data: @json($series->pluck('spending')->map(fn ($v) => (float) $v)),
                borderColor: '#e15759',
                backgroundColor: 'rgba(225,87,89,.08)',
                fill: true,
                tension: .3,
            },
            {
                label: 'On cards',
                data: @json($series->pluck('card_spending')->map(fn ($v) => (float) $v)),
                borderColor: '#f28e2c',
                borderDash: [4, 4],
                fill: false,
                tension: .3,
            },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: { y: { beginAtZero: true, ticks: { callback: v => '₹' + v.toLocaleString('en-IN') } } },
        plugins: { legend: { position: 'bottom' } },
    },
});
</script>
@endif
@endpush
