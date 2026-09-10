@extends('layouts.app')

@section('title', 'Net worth')
@section('heading', 'What you own, what you owe')
@section('subheading', 'As of today')

@section('actions')
    <x-ui.button :href="route('reports.index')" variant="outline">Back to reports</x-ui.button>
@endsection

@section('content')

<x-ui.card>
    <x-ui.card-content class="!py-6">
        <div class="grid gap-5 sm:grid-cols-3">
            <div>
                <p class="text-xs font-medium text-muted-foreground">Own</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight tabular text-income">
                    {{ \App\Support\Money::inr($summary['assets']) }}
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-muted-foreground">Owe</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight tabular text-expense">
                    {{ \App\Support\Money::inr($summary['liabilities']) }}
                </p>
                <p class="text-xs text-muted-foreground">
                    cards {{ \App\Support\Money::compact($summary['card_debt']) }} ·
                    loans {{ \App\Support\Money::compact($summary['loan_debt']) }}
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-muted-foreground">Net worth</p>
                <p class="mt-1 text-3xl font-semibold tracking-tight tabular
                          {{ bccomp($summary['net_worth'], '0', 2) === -1 ? 'text-expense' : 'text-income' }}">
                    {{ \App\Support\Money::inr($summary['net_worth']) }}
                </p>
            </div>
        </div>
    </x-ui.card-content>
</x-ui.card>

@if ($summary['unvalued_assets'] > 0)
    {{-- The figure counts every rupee owed but only assets someone valued.
         Saying so is the difference between a caveat and a wrong number. --}}
    <div class="mt-4">
        <x-ui.alert variant="warning" title="This figure is more pessimistic than your real position">
            Every rupee you owe is counted, but {{ $summary['unvalued_assets'] }} asset(s) have no
            value recorded, so they contribute nothing.
            <a href="{{ route('assets.index') }}"
               class="underline underline-offset-2 hover:text-foreground">Add values</a>
            if you want them counted — leaving them blank is a fine choice too.
        </x-ui.alert>
    </div>
@endif

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <x-ui.card class="h-full">
        <x-ui.card-header title="What you own" />
        <x-ui.card-content flush>
            <ul class="divide-y divide-border">
                @foreach ($accounts->where('normal_balance', \App\Enums\NormalBalance::Asset) as $account)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                        <div class="min-w-0">
                            <a href="{{ route('accounts.show', $account) }}"
                               class="text-sm hover:underline">{{ $account->name }}</a>
                            <p class="text-xs text-muted-foreground">
                                {{ $account->type->label() }}{{ $account->owner ? ' · '.$account->owner->name : '' }}
                            </p>
                        </div>
                        <x-finance.money :amount="$account->cached_balance" class="text-sm" />
                    </li>
                @endforeach

                @foreach ($assets as $asset)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                        <div class="min-w-0">
                            <a href="{{ route('assets.index') }}" class="text-sm hover:underline">{{ $asset->name }}</a>
                            <p class="text-xs text-muted-foreground">
                                {{ $asset->typeLabel() }}{{ $asset->hasValue() ? '' : ' · not valued' }}
                            </p>
                        </div>
                        @if ($asset->hasValue())
                            <x-finance.money :amount="$asset->current_value" class="text-sm" />
                        @else
                            <span class="text-sm text-muted-foreground">—</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-ui.card-content>
        <x-ui.card-footer class="flex items-center justify-between">
            <span class="font-medium text-foreground">Total</span>
            <x-finance.money :amount="$summary['assets']" tone="strong" class="text-sm" />
        </x-ui.card-footer>
    </x-ui.card>

    <x-ui.card class="h-full">
        <x-ui.card-header title="What you owe" />
        <x-ui.card-content flush>
            <ul class="divide-y divide-border">
                @foreach ($accounts->where('normal_balance', \App\Enums\NormalBalance::Liability) as $account)
                    @continue(bccomp((string) $account->cached_balance, '0', 2) === 0)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                        <div class="min-w-0">
                            <a href="{{ route('accounts.show', $account) }}"
                               class="text-sm hover:underline">{{ $account->name }}</a>
                            <p class="text-xs text-muted-foreground">
                                Credit card{{ $account->owner ? ' · '.$account->owner->name : '' }}
                            </p>
                        </div>
                        <x-finance.money :amount="$account->cached_balance" tone="debt" class="text-sm" />
                    </li>
                @endforeach

                @foreach ($loans as $loan)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                        <div class="min-w-0">
                            <a href="{{ route('loans.show', $loan) }}"
                               class="text-sm hover:underline">{{ $loan->name }}</a>
                            <p class="text-xs text-muted-foreground">
                                {{ $loan->remainingCount() }} EMIs left{{ $loan->owner ? ' · '.$loan->owner->name : '' }}
                            </p>
                        </div>
                        <x-finance.money :amount="$loan->remainingAmount()" tone="debt" class="text-sm" />
                    </li>
                @endforeach
            </ul>
        </x-ui.card-content>
        <x-ui.card-footer class="flex items-center justify-between">
            <span class="font-medium text-foreground">Total</span>
            <x-finance.money :amount="$summary['liabilities']" tone="debt" class="text-sm font-medium" />
        </x-ui.card-footer>
    </x-ui.card>
</div>

<x-ui.card class="mt-4">
    <x-ui.card-content class="text-sm text-muted-foreground">
        <span class="font-medium text-foreground">How loan debt is counted here.</span>
        Loans show the cash still to pay — remaining EMIs multiplied by the EMI amount — so the
        figure includes future interest. That is deliberately conservative: it is larger than the
        principal your lender would quote to close the loan early. Set-aside money is left out
        entirely — an emergency fund does not appear in any figure on this page.
    </x-ui.card-content>
</x-ui.card>
@endsection
