@extends('layouts.app')

@section('title', $event->name)
@section('heading', $event->name)
@section('subheading', $event->dateRange().' · '.$event->kind->label())

@section('actions')
    <x-ui.button :href="route('events.edit', $event)" variant="outline" icon="pencil">Edit</x-ui.button>
    <x-ui.button :href="route('quick-entry', ['event' => $event->id])" variant="outline" icon="plus">Add a spend</x-ui.button>
    <x-ui.button icon="split" x-on:click="$dispatch('open-dialog', 'settle-up')">Settle up</x-ui.button>
@endsection

@section('content')
@php
    $hasSettlements = $event->settlements->isNotEmpty();
    $costHint = $hasSettlements
        ? 'After friends\' shares'
        : 'Everything filed under this '.strtolower($event->kind->label());
    $pocketHint = bccomp($theyOwe, '0', 2) === 1
        ? 'Includes '.\App\Support\Money::inr($theyOwe).' you covered for friends'
        : 'From your own accounts';
@endphp

@if ($event->is_archived)
    <div class="mb-4">
        <x-ui.alert variant="muted" icon="archive" title="Archived">
            It is hidden from Quick Entry. Edit it to bring it back.
        </x-ui.alert>
    </div>
@endif

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    <x-ui.stat label="Your cost" icon="plane" :value="\App\Support\Money::inr($ourCost)" :hint="$costHint" />
    <x-ui.stat label="Paid out of pocket" icon="wallet" :value="\App\Support\Money::inr($paidOutOfPocket)" :hint="$pocketHint" />
    <x-ui.stat label="Friends owe you" icon="hand-coins" tone="income" :value="\App\Support\Money::inr($theyOwe)"
        hint="From settling this up" />
    <x-ui.stat label="You owe friends" icon="hand-coins" tone="debt" :value="\App\Support\Money::inr($weOwe)"
        hint="From settling this up" />
</div>

<div class="mt-4 grid items-start gap-4 lg:grid-cols-12">
    <div class="space-y-4 lg:col-span-8">
        @include('events._entries')
    </div>

    <div class="space-y-4 lg:col-span-4">
        @include('events._settlements')
        @include('events._breakdown')
    </div>
</div>

@include('events._settle-dialog')
@endsection
