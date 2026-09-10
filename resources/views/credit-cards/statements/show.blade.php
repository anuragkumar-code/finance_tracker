@extends('layouts.app')

@section('title', 'Statement')
@section('heading', $card->card_name.' statement')
@section('subheading', $statement->period_start->format('d M').' – '.$statement->period_end->format('d M Y'))

@section('actions')
    @unless ($statement->status->isSettled())
        <x-ui.button :href="route('credit-cards.payments.create', ['creditCard' => $card, 'statement' => $statement->id])">
            Pay this bill
        </x-ui.button>
    @endunless
    <x-ui.button :href="route('credit-cards.show', $card)" variant="outline">Back to card</x-ui.button>
@endsection

@section('content')

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    <x-ui.stat label="Statement amount" :value="\App\Support\Money::inr($statement->statement_amount)" />
    <x-ui.stat label="Paid" tone="income" :value="\App\Support\Money::inr($statement->amountPaid())" />
    <x-ui.stat label="Remaining" :value="\App\Support\Money::inr($statement->balanceRemaining())" />
    <div class="rounded-xl border border-border bg-card px-5 py-4 shadow-xs">
        <p class="text-xs font-medium text-muted-foreground">Due by</p>
        <p class="mt-1.5 text-2xl font-semibold tracking-tight {{ $statement->isOverdue() ? 'text-destructive' : '' }}">
            {{ $statement->due_date->format('d M') }}
        </p>
        <div class="mt-1.5">
            <x-ui.badge :variant="match($statement->status->value) {
                'paid' => 'success', 'overdue' => 'destructive',
                'partially_paid' => 'warning', default => 'default',
            }">{{ $statement->status->label() }}</x-ui.badge>
        </div>
    </div>
</div>

@if (bccomp($discrepancy, '0', 2) !== 0)
    {{-- Never quietly reconciled away: a gap here is real information. --}}
    <div class="mt-4">
        <x-ui.alert variant="warning"
            :title="'This statement is '.\App\Support\Money::inr($discrepancy).' away from the purchases recorded for the period'">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span>
                    Either a purchase is missing from the app, or something posted outside these
                    dates. Compare the list below against your real statement.
                </span>
                <form method="POST" action="{{ route('credit-cards.statements.regenerate', [$card, $statement]) }}">
                    @csrf
                    <x-ui.button type="submit" variant="outline" size="sm">Re-link transactions</x-ui.button>
                </form>
            </div>
        </x-ui.alert>
    </div>
@endif

<div class="mt-4 grid gap-4 lg:grid-cols-12">
    <div class="lg:col-span-8">
        <x-ui.card>
            <x-ui.card-header title="Purchases on this statement"
                :description="$statement->items->count().' items'" />
            <x-ui.card-content flush>
                @if ($statement->items->isEmpty())
                    <x-ui.empty-state icon="inbox" title="Nothing grouped"
                        description="No purchases were linked into this statement." />
                @else
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Details</th>
                                    <th scope="col" class="hidden sm:table-cell">Category</th>
                                    <th scope="col" class="num">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($statement->items as $item)
                                @php($t = $item->transaction)
                                <tr>
                                    <td class="whitespace-nowrap tabular">
                                        {{ $t?->transaction_date?->format('d M') ?? '—' }}
                                    </td>
                                    <td>
                                        @if ($t)
                                            <a href="{{ route('transactions.show', $t) }}" class="hover:underline">
                                                {{ $t->description ?: $t->merchant?->name ?: 'Purchase' }}
                                            </a>
                                        @else
                                            <span class="text-muted-foreground">Transaction removed</span>
                                        @endif
                                    </td>
                                    <td class="hidden sm:table-cell">
                                        {{ $t?->category?->name ?: '—' }}
                                    </td>
                                    <td class="num">
                                        <x-finance.money :amount="$item->amount_snapshot" />
                                        @if ($t && bccomp((string) $item->amount_snapshot, (string) $t->amount, 2) !== 0)
                                            <p class="text-xs text-warning">
                                                now {{ \App\Support\Money::inr($t->amount) }}
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex items-center justify-between border-t border-border px-5 py-2.5">
                        <span class="text-xs font-medium text-muted-foreground">Grouped total</span>
                        <x-finance.money :amount="$statement->items->sum('amount_snapshot')" tone="strong" class="text-sm" />
                    </div>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    </div>

    <div class="space-y-4 lg:col-span-4">
        <x-ui.card>
            <x-ui.card-header title="Payments against this bill" />
            <x-ui.card-content flush>
                @if ($statement->payments->isEmpty())
                    <x-ui.empty-state icon="banknote" title="Not paid yet" />
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($statement->payments as $payment)
                            <li class="flex items-center gap-3 px-5 py-2.5">
                                <span class="w-11 shrink-0 text-xs text-muted-foreground tabular">
                                    {{ $payment->payment_date->format('d M') }}
                                </span>
                                <span class="min-w-0 flex-1 truncate text-sm text-muted-foreground">
                                    {{ $payment->sourceAccount->name }}
                                </span>
                                <x-finance.money :amount="$payment->amount" class="text-sm" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.alert variant="muted" icon="info">
            This statement created no new expense. Every purchase listed here was already counted
            as spending on the day it happened.
            @if ($statement->notes)
                <span class="mt-2 block text-foreground">{{ $statement->notes }}</span>
            @endif
        </x-ui.alert>
    </div>
</div>
@endsection
