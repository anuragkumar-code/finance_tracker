@extends('layouts.app')

@section('title', 'Accounts')
@section('heading', 'Accounts')
@section('subheading', 'Where money goes out from, and what is still owed')

@section('actions')
    <x-ui.button :href="route('accounts.create')" icon="plus">Add account</x-ui.button>
@endsection

@section('content')

<div class="grid grid-cols-2 gap-3 lg:gap-4">
    <x-ui.stat label="Out this month" icon="trending-down"
        :value="\App\Support\Money::inr($spentThisMonth)"
        :href="route('transactions.index', ['start' => now()->startOfMonth()->toDateString(), 'end' => now()->endOfMonth()->toDateString()])"
        hint="Across every account" />

    <x-ui.stat label="Of that, on cards" icon="credit-card"
        :value="\App\Support\Money::inr($onCards)"
        hint="Charged this month, not owed" />
</div>

{{-- Owner filter. Everyone is the default: the app should never look like a
     scoreboard between partners. A segmented control rather than a row of
     buttons, because exactly one of these is on at a time. --}}
<div class="mt-5 flex flex-wrap items-center gap-2">
    <span class="text-xs font-medium text-muted-foreground">Whose</span>
    <div class="segmented">
        <a href="{{ route('accounts.index') }}" @if (! $selectedOwner) aria-current="page" @endif>Everyone</a>
        @foreach ($owners as $owner)
            <a href="{{ route('accounts.index', ['owner' => $owner->id]) }}"
               @if ($selectedOwner === $owner->id) aria-current="page" @endif>{{ $owner->name }}</a>
        @endforeach
    </div>
</div>

@if ($paymentSources->isEmpty() && $balanceAccounts->isEmpty())
    <x-ui.card class="mt-4">
        @if ($selectedOwner)
            <x-ui.empty-state icon="wallet" title="No accounts for this person"
                description="Nothing is tagged to them yet.">
                <x-ui.button :href="route('accounts.index')" variant="outline" size="sm">Show everyone</x-ui.button>
            </x-ui.empty-state>
        @else
            <x-ui.empty-state icon="wallet" title="No accounts yet"
                description="Add the accounts and cards you pay with. They are labels for where money went out from — only cards carry a balance.">
                <x-ui.button :href="route('accounts.create')" icon="plus">Add your first account</x-ui.button>
            </x-ui.empty-state>
        @endif
    </x-ui.card>
@endif

@if ($paymentSources->isNotEmpty())
    {{--
        Payment sources carry no balance.

        A current-account balance here was derived as opening balance + income −
        expenses, and this household records expenses faithfully and income
        almost never — so the figure drifted further from the truth every week,
        one account reaching −₹56,932. What it spent this month is something the
        app actually knows.
    --}}
    <x-ui.card class="mt-4">
        <x-ui.card-header title="Payment sources"
            description="Bank, cash and cards — how money leaves, with no balance kept" />
        <x-ui.card-content flush>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col" class="hidden sm:table-cell">Whose</th>
                            <th scope="col" class="hidden lg:table-cell">Institution</th>
                            <th scope="col" class="num">Out this month</th>
                            <th scope="col" class="w-px"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($paymentSources as $account)
                        @php
                            $row = $spendByAccount->get($account->id);
                            $inactiveClass = $account->is_active ? '' : 'opacity-60';
                        @endphp
                        <tr class="{{ $inactiveClass }}">
                            <td>
                                <a href="{{ route('accounts.show', $account) }}"
                                   class="font-medium hover:underline">{{ $account->name }}</a>
                                @unless ($account->is_active)
                                    <x-ui.badge variant="secondary" class="ml-1">Inactive</x-ui.badge>
                                @endunless
                                <p class="text-xs text-muted-foreground">{{ $account->type->label() }}</p>
                            </td>
                            <td class="hidden sm:table-cell">
                                @if ($account->owner)
                                    <x-ui.badge variant="outline">{{ $account->owner->name }}</x-ui.badge>
                                @else
                                    <span class="text-xs text-muted-foreground">—</span>
                                @endif
                            </td>
                            <td class="hidden lg:table-cell">{{ $account->institution ?: '—' }}</td>
                            <td class="num">
                                @if ($row)
                                    <x-finance.money :amount="$row->spent" tone="strong" />
                                    <p class="text-[0.6875rem] text-muted-foreground">
                                        {{ $row->entries }} {{ \Illuminate\Support\Str::plural('entry', $row->entries) }}
                                    </p>
                                @else
                                    <span class="text-sm text-subtle">—</span>
                                @endif
                            </td>
                            <td class="num">
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
@endif

@foreach ($balanceAccounts as $typeLabel => $group)
    <x-ui.card class="mt-4">
        <x-ui.card-header :title="$typeLabel" />
        <x-ui.card-content flush>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col" class="hidden sm:table-cell">Whose</th>
                            <th scope="col" class="hidden lg:table-cell">Institution</th>
                            <th scope="col" class="hidden num md:table-cell">Out this month</th>
                            <th scope="col" class="num">Owed</th>
                            <th scope="col" class="w-px"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($group as $account)
                        @php
                            $row = $spendByAccount->get($account->id);
                            $isLiability = $account->isLiability();
                            $balanceTone = $isLiability ? 'debt' : 'strong';
                            $inactiveClass = $account->is_active ? '' : 'opacity-60';
                        @endphp
                        <tr class="{{ $inactiveClass }}">
                            <td>
                                <a href="{{ route('accounts.show', $account) }}"
                                   class="font-medium hover:underline">{{ $account->name }}</a>
                                @unless ($account->is_active)
                                    <x-ui.badge variant="secondary" class="ml-1">Inactive</x-ui.badge>
                                @endunless
                            </td>
                            <td class="hidden sm:table-cell">
                                @if ($account->owner)
                                    <x-ui.badge variant="outline">{{ $account->owner->name }}</x-ui.badge>
                                @else
                                    <span class="text-xs text-muted-foreground">—</span>
                                @endif
                            </td>
                            <td class="hidden lg:table-cell">{{ $account->institution ?: '—' }}</td>
                            <td class="hidden num md:table-cell">
                                @if ($row)
                                    <x-finance.money :amount="$row->spent" tone="muted" />
                                @else
                                    <span class="text-sm text-subtle">—</span>
                                @endif
                            </td>
                            <td class="num">
                                <x-finance.money :amount="$account->cached_balance" :tone="$balanceTone" />
                                @if ($isLiability)
                                    <p class="text-[0.6875rem] text-muted-foreground">owed</p>
                                @endif
                            </td>
                            <td class="num">
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
@endforeach

@if ($setAside->isNotEmpty())
    {{-- Deliberately set apart. This money is kept out of every total and every
         other screen, but must stay reachable so it can be managed. --}}
    <x-ui.card class="mt-6 border-info/30">
        <x-ui.card-header title="Set aside" description="Not counted in any total or report" />
        <x-ui.card-content flush>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col" class="hidden sm:table-cell">Why</th>
                            <th scope="col" class="w-px"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($setAside as $account)
                        <tr>
                            <td>
                                <a href="{{ route('accounts.show', $account) }}"
                                   class="font-medium hover:underline">{{ $account->name }}</a>
                                <p class="text-xs text-muted-foreground">{{ $account->type->label() }}</p>
                            </td>
                            <td class="hidden sm:table-cell text-xs text-muted-foreground">
                                {{ $account->set_aside_reason ?: 'Ring-fenced' }}
                            </td>
                            <td class="num">
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
@endif

<x-ui.alert variant="muted" icon="info" title="Why no balances here" class="mt-6">
    This app answers how much goes out each month and where it goes. An account is the mode of
    payment on an entry — which bank, or which card — and nothing more. A bank balance would be
    guesswork without recording income, and what is still owed on a card is a question its own
    statement answers.
</x-ui.alert>
@endsection
