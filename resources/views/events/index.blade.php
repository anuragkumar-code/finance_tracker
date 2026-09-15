@extends('layouts.app')

@section('title', 'Trips & events')
@section('heading', 'Trips & events')
@section('subheading', 'What each trip and occasion really cost, after friends paid their share')

@section('actions')
    <x-ui.button :href="route('friends.index')" variant="outline" icon="hand-coins">Friends</x-ui.button>
    <x-ui.button :href="route('events.create')" icon="plus">New trip or event</x-ui.button>
@endsection

@section('content')
@php
    $thisYear = $holidayByYear->firstWhere('year', now()->year);
    $lastYear = $holidayByYear->firstWhere('year', now()->year - 1);

    $thisYearAmount = $thisYear?->amount ?? '0.00';
    $lastYearHint = $lastYear
        ? \App\Support\Money::inr($lastYear->amount).' in '.$lastYear->year
        : 'Nothing recorded last year';

    $openEvents = $events->reject->is_archived;
    $archived = $events->filter->is_archived;
    $archivedHint = $archived->isNotEmpty() ? $archived->count().' archived' : 'None archived';
@endphp

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    <x-ui.stat :label="'Holidays in '.now()->year" icon="plane"
        :value="\App\Support\Money::inr($thisYearAmount)" :hint="$lastYearHint" />

    <x-ui.stat label="Trips & events" icon="calendar-days"
        :value="(string) $openEvents->count()"
        :hint="$archivedHint" />

    <x-ui.stat label="Friends owe you" icon="hand-coins" tone="income"
        :value="\App\Support\Money::inr($owedToUs)" :href="route('friends.index')"
        hint="Waiting to be paid back" />

    <x-ui.stat label="You owe friends" icon="hand-coins" tone="debt"
        :value="\App\Support\Money::inr($weOwe)" :href="route('friends.index')"
        hint="Still to pay back" />
</div>

@if ($current)
    <div class="mt-4">
        <x-ui.alert variant="info" icon="plane" :title="'You are on '.$current->name">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span>Quick Entry files every spend under it until {{ ($current->end_date ?? $current->start_date)->format('d M') }}.</span>
                <div class="flex gap-2">
                    <x-ui.button :href="route('quick-entry')" size="sm" icon="plus">Record a spend</x-ui.button>
                    <x-ui.button :href="route('events.show', $current)" size="sm" variant="outline">Open</x-ui.button>
                </div>
            </div>
        </x-ui.alert>
    </div>
@endif

@if ($events->isEmpty())
    <x-ui.card class="mt-4">
        <x-ui.empty-state icon="plane" title="No trips or events yet"
            description="Create one before you go, and every spend during it is filed under it. Afterwards, settle up with the friends who came so only your share counts as spending.">
            <x-ui.button :href="route('events.create')" icon="plus">Create your first trip</x-ui.button>
        </x-ui.empty-state>
    </x-ui.card>
@else
    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($openEvents as $event)
            @include('events._card', ['event' => $event])
        @endforeach
    </div>

    @if ($archived->isNotEmpty())
        <details class="group mt-6">
            <summary class="flex cursor-pointer list-none items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground">
                <x-ui.icon name="chevron-right" class="size-4 transition-transform group-open:rotate-90" />
                Archived ({{ $archived->count() }})
            </summary>
            <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($archived as $event)
                    @include('events._card', ['event' => $event])
                @endforeach
            </div>
        </details>
    @endif

    @if ($holidayByYear->count() > 1)
        <x-ui.card class="mt-6">
            <x-ui.card-header title="Holidays by year" description="Everything filed under the Holiday category" />
            <x-ui.card-content flush>
                <ul class="divide-y divide-border">
                    @foreach ($holidayByYear as $year)
                        <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                            <span class="text-sm font-medium tabular">{{ $year->year }}</span>
                            <span class="flex items-baseline gap-4">
                                <span class="text-xs text-muted-foreground">
                                    {{ $year->trips }} {{ \Illuminate\Support\Str::plural('trip', $year->trips) }}
                                </span>
                                <x-finance.money :amount="$year->amount" tone="strong" class="text-sm" />
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card-content>
        </x-ui.card>
    @endif
@endif
@endsection
