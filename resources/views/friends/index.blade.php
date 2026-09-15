@extends('layouts.app')

@section('title', 'Friends')
@section('heading', 'Friends')
@section('subheading', 'Who owes you, whom you owe — and none of it counted as income or spending twice')

@section('content')
@php
    $withBalance = $rows->filter(fn ($r) => bccomp($r->balance, '0', 2) !== 0);
    $square = $rows->filter(fn ($r) => bccomp($r->balance, '0', 2) === 0);
@endphp

<div class="grid grid-cols-2 gap-3 lg:gap-4">
    <x-ui.stat label="Friends owe you" icon="hand-coins" tone="income"
        :value="\App\Support\Money::inr($owedToUs)" hint="Money you are waiting to get back" />
    <x-ui.stat label="You owe friends" icon="hand-coins" tone="debt"
        :value="\App\Support\Money::inr($weOwe)" hint="Money you still have to pay back" />
</div>

<div class="mt-4 grid items-start gap-4 lg:grid-cols-12">
    <div class="space-y-4 lg:col-span-8">
        <x-ui.card>
            <x-ui.card-header title="Balances" description="Settle a trip or split a bill and they appear here" />

            @if ($rows->isEmpty())
                <x-ui.empty-state icon="users" title="No friends added yet"
                    description="Add the people you share costs with. They stay separate from the household, so they never show up as who paid or who it was for." />
            @else
                <ul class="divide-y divide-border">
                    @foreach ($withBalance->concat($square) as $row)
                        @include('friends._row', ['row' => $row])
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <div class="space-y-4 lg:col-span-4">
        <x-ui.card>
            <x-ui.card-header title="Add a friend" />
            <form method="POST" action="{{ route('friends.store') }}">
                @csrf
                <x-ui.card-content class="space-y-4">
                    <x-ui.input label="Name" name="name" :value="old('name')" required placeholder="Rahul" />
                    <x-ui.input label="Relationship" name="relationship" :value="old('relationship')"
                        placeholder="college friend, cousin…" hint="Optional" />
                    <x-ui.button type="submit" class="w-full" icon="user-plus">Add friend</x-ui.button>
                </x-ui.card-content>
            </form>
        </x-ui.card>

        <x-ui.alert variant="muted" icon="info" title="How this stays honest">
            When you pay for a friend, their share is a loan, not your spending — it sits here
            until they pay it back. Their repayment moves money into your account without
            counting as income. If it is never coming back, write it off and it becomes your
            spending after all.
        </x-ui.alert>
    </div>
</div>
@endsection
