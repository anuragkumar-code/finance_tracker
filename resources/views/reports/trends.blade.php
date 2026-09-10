@extends('layouts.app')

@section('title', 'Trends')
@section('heading', 'How things are changing')
@section('subheading', 'Last '.$months.' months side by side')

@section('actions')
    <div class="inline-flex rounded-lg border border-border bg-muted p-0.5">
        @foreach ([3, 6, 12] as $option)
            <a href="{{ route('reports.trends', ['months' => $option]) }}"
               @if ($months === $option) aria-current="page" @endif
               class="rounded-md px-3 py-1.5 text-sm transition-colors
                      {{ $months === $option ? 'bg-card font-medium text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground' }}">
                {{ $option }}m
            </a>
        @endforeach
    </div>
    <x-ui.button :href="route('reports.index')" variant="outline">This month</x-ui.button>
@endsection

@section('content')
@php($hasData = $series->contains(fn ($m) => bccomp($m->spending, '0', 2) === 1 || bccomp($m->income, '0', 2) === 1))

@unless ($hasData)
    <x-ui.card>
        <x-ui.empty-state icon="chart-line" title="Not enough history yet"
            description="Trends need a few months of entries before they say anything useful. Keep recording and this page will start showing patterns." />
    </x-ui.card>
@else

<x-ui.card>
    <x-ui.card-header title="Money in vs money out" />
    <x-ui.card-content>
        <div class="h-64"><canvas id="trendChart"></canvas></div>
    </x-ui.card-content>
</x-ui.card>

<x-ui.card class="mt-4">
    <x-ui.card-header title="Month by month" />
    <x-ui.card-content flush>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left">
                        <th scope="col" class="px-5 py-2.5 text-xs font-medium text-muted-foreground">Month</th>
                        <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">Received</th>
                        <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">Spent</th>
                        <th scope="col" class="hidden px-5 py-2.5 text-right text-xs font-medium text-muted-foreground sm:table-cell">On cards</th>
                        <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">Left over</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                @foreach ($series as $m)
                    <tr class="transition-colors hover:bg-muted/60">
                        <td class="px-5 py-3">
                            <a href="{{ route('reports.index', ['month' => $m->month]) }}"
                               class="hover:underline">{{ $m->label }}</a>
                        </td>
                        <td class="px-5 py-3 text-right"><x-finance.money :amount="$m->income" tone="income" /></td>
                        <td class="px-5 py-3 text-right"><x-finance.money :amount="$m->spending" /></td>
                        <td class="hidden px-5 py-3 text-right sm:table-cell">
                            <x-finance.money :amount="$m->card_spending" tone="muted" />
                        </td>
                        <td class="px-5 py-3 text-right">
                            <x-finance.money :amount="$m->net" :tone="bccomp($m->net, '0', 2) === -1 ? 'expense' : 'income'" />
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card-content>
</x-ui.card>

{{-- Spec section 20: the grid that reveals behaviour over time. --}}
<x-ui.card class="mt-4">
    <x-ui.card-header title="Spending by category" />
    <x-ui.card-content flush>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left">
                        <th scope="col" class="px-5 py-2.5 text-xs font-medium text-muted-foreground">Category</th>
                        @foreach ($grid['months'] as $m)
                            <th scope="col" class="px-3 py-2.5 text-right text-xs font-medium text-muted-foreground">{{ $m->short }}</th>
                        @endforeach
                        <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                @forelse ($grid['rows'] as $row)
                    <tr class="transition-colors hover:bg-muted/60">
                        <td class="px-5 py-2.5 font-medium">{{ $row['label'] }}</td>
                        @foreach ($grid['months'] as $m)
                            @php($value = $row['values'][$m->month] ?? '0.00')
                            <td class="px-3 py-2.5 text-right tabular
                                       {{ bccomp($value, '0', 2) === 0 ? 'text-muted-foreground' : '' }}">
                                {{ bccomp($value, '0', 2) === 0 ? '—' : \App\Support\Money::compact($value) }}
                            </td>
                        @endforeach
                        <td class="px-5 py-2.5 text-right font-medium tabular">
                            {{ \App\Support\Money::compact($row['total']) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($grid['months']) + 2 }}">
                            <x-ui.empty-state icon="inbox" title="No categorised spending in this period" />
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card-content>
    <x-ui.card-footer>
        Amounts are shortened (K = thousand, L = lakh). Click a month above for its full breakdown.
    </x-ui.card-footer>
</x-ui.card>

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
                borderColor: 'oklch(0.55 0.13 152)',
                backgroundColor: 'oklch(0.55 0.13 152 / 0.08)',
                fill: true, tension: 0.3, borderWidth: 2,
                pointRadius: 3, pointHoverRadius: 5,
            },
            {
                label: 'Spent',
                data: @json($series->pluck('spending')->map(fn ($v) => (float) $v)),
                borderColor: 'oklch(0.58 0.16 27)',
                backgroundColor: 'oklch(0.58 0.16 27 / 0.08)',
                fill: true, tension: 0.3, borderWidth: 2,
                pointRadius: 3, pointHoverRadius: 5,
            },
            {
                label: 'On cards',
                data: @json($series->pluck('card_spending')->map(fn ($v) => (float) $v)),
                borderColor: 'oklch(0.68 0.15 70)',
                borderDash: [4, 4], fill: false, tension: 0.3, borderWidth: 2,
                pointRadius: 0, pointHoverRadius: 4,
            },
        ],
    },
    options: {
        interaction: { mode: 'index', intersect: false },
        scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: {
                beginAtZero: true,
                border: { display: false },
                grid: { color: 'oklch(0.923 0.005 248)' },
                ticks: { callback: (v) => window.ftMoney(v) },
            },
        },
        plugins: {
            legend: { display: true, position: 'bottom', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true } },
            tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + window.ftMoney(c.parsed.y) } },
        },
    },
});
</script>
@endif
@endpush
