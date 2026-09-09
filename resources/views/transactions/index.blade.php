@extends('layouts.app')

@section('title', 'Transactions')
@section('heading', 'Transactions')
@section('subheading', $transactions->total().' entries · '.\App\Support\Money::inr($filteredSpending).' of spending')

@section('actions')
    <x-ui.button :href="route('transactions.create', ['type' => 'income'])" variant="outline" icon="plus">Income</x-ui.button>
    <x-ui.button :href="route('transactions.create', ['type' => 'transfer'])" variant="outline" icon="arrow-left-right">Transfer</x-ui.button>
    <x-ui.button :href="route('quick-entry')" icon="plus">Spend</x-ui.button>
@endsection

@section('content')
@php
    $advanced = collect($filters)
        ->only(['payer_id', 'beneficiary_id', 'planned_status', 'purpose', 'merchant_id', 'min_amount', 'max_amount'])
        ->filter()
        ->isNotEmpty();
@endphp

<x-ui.card class="mb-4" x-data="{ more: {{ $advanced ? 'true' : 'false' }} }">
    <form method="GET">
        <x-ui.card-content class="space-y-3">
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
                <div class="relative lg:col-span-2">
                    <x-ui.icon name="search"
                        class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                           placeholder="Search description, merchant, notes" aria-label="Search transactions"
                           class="h-9 w-full rounded-md border border-input bg-card pl-9 pr-3 text-sm
                                  focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
                </div>

                <input type="date" name="start" value="{{ $filters['start'] ?? '' }}" aria-label="From date"
                       class="h-9 rounded-md border border-input bg-card px-3 text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
                <input type="date" name="end" value="{{ $filters['end'] ?? '' }}" aria-label="To date"
                       class="h-9 rounded-md border border-input bg-card px-3 text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">

                <select name="type" aria-label="Type"
                        class="h-9 rounded-md border border-input bg-card px-3 text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>

                <select name="account_id" aria-label="Account"
                        class="h-9 rounded-md border border-input bg-card px-3 text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
                    <option value="">All accounts</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected(($filters['account_id'] ?? '') == $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="more" x-cloak class="grid gap-2 border-t border-border pt-3 sm:grid-cols-2 lg:grid-cols-6">
                <select name="category_id" aria-label="Category"
                        class="h-9 rounded-md border border-input bg-card px-3 text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->full_name }}</option>
                    @endforeach
                </select>
                <select name="payer_id" aria-label="Paid by"
                        class="h-9 rounded-md border border-input bg-card px-3 text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
                    <option value="">Anyone paid</option>
                    @foreach ($people as $person)
                        <option value="{{ $person->id }}" @selected(($filters['payer_id'] ?? '') == $person->id)>{{ $person->name }}</option>
                    @endforeach
                </select>
                <select name="beneficiary_id" aria-label="For"
                        class="h-9 rounded-md border border-input bg-card px-3 text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
                    <option value="">For anyone</option>
                    @foreach ($people as $person)
                        <option value="{{ $person->id }}" @selected(($filters['beneficiary_id'] ?? '') == $person->id)>{{ $person->name }}</option>
                    @endforeach
                </select>
                <select name="planned_status" aria-label="Planned"
                        class="h-9 rounded-md border border-input bg-card px-3 text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
                    <option value="">Planned or not</option>
                    @foreach ($plannedStatuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['planned_status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.01" name="min_amount" value="{{ $filters['min_amount'] ?? '' }}"
                       placeholder="Min ₹" aria-label="Minimum amount"
                       class="h-9 rounded-md border border-input bg-card px-3 text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
                <input type="number" step="0.01" name="max_amount" value="{{ $filters['max_amount'] ?? '' }}"
                       placeholder="Max ₹" aria-label="Maximum amount"
                       class="h-9 rounded-md border border-input bg-card px-3 text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button type="submit" size="sm">Apply</x-ui.button>
                <x-ui.button :href="route('transactions.index')" variant="ghost" size="sm">Clear</x-ui.button>
                <button type="button" x-on:click="more = !more"
                        class="ml-auto inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <x-ui.icon name="sliders-horizontal" class="size-3.5" />
                    <span x-text="more ? 'Fewer filters' : 'More filters'">More filters</span>
                </button>
            </div>
        </x-ui.card-content>
    </form>
</x-ui.card>

<x-ui.card>
    @if ($transactions->isEmpty())
        <x-ui.empty-state icon="inbox" title="No transactions match"
            description="Nothing here for these filters. Clear them, or record your first entry.">
            <x-ui.button :href="route('transactions.index')" variant="outline" size="sm">Clear filters</x-ui.button>
            <x-ui.button :href="route('quick-entry')" size="sm" icon="plus">Record a spend</x-ui.button>
        </x-ui.empty-state>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left">
                        <th scope="col" class="px-5 py-2.5 text-xs font-medium text-muted-foreground">Date</th>
                        <th scope="col" class="px-5 py-2.5 text-xs font-medium text-muted-foreground">Details</th>
                        <th scope="col" class="hidden px-5 py-2.5 text-xs font-medium text-muted-foreground md:table-cell">Category</th>
                        <th scope="col" class="hidden px-5 py-2.5 text-xs font-medium text-muted-foreground lg:table-cell">Account</th>
                        <th scope="col" class="hidden px-5 py-2.5 text-xs font-medium text-muted-foreground xl:table-cell">Who</th>
                        <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                @foreach ($transactions as $t)
                    <tr class="transition-colors hover:bg-muted/60">
                        <td class="whitespace-nowrap px-5 py-3 text-muted-foreground tabular">
                            {{ $t->transaction_date->format('d M Y') }}
                        </td>
                        <td class="px-5 py-3">
                            <a href="{{ route('transactions.show', $t) }}" class="font-medium hover:underline">
                                {{ $t->description ?: $t->merchant?->name ?: $t->type->label() }}
                            </a>
                            <div class="mt-0.5 flex flex-wrap items-center gap-1">
                                <x-ui.badge variant="secondary">{{ $t->type->label() }}</x-ui.badge>
                                @if ($t->planned_status)
                                    <x-ui.badge :variant="match($t->planned_status->value) {
                                        'planned' => 'success', 'emergency' => 'destructive', default => 'warning',
                                    }">{{ $t->planned_status->label() }}</x-ui.badge>
                                @endif
                                {{-- Category and account repeat here on narrow screens,
                                     where their own columns are hidden. --}}
                                <span class="text-xs text-muted-foreground md:hidden">
                                    {{ $t->category?->name ?: 'Uncategorised' }} · {{ $t->account->name }}
                                </span>
                            </div>
                        </td>
                        <td class="hidden px-5 py-3 text-muted-foreground md:table-cell">
                            {{ $t->category?->name ?: '—' }}
                            @if ($t->subcategory)
                                <div class="text-xs">{{ $t->subcategory->name }}</div>
                            @endif
                        </td>
                        <td class="hidden px-5 py-3 text-muted-foreground lg:table-cell">{{ $t->account->name }}</td>
                        <td class="hidden px-5 py-3 text-xs text-muted-foreground xl:table-cell">
                            @if ($t->payer)<div>by {{ $t->payer->name }}</div>@endif
                            @if ($t->beneficiary)<div>for {{ $t->beneficiary->name }}</div>@endif
                            @if (! $t->payer && ! $t->beneficiary)—@endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <x-finance.money :amount="$t->amount"
                                :tone="$t->type->countsAsSpending() ? 'strong' : 'muted'" />
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @if ($transactions->hasPages())
            <div class="border-t border-border px-5 py-3">
                {{ $transactions->links() }}
            </div>
        @endif
    @endif
</x-ui.card>
@endsection
