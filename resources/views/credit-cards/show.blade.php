@extends('layouts.app')

@section('title', $card->card_name)
@section('heading', $card->card_name)
@section('subheading', ($card->account->institution ?: 'Credit card')
    .($card->account->owner ? ' · '.$card->account->owner->name."'s card" : ''))

@section('actions')
    <x-ui.button :href="route('credit-cards.payments.create', $card)">Pay bill</x-ui.button>
    <x-ui.button :href="route('credit-cards.statements.create', $card)" variant="outline">Add statement</x-ui.button>
    <x-ui.button :href="route('credit-cards.edit', $card)" variant="outline" size="icon" aria-label="Edit card">
        <x-ui.icon name="pencil" class="size-4" />
    </x-ui.button>
@endsection

@section('content')
@php
    $util = $card->utilisation();
    $tone = $util >= 70 ? 'destructive' : ($util >= 40 ? 'warning' : 'success');
    $unbilledTotal = $unbilled->reduce(
        fn ($c, $t) => $t->balance_effect === \App\Enums\BalanceEffect::Increase
            ? bcadd($c, $t->amount, 2) : bcsub($c, $t->amount, 2),
        '0.00'
    );
@endphp

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    <x-ui.stat label="Owed now" tone="debt" :value="\App\Support\Money::inr($card->outstanding())" />
    <x-ui.stat label="Available" :value="\App\Support\Money::inr($card->availableCredit())"
        :hint="'of '.\App\Support\Money::inr($card->credit_limit).' limit'" />
    <x-ui.stat label="Spent this month" :value="\App\Support\Money::inr($monthSpending)" hint="purchases" />
    {{-- Kept separate from spending on purpose (spec 19F). --}}
    <x-ui.stat label="Paid this month" :value="\App\Support\Money::inr($monthPayments)"
        hint="bill payments, not spending" />
</div>

<x-ui.card class="mt-4">
    <x-ui.card-content>
        <div class="mb-1.5 flex items-baseline justify-between text-xs">
            <span class="text-muted-foreground">{{ $util }}% of limit used</span>
            <span class="text-muted-foreground">
                <x-finance.money :amount="$card->credit_limit" tone="muted" /> limit
            </span>
        </div>
        <x-ui.progress :value="$util" :variant="$tone" label="Credit used" />
        <p class="mt-2 text-xs text-muted-foreground">
            Bills on the {{ $card->statement_day }} of each month, payment due on the
            {{ $card->payment_due_day }}.
        </p>
    </x-ui.card-content>
</x-ui.card>

<div class="mt-4 grid gap-4 lg:grid-cols-12">
    <div class="space-y-4 lg:col-span-7">

        <x-ui.card>
            <x-ui.card-header title="Not yet billed"
                :description="'Since '.$cycleStart->format('d M')" />
            <x-ui.card-content flush>
                @if ($unbilled->isEmpty())
                    <x-ui.empty-state icon="check-circle" title="Nothing outstanding to bill"
                        description="Purchases appear here until a statement covers them." />
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($unbilled as $t)
                            <li class="flex items-center gap-3 px-5 py-2.5">
                                <span class="w-12 shrink-0 text-xs text-muted-foreground tabular">
                                    {{ $t->transaction_date->format('d M') }}
                                </span>
                                <a href="{{ route('transactions.show', $t) }}"
                                   class="min-w-0 flex-1 truncate text-sm hover:underline">
                                    {{ $t->description ?: $t->merchant?->name ?: 'Purchase' }}
                                </a>
                                <x-finance.money :amount="$t->amount" class="text-sm"
                                    :tone="$t->balance_effect === \App\Enums\BalanceEffect::Decrease ? 'income' : null" />
                            </li>
                        @endforeach
                    </ul>
                    <div class="flex items-center justify-between border-t border-border px-5 py-2.5">
                        <span class="text-xs font-medium text-muted-foreground">Unbilled total</span>
                        <x-finance.money :amount="$unbilledTotal" tone="strong" class="text-sm" />
                    </div>
                @endif
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header title="Statements" />
            <x-ui.card-content flush>
                @if ($statements->isEmpty())
                    <x-ui.empty-state icon="file-text" title="No statements recorded"
                        description="Add one when your real statement arrives — it groups purchases you already entered.">
                        <x-ui.button :href="route('credit-cards.statements.create', $card)" variant="outline" size="sm">
                            Add your first statement
                        </x-ui.button>
                    </x-ui.empty-state>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left">
                                    <th scope="col" class="px-5 py-2.5 text-xs font-medium text-muted-foreground">Period</th>
                                    <th scope="col" class="px-5 py-2.5 text-xs font-medium text-muted-foreground">Due</th>
                                    <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">Amount</th>
                                    <th scope="col" class="hidden px-5 py-2.5 text-right text-xs font-medium text-muted-foreground sm:table-cell">Remaining</th>
                                    <th scope="col" class="px-5 py-2.5 text-xs font-medium text-muted-foreground">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                            @foreach ($statements as $statement)
                                <tr class="transition-colors hover:bg-muted/60">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('credit-cards.statements.show', [$card, $statement]) }}"
                                           class="hover:underline">
                                            {{ $statement->period_start->format('d M') }} – {{ $statement->period_end->format('d M Y') }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-3 {{ $statement->isOverdue() ? 'font-medium text-destructive' : 'text-muted-foreground' }}">
                                        {{ $statement->due_date->format('d M') }}
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <x-finance.money :amount="$statement->statement_amount" />
                                    </td>
                                    <td class="hidden px-5 py-3 text-right sm:table-cell">
                                        <x-finance.money :amount="$statement->balanceRemaining()" tone="muted" />
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-ui.badge :variant="match($statement->status->value) {
                                            'paid' => 'success', 'overdue' => 'destructive',
                                            'partially_paid' => 'warning', default => 'default',
                                        }">{{ $statement->status->label() }}</x-ui.badge>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    </div>

    <div class="space-y-4 lg:col-span-5">
        <x-ui.card>
            <x-ui.card-header title="Recent payments" />
            <x-ui.card-content flush>
                @if ($payments->isEmpty())
                    <x-ui.empty-state icon="banknote" title="No bill payments yet" />
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($payments as $payment)
                            <li class="flex items-center gap-3 px-5 py-2.5">
                                <span class="w-12 shrink-0 text-xs text-muted-foreground tabular">
                                    {{ $payment->payment_date->format('d M') }}
                                </span>
                                <span class="min-w-0 flex-1 truncate text-sm text-muted-foreground">
                                    from {{ $payment->sourceAccount->name }}
                                </span>
                                <x-finance.money :amount="$payment->amount" class="text-sm" />
                                <button type="button"
                                        x-on:click="$dispatch('open-dialog', 'void-payment-{{ $payment->id }}')"
                                        class="shrink-0 rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                        aria-label="Void payment of {{ \App\Support\Money::inr($payment->amount) }}">
                                    <x-ui.icon name="trash-2" class="size-3.5" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.alert variant="muted" icon="info" title="How this card is accounted for">
            Purchases count as spending on the day you make them. A statement just groups those
            purchases — it adds nothing. Paying the bill moves money out of your bank and reduces
            what you owe, and is never counted as spending again.
        </x-ui.alert>
    </div>
</div>

@foreach ($payments as $payment)
    <x-ui.dialog id="void-payment-{{ $payment->id }}" title="Void this payment?">
        <form method="POST" action="{{ route('credit-cards.payments.void', [$card, $payment]) }}">
            @csrf
            <div class="space-y-4 px-5 py-4">
                <p class="text-sm text-muted-foreground">
                    The {{ \App\Support\Money::inr($payment->amount) }} payment made on
                    {{ $payment->payment_date->format('d M Y') }} will be reversed: your bank balance
                    goes back up and the amount owed is restored.
                </p>
                <x-ui.input label="Why?" name="void_reason" required
                    placeholder="Payment bounced, entered twice, …" />
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-5 py-3">
                <x-ui.button type="button" variant="ghost"
                    x-on:click="$dispatch('close-dialog', 'void-payment-{{ $payment->id }}')">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="destructive">Void payment</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
@endforeach
@endsection
