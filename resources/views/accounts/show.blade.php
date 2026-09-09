@extends('layouts.app')

@section('title', $account->name)
@section('heading', $account->name)
@section('subheading', $account->type->label()
    .($account->institution ? ' · '.$account->institution : '')
    .($account->owner ? ' · '.$account->owner->name : ''))

@section('actions')
    <x-ui.button :href="route('accounts.edit', $account)" variant="outline" icon="pencil">Edit account</x-ui.button>
@endsection

@section('content')

{{-- Rule 8: a balance must be explainable as its opening figure plus its
     ledger. This card is that explanation, laid out. --}}
<x-ui.card class="mb-4">
    <x-ui.card-content>
        <div class="grid gap-5 sm:grid-cols-3">
            <div>
                <p class="text-xs font-medium text-muted-foreground">Opening balance</p>
                <p class="mt-1 text-lg font-semibold tabular">{{ \App\Support\Money::inr($account->opening_balance) }}</p>
                <p class="text-xs text-muted-foreground">on {{ $account->opening_balance_date->format('d M Y') }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-muted-foreground">Since then</p>
                <p class="mt-1 text-lg font-semibold tabular">
                    {{ \App\Support\Money::inr(bcsub($derivedBalance, $account->opening_balance, 2)) }}
                </p>
                <p class="text-xs text-muted-foreground">net of all entries</p>
            </div>
            <div>
                <p class="text-xs font-medium text-muted-foreground">
                    {{ $account->isLiability() ? 'Currently owed' : 'Current balance' }}
                </p>
                <p class="mt-1 text-lg font-semibold tabular {{ $account->isLiability() ? 'text-debt' : '' }}">
                    {{ \App\Support\Money::inr($derivedBalance) }}
                </p>
                @if (bccomp($derivedBalance, (string) $account->cached_balance, 2) !== 0)
                    <p class="text-xs text-destructive">
                        Stored balance says {{ \App\Support\Money::inr($account->cached_balance) }} — run "Check balances".
                    </p>
                @endif
            </div>
        </div>
    </x-ui.card-content>
</x-ui.card>

<x-ui.card>
    <x-ui.card-header title="Ledger" description="Every entry behind the balance above" />
    <x-ui.card-content flush>
        @if ($transactions->isEmpty())
            <x-ui.empty-state icon="inbox" title="No entries yet"
                description="Nothing has been recorded against this account.">
                <x-ui.button :href="route('quick-entry')" variant="outline" size="sm" icon="plus">Record a spend</x-ui.button>
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left">
                            <th scope="col" class="px-5 py-2.5 text-xs font-medium text-muted-foreground">Date</th>
                            <th scope="col" class="px-5 py-2.5 text-xs font-medium text-muted-foreground">Details</th>
                            <th scope="col" class="hidden px-5 py-2.5 text-xs font-medium text-muted-foreground sm:table-cell">Type</th>
                            <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">In</th>
                            <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">Out</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                    @foreach ($transactions as $t)
                        @php($isIncrease = $t->balance_effect === \App\Enums\BalanceEffect::Increase)
                        <tr class="transition-colors hover:bg-muted/60">
                            <td class="whitespace-nowrap px-5 py-3 text-muted-foreground tabular">
                                {{ $t->transaction_date->format('d M') }}
                            </td>
                            <td class="px-5 py-3">
                                <a href="{{ route('transactions.show', $t) }}" class="font-medium hover:underline">
                                    {{ $t->description ?: $t->merchant?->name ?: $t->type->label() }}
                                </a>
                                @if ($t->category)
                                    <p class="text-xs text-muted-foreground">{{ $t->category->name }}</p>
                                @endif
                            </td>
                            <td class="hidden px-5 py-3 sm:table-cell">
                                <x-ui.badge variant="secondary">{{ $t->type->label() }}</x-ui.badge>
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if ($isIncrease)
                                    <x-finance.money :amount="$t->amount" tone="income" />
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if (! $isIncrease)
                                    <x-finance.money :amount="$t->amount" tone="expense" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @if ($transactions->hasPages())
                <div class="border-t border-border px-5 py-3">{{ $transactions->links() }}</div>
            @endif
        @endif
    </x-ui.card-content>
</x-ui.card>
@endsection
