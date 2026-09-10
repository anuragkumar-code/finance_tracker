@extends('layouts.app')

@section('title', 'Upcoming')
@section('heading', 'What is coming')
@section('subheading', 'Your bank balance minus what is already committed')

@section('actions')
    <div class="segmented">
        @foreach ([7, 30, 60, 90] as $option)
            <a href="{{ route('upcoming.index', ['days' => $option]) }}"
               @if ($days === $option) aria-current="page" @endif>
                {{ $option }}d
            </a>
        @endforeach
    </div>
@endsection

@section('content')

{{-- Spec section 21: the figure that matters more than the bank balance. --}}
<x-ui.card>
    <x-ui.card-content class="!py-6">
        <div class="grid items-center gap-5 sm:grid-cols-[1fr_auto_1fr_auto_1.2fr]">
            <div>
                <p class="text-xs font-medium text-muted-foreground">In bank + cash</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight tabular">
                    {{ \App\Support\Money::inr($reality['available']) }}
                </p>
            </div>

            <span class="hidden text-xl text-muted-foreground sm:block" aria-hidden="true">−</span>

            <div>
                <p class="text-xs font-medium text-muted-foreground">Committed ({{ $days }} days)</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight tabular text-expense">
                    {{ \App\Support\Money::inr($reality['committed']) }}
                </p>
                @if (bccomp($reality['estimated_portion'], '0', 2) === 1)
                    <p class="text-xs text-muted-foreground">
                        includes {{ \App\Support\Money::inr($reality['estimated_portion']) }} estimated
                    </p>
                @endif
            </div>

            <span class="hidden text-xl text-muted-foreground sm:block" aria-hidden="true">=</span>

            <div>
                <p class="text-xs font-medium text-muted-foreground">Realistically available</p>
                <p class="mt-1 text-3xl font-semibold tracking-tight tabular
                          {{ $reality['is_negative'] ? 'text-expense' : 'text-income' }}">
                    {{ \App\Support\Money::inr($reality['realistic']) }}
                </p>
                @if ($reality['is_negative'])
                    <p class="text-xs text-destructive">
                        Commitments over the next {{ $days }} days exceed what you hold.
                    </p>
                @endif
            </div>
        </div>
    </x-ui.card-content>
</x-ui.card>

@if ($overdue->isNotEmpty())
    <div class="mt-4">
        <x-ui.alert variant="warning" :title="$overdue->count().' commitment(s) are past due and not yet recorded'">
            {{ $overdue->map(fn ($i) => $i->label.' ('.$i->due_date->format('d M').')')->implode(', ') }}
        </x-ui.alert>
    </div>
@endif

<div class="mt-4 grid gap-4 lg:grid-cols-12">
    <div class="lg:col-span-8">
        <x-ui.card>
            <x-ui.card-header :title="'Next '.$days.' days'">
                <x-slot:action>
                    <span class="text-sm text-muted-foreground">
                        next 7 days: <span class="tabular">{{ \App\Support\Money::inr($next7) }}</span>
                    </span>
                </x-slot:action>
            </x-ui.card-header>
            <x-ui.card-content flush>
                @if ($obligations->isEmpty())
                    <x-ui.empty-state icon="calendar-clock" :title="'Nothing committed in the next '.$days.' days'"
                        description="Add loans or recurring commitments to see them here.">
                        <x-ui.button :href="route('loans.index')" variant="outline" size="sm">Loans</x-ui.button>
                        <x-ui.button :href="route('recurring.index')" variant="outline" size="sm">Recurring</x-ui.button>
                    </x-ui.empty-state>
                @else
                    {{-- Grouped by date rather than a flat table: what matters is
                         which day money leaves, not the order rows were created. --}}
                    @foreach ($obligations->groupBy(fn ($o) => $o->due_date->toDateString()) as $date => $items)
                        @php($day = \Carbon\Carbon::parse($date))
                        <div class="border-b border-border last:border-0">
                            <div class="flex items-baseline justify-between gap-3 bg-muted/50 px-5 py-2">
                                <p class="text-xs font-semibold text-foreground">
                                    {{ $day->format('d M') }}
                                    <span class="ml-1 font-normal text-muted-foreground">
                                        {{ $day->isToday() ? 'today' : $day->diffForHumans(['short' => true]) }}
                                    </span>
                                </p>
                                <x-finance.money :amount="$items->reduce(fn ($c, $i) => bcadd($c, $i->amount, 2), '0.00')"
                                    tone="muted" class="text-xs" />
                            </div>
                            <ul class="divide-y divide-border">
                                @foreach ($items as $item)
                                    <li class="flex items-center gap-3 px-5 py-3">
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ $item->url }}" class="text-sm font-medium hover:underline">
                                                {{ $item->label }}
                                            </a>
                                            <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                                <x-ui.badge variant="secondary">
                                                    {{ match ($item->source) {
                                                        'loan' => 'EMI',
                                                        'credit_card' => 'Card bill',
                                                        default => 'Recurring',
                                                    } }}
                                                </x-ui.badge>
                                                @if ($item->is_estimated)
                                                    {{-- Forecasts are labelled so they are never read
                                                         as a fixed, known amount. --}}
                                                    <x-ui.badge variant="outline">Estimate</x-ui.badge>
                                                @endif
                                                @if ($item->detail)
                                                    <span class="text-xs text-muted-foreground">{{ $item->detail }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <x-finance.money :amount="$item->amount" tone="strong" class="text-sm" />
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach

                    <div class="flex items-center justify-between border-t border-border px-5 py-2.5">
                        <span class="text-xs font-medium text-muted-foreground">Total committed</span>
                        <x-finance.money :amount="$reality['committed']" tone="strong" class="text-sm" />
                    </div>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    </div>

    <div class="space-y-4 lg:col-span-4">
        <x-ui.card>
            <x-ui.card-header title="By type" />
            <x-ui.card-content flush>
                @php($grouped = $obligations->groupBy('source'))
                @if ($grouped->isEmpty())
                    <x-ui.empty-state icon="chart-pie" title="Nothing to break down" />
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($grouped as $source => $items)
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                                <div>
                                    <p class="text-sm">
                                        {{ match ($source) {
                                            'loan' => 'Loan EMIs',
                                            'credit_card' => 'Card bills',
                                            default => 'Recurring bills',
                                        } }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">{{ $items->count() }} item(s)</p>
                                </div>
                                <x-finance.money :amount="$items->reduce(fn ($c, $i) => bcadd($c, $i->amount, 2), '0.00')"
                                    class="text-sm" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.alert variant="muted" icon="info" title="Why this differs from your bank balance">
            Your bank shows what you hold today. This shows what is left once money you have
            already committed goes out. Amounts marked <em>estimate</em> are forecasts from
            recurring commitments and may change — EMIs and issued card bills are fixed.
        </x-ui.alert>
    </div>
</div>
@endsection
