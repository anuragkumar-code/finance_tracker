@extends('layouts.app')

@section('title', 'Compare months')
@section('heading', 'Compare months')
@section('subheading', $left->format('F Y').' against '.$right->format('F Y'))

@section('actions')
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input type="month" name="left" value="{{ $left->format('Y-m') }}"
               class="h-9 rounded-md border border-input bg-card px-3 text-sm shadow-xs
                      focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
        <span class="text-sm text-muted-foreground">vs</span>
        <input type="month" name="right" value="{{ $right->format('Y-m') }}"
               class="h-9 rounded-md border border-input bg-card px-3 text-sm shadow-xs
                      focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
        <input type="hidden" name="window" value="{{ $window }}">
        <x-ui.button type="submit" variant="outline">Go</x-ui.button>
    </form>
    <x-ui.button :href="route('reports.index')" variant="outline">This month</x-ui.button>
@endsection

@section('content')
@php
    $leftTotal = $totals['left'];
    $rightTotal = $totals['right'];
    $delta = bcsub($leftTotal, $rightTotal, 2);
    $rose = bccomp($delta, '0', 2) === 1;
    $absoluteDelta = $rose ? $delta : bcsub('0', $delta, 2);
    $deltaTone = $rose ? 'expense' : 'income';
@endphp

<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="segmented">
        <a href="{{ route('reports.compare', ['left' => $left->format('Y-m'), 'right' => $right->format('Y-m')]) }}"
           @if ($window === 'full') aria-current="page" @endif>Whole months</a>
        <a href="{{ route('reports.compare', ['left' => $left->format('Y-m'), 'right' => $right->format('Y-m'), 'window' => 'aligned']) }}"
           @if ($window === 'aligned') aria-current="page" @endif>First {{ $alignedDays }} days of each</a>
    </div>

    @if ($window === 'aligned')
        <p class="text-xs text-muted-foreground">
            Comparing like with like while {{ $left->format('F') }} is still running.
        </p>
    @endif
</div>

@unless ($comparable)
    <div class="mt-4">
        <x-ui.alert variant="warning" title="One of these months is only part-recorded">
            Entries start on {{ \Illuminate\Support\Carbon::parse($ledgerStart)->format('d M Y') }}, so anything
            before that is missing rather than genuinely lower. Read the differences with that in mind.
        </x-ui.alert>
    </div>
@endunless

<div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    <x-ui.stat :label="$left->format('F')" icon="trending-down" :value="\App\Support\Money::inr($leftTotal)"
        :hint="$window === 'aligned' ? 'First '.$alignedDays.' days' : 'Whole month'" />

    <x-ui.stat :label="$right->format('F')" icon="trending-down" :value="\App\Support\Money::inr($rightTotal)"
        :hint="$window === 'aligned' ? 'First '.$alignedDays.' days' : 'Whole month'" />

    <x-ui.stat label="Difference" :tone="$deltaTone"
        :value="($rose ? '+' : '−').\App\Support\Money::inr($absoluteDelta)"
        :hint="$rose ? 'More than '.$right->format('F') : 'Less than '.$right->format('F')" />

    <x-ui.stat label="Committed share" icon="calendar-clock"
        :value="$splits['left']['committed_share'].'%'"
        :hint="'Was '.$splits['right']['committed_share'].'% in '.$right->format('F')" />
</div>

<x-ui.card class="mt-4">
    <x-ui.card-header title="Category by category" description="Sorted by how much the figure moved" />
    <x-ui.card-content flush>
        @if ($rows->isEmpty())
            <x-ui.empty-state icon="chart-line" title="Nothing recorded in either month" />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Category</th>
                            <th scope="col" class="num">{{ $left->format('M') }}</th>
                            <th scope="col" class="num">{{ $right->format('M') }}</th>
                            <th scope="col" class="num">Difference</th>
                            <th scope="col" class="hidden num sm:table-cell">Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $rowRose = bccomp($row->delta, '0', 2) === 1;
                                $rowFlat = bccomp($row->delta, '0', 2) === 0;
                                $rowAbsolute = $rowRose ? $row->delta : bcsub('0', $row->delta, 2);
                                $rowTone = $rowFlat ? 'muted' : ($rowRose ? 'expense' : 'income');
                                $rowLink = route('transactions.index', [
                                    'start' => $periods['left'][0], 'end' => $periods['left'][1],
                                    'type' => 'expense', 'category_id' => $row->category_id,
                                ]);
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ $rowLink }}" class="font-medium hover:underline">{{ $row->label }}</a>
                                    @if (bccomp($row->before, '0', 2) === 0)
                                        <x-ui.badge variant="info" class="ml-1">New</x-ui.badge>
                                    @elseif (bccomp($row->now, '0', 2) === 0)
                                        <x-ui.badge variant="secondary" class="ml-1">Stopped</x-ui.badge>
                                    @endif
                                </td>
                                <td class="num"><x-finance.money :amount="$row->now" tone="strong" /></td>
                                <td class="num"><x-finance.money :amount="$row->before" tone="muted" /></td>
                                <td class="num">
                                    @if ($rowFlat)
                                        <span class="text-sm text-subtle">—</span>
                                    @else
                                        <span class="inline-flex items-center gap-1">
                                            <x-ui.icon :name="$rowRose ? 'arrow-up' : 'arrow-down'" class="size-3" />
                                            <x-finance.money :amount="$rowAbsolute" :tone="$rowTone" />
                                        </span>
                                    @endif
                                </td>
                                <td class="hidden num sm:table-cell">
                                    @if ($row->percent === null)
                                        <span class="text-xs text-subtle">—</span>
                                    @else
                                        <span class="text-xs tabular {{ $rowRose ? 'text-expense' : 'text-income' }}">
                                            {{ $rowRose ? '+' : '' }}{{ $row->percent }}%
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card-content>
    <x-ui.card-footer>
        A category is "new" when nothing was filed under it in {{ $right->format('F') }}, and "stopped"
        when nothing was this time. Investments are counted separately and never appear here.
    </x-ui.card-footer>
</x-ui.card>

<x-ui.card class="mt-4">
    <x-ui.card-header title="How you paid" :description="$left->format('F').' against '.$right->format('F')" />
    <x-ui.card-content flush>
        <ul class="divide-y divide-border">
            @foreach ($modes as $mode)
                @php
                    $modeRose = bccomp($mode->delta, '0', 2) === 1;
                    $modeAbsolute = $modeRose ? $mode->delta : bcsub('0', $mode->delta, 2);
                    $modeTone = bccomp($mode->delta, '0', 2) === 0 ? 'muted' : ($modeRose ? 'expense' : 'income');
                @endphp
                <li class="flex items-center gap-3 px-5 py-2.5">
                    <span class="min-w-0 flex-1 truncate text-sm">{{ $mode->label }}</span>
                    <x-finance.money :amount="$mode->now" tone="strong" class="w-28 text-right text-sm" />
                    <x-finance.money :amount="$mode->before" tone="muted" class="w-28 text-right text-sm" />
                    <x-finance.money :amount="$modeAbsolute" :tone="$modeTone" class="w-24 text-right text-sm" />
                </li>
            @endforeach
        </ul>
    </x-ui.card-content>
</x-ui.card>
@endsection
