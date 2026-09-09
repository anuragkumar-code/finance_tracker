@extends('layouts.app')

@section('title', 'Accounts')
@section('heading', 'Accounts')
@section('subheading', 'What you hold and what you owe')

@section('actions')
    <form method="POST" action="{{ route('accounts.recalculate') }}">
        @csrf
        <x-ui.button type="submit" variant="outline"
            title="Re-derive every balance from the underlying transactions">Check balances</x-ui.button>
    </form>
    <x-ui.button :href="route('accounts.create')" icon="plus">Add account</x-ui.button>
@endsection

@section('content')

<div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:gap-4">
    <x-ui.stat label="You own" icon="trending-up" tone="income"
        :value="\App\Support\Money::inr($netWorth['assets'])"
        :hint="\App\Support\Money::inr($netWorth['bank_cash']).' in bank &amp; cash'" />

    <x-ui.stat label="You owe" icon="trending-down" tone="debt"
        :value="\App\Support\Money::inr($netWorth['liabilities'])"
        :hint="\App\Support\Money::inr($netWorth['card_debt']).' cards · '.\App\Support\Money::inr($netWorth['loan_debt']).' loans'" />

    <x-ui.stat label="Net worth" icon="scale"
        :tone="bccomp($netWorth['net_worth'], '0', 2) === -1 ? 'expense' : 'default'"
        :value="\App\Support\Money::inr($netWorth['net_worth'])"
        hint="Loan debt counts all remaining EMIs, interest included" />
</div>

{{-- Owner filter. Everyone is the default: the app should never look like a
     scoreboard between partners. --}}
<div class="mt-5 flex flex-wrap items-center gap-1.5">
    <span class="mr-1 text-xs font-medium text-muted-foreground">Whose</span>
    <a href="{{ route('accounts.index') }}"
       class="rounded-md border px-2.5 py-1 text-sm transition-colors
              {{ ! $selectedOwner ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card hover:bg-muted' }}">
        Everyone
    </a>
    @foreach ($owners as $owner)
        <a href="{{ route('accounts.index', ['owner' => $owner->id]) }}"
           class="rounded-md border px-2.5 py-1 text-sm transition-colors
                  {{ $selectedOwner === $owner->id ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card hover:bg-muted' }}">
            {{ $owner->name }}
        </a>
    @endforeach
</div>

<div class="mt-4 space-y-4">
@forelse ($accounts as $typeLabel => $group)
    <x-ui.card>
        <x-ui.card-header :title="$typeLabel" />
        <x-ui.card-content flush>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left">
                            <th scope="col" class="px-5 py-2.5 text-xs font-medium text-muted-foreground">Account</th>
                            <th scope="col" class="hidden px-5 py-2.5 text-xs font-medium text-muted-foreground sm:table-cell">Whose</th>
                            <th scope="col" class="hidden px-5 py-2.5 text-xs font-medium text-muted-foreground lg:table-cell">Institution</th>
                            <th scope="col" class="hidden px-5 py-2.5 text-right text-xs font-medium text-muted-foreground lg:table-cell">Opening</th>
                            <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">Current</th>
                            <th scope="col" class="w-px px-5 py-2.5"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                    @foreach ($group as $account)
                        <tr class="transition-colors hover:bg-muted/60 {{ $account->is_active ? '' : 'opacity-60' }}">
                            <td class="px-5 py-3">
                                <a href="{{ route('accounts.show', $account) }}"
                                   class="font-medium hover:underline">{{ $account->name }}</a>
                                @unless ($account->is_active)
                                    <x-ui.badge variant="secondary" class="ml-1">Inactive</x-ui.badge>
                                @endunless
                                <p class="text-xs text-muted-foreground">
                                    since {{ $account->opening_balance_date->format('d M Y') }}
                                </p>
                            </td>
                            <td class="hidden px-5 py-3 sm:table-cell">
                                @if ($account->owner)
                                    <x-ui.badge variant="outline">{{ $account->owner->name }}</x-ui.badge>
                                @else
                                    <span class="text-xs text-muted-foreground">—</span>
                                @endif
                            </td>
                            <td class="hidden px-5 py-3 text-muted-foreground lg:table-cell">
                                {{ $account->institution ?: '—' }}
                            </td>
                            <td class="hidden px-5 py-3 text-right lg:table-cell">
                                <x-finance.money :amount="$account->opening_balance" tone="muted" />
                            </td>
                            <td class="px-5 py-3 text-right">
                                <x-finance.money :amount="$account->cached_balance"
                                    :tone="$account->isLiability() ? 'debt' : 'strong'" />
                                @if ($account->isLiability())
                                    <p class="text-[0.6875rem] text-muted-foreground">owed</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <x-ui.button :href="route('accounts.edit', $account)" variant="ghost" size="icon"
                                    aria-label="Edit {{ $account->name }}">
                                    <x-ui.icon name="pencil" class="size-4" />
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card-content>
    </x-ui.card>
@empty
    <x-ui.card>
        @if ($selectedOwner)
            <x-ui.empty-state icon="wallet" title="No accounts for this person"
                description="Nothing is tagged to them yet.">
                <x-ui.button :href="route('accounts.index')" variant="outline" size="sm">Show everyone</x-ui.button>
            </x-ui.empty-state>
        @else
            <x-ui.empty-state icon="wallet" title="No accounts yet"
                description="Add each bank account, cash holding and card, with what it holds today. Opening balances record your starting position without inventing fake transactions.">
                <x-ui.button :href="route('accounts.create')" icon="plus">Add your first account</x-ui.button>
            </x-ui.empty-state>
        @endif
    </x-ui.card>
@endforelse
</div>

@if ($setAside->isNotEmpty())
    {{-- Deliberately set apart. This money is kept out of every total and every
         other screen, but must stay reachable so it can be managed. --}}
    <x-ui.card class="mt-6 border-info/30">
        <x-ui.card-header title="Set aside"
            description="Not counted in any total, report or net worth" />
        <x-ui.card-content flush>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left">
                            <th scope="col" class="px-5 py-2.5 text-xs font-medium text-muted-foreground">Account</th>
                            <th scope="col" class="hidden px-5 py-2.5 text-xs font-medium text-muted-foreground sm:table-cell">Whose</th>
                            <th scope="col" class="hidden px-5 py-2.5 text-xs font-medium text-muted-foreground sm:table-cell">What for</th>
                            <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">Balance</th>
                            <th scope="col" class="w-px px-5 py-2.5"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                    @foreach ($setAside as $account)
                        <tr class="transition-colors hover:bg-muted/60">
                            <td class="px-5 py-3">
                                <a href="{{ route('accounts.show', $account) }}"
                                   class="font-medium hover:underline">{{ $account->name }}</a>
                                <p class="text-xs text-muted-foreground">{{ $account->type->label() }}</p>
                            </td>
                            <td class="hidden px-5 py-3 sm:table-cell">
                                @if ($account->owner)
                                    <x-ui.badge variant="outline">{{ $account->owner->name }}</x-ui.badge>
                                @else
                                    <span class="text-xs text-muted-foreground">—</span>
                                @endif
                            </td>
                            <td class="hidden px-5 py-3 text-muted-foreground sm:table-cell">
                                {{ $account->set_aside_reason ?: 'Set aside' }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <x-finance.money :amount="$account->cached_balance" tone="strong" />
                            </td>
                            <td class="px-5 py-3 text-right">
                                <x-ui.button :href="route('accounts.edit', $account)" variant="ghost" size="icon"
                                    aria-label="Edit {{ $account->name }}">
                                    <x-ui.icon name="pencil" class="size-4" />
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card-content>
        <x-ui.card-footer>
            Invisible everywhere else in the app — not in your available balance, net worth,
            reports or charts. You can still transfer into it from
            <a href="{{ route('transactions.create', ['type' => 'transfer']) }}"
               class="underline underline-offset-2 hover:text-foreground">Transactions → Transfer</a>.
        </x-ui.card-footer>
    </x-ui.card>
@endif
@endsection
