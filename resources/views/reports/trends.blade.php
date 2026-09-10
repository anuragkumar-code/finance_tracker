@extends('layouts.app')

@section('title', 'Trends')
@section('heading', 'How things are changing')
@section('subheading', 'Last '.$months.' months side by side')

@section('actions')
    <div class="segmented">
        @foreach ([3, 6, 12] as $option)
            <a href="{{ route('reports.trends', ['months' => $option]) }}"
               @if ($months === $option) aria-current="page" @endif>
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
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Month</th>
                        <th scope="col" class="num">Received</th>
                        <th scope="col" class="num">Spent</th>
                        <th scope="col" class="hidden num sm:table-cell">On cards</th>
                        <th scope="col" class="num">Left over</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($series as $m)
                    <tr>
                        <td>
                            <a href="{{ route('reports.index', ['month' => $m->month]) }}"
                               class="hover:underline">{{ $m->label }}</a>
                        </td>
                        <td class="num"><x-finance.money :amount="$m->income" tone="income" /></td>
                        <td class="num"><x-finance.money :amount="$m->spending" /></td>
                        <td class="hidden num sm:table-cell">
                            <x-finance.money :amount="$m->card_spending" tone="muted" />
                        </td>
                        <td class="num">
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
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Category</th>
                        @foreach ($grid['months'] as $m)
                            <th scope="col" class="px-3 num">{{ $m->short }}</th>
                        @endforeach
                        <th scope="col" class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($grid['rows'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        @foreach ($grid['months'] as $m)
                            @php($value = $row['values'][$m->month] ?? '0.00')
                            <td class="px-3 num tabular {{ bccomp($value, '0', 2) === 0 ? 'text-muted-foreground' : '' }}">
                                {{ bccomp($value, '0', 2) === 0 ? '—' : \App\Support\Money::compact($value) }}
                            </td>
                        @endforeach
                        <td class="num tabular">
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
document.addEventListener('DOMContentLoaded', function () {
    // Card spending is drawn as a plain line rather than a filled area: it is
    // a subset of "Spent", and filling it would imply a third, separate pot.
    ftChart.area(document.getElementById('trendChart'), {
        labels: @json($series->pluck('label')),
        datasets: [
            { label: 'Received', data: @json($series->pluck('income')->map(fn ($v) => (float) $v)), color: ftChart.colors.income },
            { label: 'Spent', data: @json($series->pluck('spending')->map(fn ($v) => (float) $v)), color: ftChart.colors.expense },
            { label: 'On cards', data: @json($series->pluck('card_spending')->map(fn ($v) => (float) $v)), color: ftChart.colors.debt, fill: false },
        ],
    });
});
</script>
@endif
@endpush
