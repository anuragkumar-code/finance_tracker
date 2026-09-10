@extends('layouts.app')

@section('title', 'Reports')
@section('heading', 'Where the money went')
@section('subheading', $month->format('F Y'))

@section('actions')
    <form method="GET" class="flex items-center gap-2">
        <input type="month" name="month" value="{{ $month->format('Y-m') }}"
               class="h-9 rounded-md border border-input bg-card px-3 text-sm
                      focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
        <x-ui.button type="submit" variant="outline">Go</x-ui.button>
    </form>
    <x-ui.button :href="route('reports.trends')" variant="outline">Trends</x-ui.button>
    <x-ui.button :href="route('reports.net-worth')" variant="outline">Net worth</x-ui.button>
@endsection

@section('content')
@php($base = ['start' => $start, 'end' => $end])

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    <x-ui.stat label="Received" icon="trending-up" tone="income" :value="\App\Support\Money::inr($income)" />

    <x-ui.stat label="Spent" icon="trending-down" :value="\App\Support\Money::inr($spending)"
        :hint="bccomp($debtRepayment, '0', 2) === 1
            ? 'incl. '.\App\Support\Money::inr($debtRepayment).' loan EMIs' : null" />

    <x-ui.stat label="Left over"
        :tone="bccomp($surplus, '0', 2) === -1 ? 'expense' : 'income'"
        :value="\App\Support\Money::inr($surplus)"
        :hint="$savingsRate !== null ? $savingsRate.'% of what came in' : null" />

    {{-- Cash outflow is deliberately not the same as spending. --}}
    <x-ui.stat label="Left your accounts" icon="arrow-up-right"
        :value="\App\Support\Money::inr($cashOutflow)" hint="incl. transfers &amp; card bills" />
</div>

@if (bccomp($spending, '0', 2) !== 1 && bccomp($income, '0', 2) !== 1)
    <x-ui.card class="mt-4">
        <x-ui.empty-state icon="chart-line" :title="'Nothing recorded for '.$month->format('F Y')"
            description="Once you start entering spending, this page breaks it down every way.">
            <x-ui.button :href="route('quick-entry')" icon="plus">Record a spend</x-ui.button>
        </x-ui.empty-state>
    </x-ui.card>
@else

<x-ui.card class="mt-4">
    <x-ui.card-header title="Week by week" description="How spending falls across the month" />
    <x-ui.card-content>
        <div class="grid gap-5 lg:grid-cols-[1fr_18rem]">
            <div class="h-52"><canvas id="weeklyChart"></canvas></div>
            <ul class="divide-y divide-border">
                @foreach ($weekly as $week)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <a href="{{ route('transactions.index', ['start' => $week->start, 'end' => $week->end]) }}"
                           class="min-w-0">
                            <span class="text-sm hover:underline">{{ $week->label }}</span>
                            <span class="block text-xs text-muted-foreground">{{ $week->range }}</span>
                        </a>
                        <span class="shrink-0 text-right">
                            <x-finance.money :amount="$week->spending" class="block text-sm" />
                            @if (bccomp($week->income, '0', 2) === 1)
                                <x-finance.money :amount="$week->income" tone="income" class="block text-xs" />
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </x-ui.card-content>
</x-ui.card>

<div class="mt-4 grid gap-4 lg:grid-cols-12">
    <div class="lg:col-span-7">
        <x-ui.card class="h-full">
            <x-ui.card-header title="By category" />
            <x-ui.card-content>
                <div class="grid items-center gap-5 sm:grid-cols-[minmax(0,11rem)_1fr]">
                    <div class="relative mx-auto h-44 w-44">
                        <canvas id="categoryChart"></canvas>
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-[0.6875rem] text-muted-foreground">Total</span>
                            <span class="text-sm font-semibold tabular">{{ \App\Support\Money::compact($spending) }}</span>
                        </div>
                    </div>
                    <ul class="space-y-1">
                        @foreach ($byCategory as $i => $row)
                            @php($share = bccomp($spending, '0', 2) === 1 ? round($row->amount / $spending * 100) : 0)
                            <li>
                                <a href="{{ route('transactions.index', $base + ['type' => 'expense', 'category_id' => $row->category_id]) }}"
                                   class="group flex items-center gap-2.5 rounded-md px-1.5 py-1 hover:bg-muted">
                                    <span class="size-2 shrink-0 rounded-full" data-swatch="{{ $i }}"></span>
                                    <span class="min-w-0 flex-1 truncate text-sm group-hover:underline">{{ $row->label }}</span>
                                    <span class="text-xs text-muted-foreground tabular">{{ $share }}%</span>
                                    <x-finance.money :amount="$row->amount" class="w-20 text-right text-sm" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </x-ui.card-content>
        </x-ui.card>
    </div>

    <div class="lg:col-span-5">
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
<x-ui.card class="mt-4">
    <x-ui.card-header title="How you bought it">
        <x-slot:action>
            <span class="text-sm text-muted-foreground">
                online &amp; delivery:
                <span class="font-medium text-foreground tabular">{{ \App\Support\Money::inr($online['total_online']) }}</span>
            </span>
        </x-slot:action>
    </x-ui.card-header>
    <x-ui.card-content>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['label' => 'Quick commerce', 'value' => $online['quick_commerce'], 'hint' => 'Blinkit, Zepto, Instamart'],
                ['label' => 'Online shopping', 'value' => $online['ecommerce'], 'hint' => 'Amazon, Flipkart, Myntra'],
                ['label' => 'Food delivery', 'value' => $online['food_delivery'], 'hint' => 'Swiggy, Zomato'],
            ] as $tile)
                <div class="rounded-lg border border-border bg-muted/40 px-4 py-3">
                    <p class="text-xs font-medium text-muted-foreground">{{ $tile['label'] }}</p>
                    <p class="mt-1 text-lg font-semibold tabular">{{ \App\Support\Money::inr($tile['value']) }}</p>
                    <p class="text-xs text-muted-foreground">{{ $tile['hint'] }}</p>
                </div>
            @endforeach
        </div>

        @if ($byChannel->isNotEmpty())
            <ul class="mt-4 divide-y divide-border border-t border-border">
                @foreach ($byChannel as $row)
                    @php($share = bccomp($spending, '0', 2) === 1 ? round($row->amount / $spending * 100) : 0)
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <x-ui.badge :variant="match($row->key) {
                                'quick_commerce' => 'destructive',
                                'ecommerce' => 'default',
                                'food_delivery' => 'warning',
                                'subscription' => 'info',
                                default => 'secondary',
                            }">{{ $row->label }}</x-ui.badge>
                            @if ($row->hint)
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ $row->hint }}</p>
                            @endif
                        </div>
                        <span class="flex shrink-0 items-baseline gap-3">
                            <span class="text-xs text-muted-foreground">{{ $row->count }} entries</span>
                            <span class="text-xs text-muted-foreground tabular">{{ $share }}%</span>
                            <x-finance.money :amount="$row->amount" class="w-24 text-right text-sm" />
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card-content>
    <x-ui.card-footer>
        Channels come from the merchant. Well-known names classify themselves; the rest default to
        "in person" and can be corrected under
        <a href="{{ route('settings.merchants.index') }}"
           class="underline underline-offset-2 hover:text-foreground">Settings → Merchants</a>.
    </x-ui.card-footer>
</x-ui.card>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    @include('reports._breakdown', ['title' => 'Who paid', 'rows' => $byPayer, 'total' => $spending,
        'filterKey' => 'payer_id', 'baseFilters' => $base])
    @include('reports._breakdown', ['title' => 'Who it was for', 'rows' => $byBeneficiary, 'total' => $spending,
        'filterKey' => 'beneficiary_id', 'baseFilters' => $base])
    @include('reports._breakdown', ['title' => 'Paid with', 'rows' => $byAccount, 'total' => $spending,
        'filterKey' => 'account_id', 'baseFilters' => $base])
    @include('reports._breakdown', ['title' => 'What it was for', 'rows' => $byPurpose, 'total' => $spending,
        'filterKey' => 'purpose', 'baseFilters' => $base])
    @include('reports._breakdown', ['title' => 'By subcategory', 'rows' => $bySubcategory, 'total' => $spending,
        'filterKey' => 'category_id', 'baseFilters' => $base, 'empty' => 'No subcategories used this month.'])
    @include('reports._breakdown', ['title' => 'Top merchants', 'rows' => $byMerchant, 'total' => $spending,
        'filterKey' => 'merchant_id', 'baseFilters' => $base, 'empty' => 'No merchants recorded this month.'])
</div>

<x-ui.card class="mt-4">
    <x-ui.card-content class="text-sm text-muted-foreground">
        <span class="font-medium text-foreground">Reading these numbers.</span>
        "Spent" is what you consumed. "Left your accounts" is broader — it includes moving money
        between your own accounts and paying card bills, neither of which is spending. Card purchases
        count as spending on the day you buy; paying that card's bill later is not counted again.
    </x-ui.card-content>
</x-ui.card>

@endif
@endsection

@push('scripts')
@if (bccomp($spending, '0', 2) === 1 || bccomp($income, '0', 2) === 1)
<script>
(function () {
    new Chart(document.getElementById('weeklyChart'), {
        type: 'bar',
        data: {
            labels: @json($weekly->pluck('label')),
            datasets: [
                {
                    label: 'Received',
                    data: @json($weekly->pluck('income')->map(fn ($v) => (float) $v)),
                    backgroundColor: 'oklch(0.58 0.12 152 / 0.85)',
                    borderRadius: 4,
                    maxBarThickness: 26,
                },
                {
                    label: 'Spent',
                    data: @json($weekly->pluck('spending')->map(fn ($v) => (float) $v)),
                    backgroundColor: 'oklch(0.55 0.13 253 / 0.85)',
                    borderRadius: 4,
                    maxBarThickness: 26,
                },
            ],
        },
        options: {
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

    @if ($byCategory->isNotEmpty())
    const labels = @json($byCategory->pluck('label'));
    const data = @json($byCategory->pluck('amount')->map(fn ($a) => (float) $a));
    const colors = window.ftChartPalette.slice(0, labels.length);

    document.querySelectorAll('[data-swatch]').forEach((el) => {
        el.style.backgroundColor = colors[Number(el.dataset.swatch) % colors.length];
    });

    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 0, hoverOffset: 4 }] },
        options: {
            cutout: '72%',
            plugins: { tooltip: { callbacks: { label: (c) => c.label + ': ' + window.ftMoney(c.parsed) } } },
        },
    });
    @endif
})();
</script>
@endif
@endpush
