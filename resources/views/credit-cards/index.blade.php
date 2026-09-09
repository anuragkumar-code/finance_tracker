@extends('layouts.app')

@section('title', 'Credit Cards')
@section('heading', 'Credit Cards')
@section('subheading', 'What you owe, and what is due next')

@section('actions')
    <x-ui.button :href="route('reports.credit-cards')" variant="outline">Card report</x-ui.button>
    <x-ui.button :href="route('credit-cards.create')" icon="plus">Add card</x-ui.button>
@endsection

@section('content')

@if ($cards->isEmpty())
    <x-ui.card>
        <x-ui.empty-state icon="credit-card" title="No cards yet"
            description="Add a card with its limit, billing cycle and what you currently owe. Purchases get recorded as they happen; paying the bill later is a separate step that is never counted as spending twice.">
            <x-ui.button :href="route('credit-cards.create')" icon="plus">Add your first card</x-ui.button>
        </x-ui.empty-state>
    </x-ui.card>
@else

<div class="grid gap-3 sm:grid-cols-2 lg:gap-4">
    <x-ui.stat label="Total owed across cards" icon="credit-card" tone="debt"
        :value="\App\Support\Money::inr($totalOwed)" />

    <x-ui.stat label="Due in the next 30 days" icon="calendar-clock"
        :value="$upcomingDues->isEmpty()
            ? '—'
            : \App\Support\Money::inr($upcomingDues->reduce(fn ($c, $s) => bcadd($c, $s->balanceRemaining(), 2), '0.00'))"
        :hint="$upcomingDues->isEmpty()
            ? 'No statements awaiting payment'
            : $upcomingDues->map(fn ($d) => $d->creditCard->card_name.' by '.$d->due_date->format('d M'))->implode(', ')" />
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    @foreach ($cards as $card)
        @php
            $util = $card->utilisation();
            $tone = $util >= 70 ? 'destructive' : ($util >= 40 ? 'warning' : 'success');
        @endphp

        <x-ui.card class="{{ $card->is_active ? '' : 'opacity-60' }}">
            <x-ui.card-content class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <a href="{{ route('credit-cards.show', $card) }}"
                               class="text-base font-semibold hover:underline">{{ $card->card_name }}</a>
                            @if ($card->account->owner)
                                <x-ui.badge variant="outline">{{ $card->account->owner->name }}</x-ui.badge>
                            @endif
                            @if ($util >= 70)
                                <x-ui.badge variant="destructive">High usage</x-ui.badge>
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ $card->account->institution ?: 'Credit card' }} ·
                            bills on the {{ $card->statement_day }}, due on the {{ $card->payment_due_day }}
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-xs text-muted-foreground">Owed</p>
                        <x-finance.money :amount="$card->outstanding()" tone="debt"
                            class="text-lg font-semibold" />
                    </div>
                </div>

                <div>
                    <div class="mb-1.5 flex items-baseline justify-between text-xs">
                        <span class="text-muted-foreground">{{ $util }}% of limit used</span>
                        <span class="text-muted-foreground">
                            <x-finance.money :amount="$card->availableCredit()" tone="muted" /> available
                        </span>
                    </div>
                    <x-ui.progress :value="$util" :variant="$tone"
                        :label="$card->card_name.' credit used'" />
                </div>

                <div class="flex items-center gap-2 border-t border-border pt-3">
                    <x-ui.button :href="route('credit-cards.payments.create', $card)" size="sm">Pay bill</x-ui.button>
                    <x-ui.button :href="route('credit-cards.statements.create', $card)" variant="outline" size="sm">
                        Add statement
                    </x-ui.button>
                    <a href="{{ route('credit-cards.show', $card) }}"
                       class="ml-auto text-sm text-muted-foreground hover:text-foreground">Details</a>
                </div>
            </x-ui.card-content>
        </x-ui.card>
    @endforeach
</div>
@endif
@endsection
