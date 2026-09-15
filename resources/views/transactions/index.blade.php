@extends('layouts.app')

@section('title', 'Transactions')
@section('heading', 'Transactions')
@section('subheading', 'Every entry, filterable and sortable')

@section('actions')
    <x-ui.button :href="route('transactions.create', ['type' => 'income'])" variant="outline" icon="trending-up">Income</x-ui.button>
    <x-ui.button :href="route('transactions.create', ['type' => 'transfer'])" variant="outline" icon="arrow-left-right">Transfer</x-ui.button>
    <x-ui.button :href="route('quick-entry')" icon="plus">Spend</x-ui.button>
@endsection

@section('content')
@php
    $advancedKeys = ['category_id', 'payer_id', 'beneficiary_id', 'planned_status', 'purpose', 'merchant_id', 'merchant_group_id', 'event_id', 'min_amount', 'max_amount'];
    $advancedCount = collect($filters)->only($advancedKeys)->filter(fn ($v) => filled($v))->count();

    $sort = request()->query('sort', 'date');
    $dir = request()->query('dir', 'desc');
    $perPage = (int) request()->query('per_page', 50);

    // Filters are applied by GET, so anything not represented as a field in the
    // form would be dropped the moment someone hits Apply. Sort and page size
    // ride along as hidden inputs rather than being silently reset.
    $carried = ['sort' => $sort, 'dir' => $dir, 'per_page' => $perPage];

    $lookup = fn ($collection, $id, $field = 'name') => optional($collection->firstWhere('id', $id))->{$field};

    // One chip per applied filter, each linking back to this page without it.
    $chips = [
        ['key' => 'q', 'label' => 'Search', 'value' => $filters['q'] ?? null],
        ['key' => 'type', 'label' => 'Type', 'value' => collect($types)->firstWhere('value', $filters['type'] ?? null)?->label()],
        ['key' => 'account_id', 'label' => 'Account', 'value' => $lookup($accounts, $filters['account_id'] ?? null)],
        ['key' => 'category_id', 'label' => 'Category', 'value' => $lookup($categories, $filters['category_id'] ?? null)],
        ['key' => 'merchant_id', 'label' => 'Merchant', 'value' => $lookup($merchants, $filters['merchant_id'] ?? null)],
        ['key' => 'merchant_group_id', 'label' => 'Kind of place', 'value' => $lookup($merchantGroups, $filters['merchant_group_id'] ?? null)],
        ['key' => 'event_id', 'label' => 'Trip', 'value' => $lookup($events, $filters['event_id'] ?? null)],
        ['key' => 'payer_id', 'label' => 'Paid by', 'value' => $lookup($people, $filters['payer_id'] ?? null)],
        ['key' => 'beneficiary_id', 'label' => 'For', 'value' => $lookup($people, $filters['beneficiary_id'] ?? null)],
        ['key' => 'planned_status', 'label' => 'Planned', 'value' => collect($plannedStatuses)->firstWhere('value', $filters['planned_status'] ?? null)?->label()],
        ['key' => 'purpose', 'label' => 'Purpose', 'value' => collect($purposes)->firstWhere('value', $filters['purpose'] ?? null)?->label()],
        // The two amount bounds read as one constraint, so removing either
        // clears both rather than leaving a half-open range behind.
        ['keys' => ['min_amount', 'max_amount'], 'key' => 'min_amount', 'label' => 'Amount',
         'value' => filled($filters['min_amount'] ?? null) || filled($filters['max_amount'] ?? null)
            ? trim(($filters['min_amount'] ?? '') !== '' ? '₹'.$filters['min_amount'] : 'any')
                .' – '.(($filters['max_amount'] ?? '') !== '' ? '₹'.$filters['max_amount'] : 'any')
            : null],
        // A date range shows as one chip because the two halves are meaningless
        // on their own.
        ['keys' => ['start', 'end'], 'key' => 'start', 'label' => 'Dates',
         'value' => filled($filters['start'] ?? null) || filled($filters['end'] ?? null)
            ? (($filters['start'] ?? '') !== '' ? \Illuminate\Support\Carbon::parse($filters['start'])->format('d M Y') : 'any')
                .' – '.(($filters['end'] ?? '') !== '' ? \Illuminate\Support\Carbon::parse($filters['end'])->format('d M Y') : 'any')
            : null],
    ];

    $field = 'h-9 w-full rounded-md border border-input bg-card px-3 text-[0.8125rem] shadow-xs '
        .'focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25';

    // Date shortcuts. Someone looking at a ledger almost always wants one of
    // these, and reaching them by typing two dates is needless work.
    $presets = [
        'This month' => [now()->startOfMonth(), now()->endOfMonth()],
        'Last month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
        'Last 3 months' => [now()->subMonthsNoOverflow(2)->startOfMonth(), now()->endOfMonth()],
        'This year' => [now()->startOfYear(), now()->endOfYear()],
    ];
@endphp

<x-ui.card class="mb-4" x-data="{ more: {{ $advancedCount > 0 ? 'true' : 'false' }} }">
    <form method="GET">
        @foreach ($carried as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach

        <div class="space-y-3 px-4 py-3 sm:px-5">
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-[13rem] flex-1">
                    <x-ui.icon name="search"
                        class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-subtle" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                           placeholder="Search description, merchant, notes…" aria-label="Search transactions"
                           class="{{ $field }} pl-9">
                </div>

                <select name="type" aria-label="Type" class="{{ $field }} w-auto min-w-[8.5rem] flex-none">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>

                <select name="account_id" aria-label="Account" class="{{ $field }} w-auto min-w-[9.5rem] flex-none">
                    <option value="">All accounts</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected(($filters['account_id'] ?? '') == $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>

                <button type="button" x-on:click="more = !more"
                        class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-md border border-border
                               bg-card px-3 text-[0.8125rem] shadow-xs transition-colors hover:bg-muted">
                    <x-ui.icon name="sliders-horizontal" class="size-3.5" />
                    <span>Filters</span>
                    @if ($advancedCount)
                        <span class="inline-flex size-4 items-center justify-center rounded-full bg-primary
                                     text-[0.625rem] font-medium text-primary-foreground">{{ $advancedCount }}</span>
                    @endif
                    <x-ui.icon name="chevron-down" class="size-3.5 transition-transform"
                        x-bind:class="more && 'rotate-180'" />
                </button>

                <x-ui.button type="submit" size="sm" class="h-9 shrink-0">Apply</x-ui.button>
            </div>

            <div x-show="more" x-cloak>
                <div class="grid gap-2 border-t border-border pt-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-muted-foreground">From</span>
                        <input type="date" name="start" value="{{ $filters['start'] ?? '' }}" class="{{ $field }}">
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-muted-foreground">To</span>
                        <input type="date" name="end" value="{{ $filters['end'] ?? '' }}" class="{{ $field }}">
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-muted-foreground">Category</span>
                        <select name="category_id" class="{{ $field }}">
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->full_name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-muted-foreground">Merchant</span>
                        <select name="merchant_id" class="{{ $field }}">
                            <option value="">All merchants</option>
                            @foreach ($merchants as $merchant)
                                <option value="{{ $merchant->id }}" @selected(($filters['merchant_id'] ?? '') == $merchant->id)>{{ $merchant->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-muted-foreground">Trip or event</span>
                        <select name="event_id" class="{{ $field }}">
                            <option value="">Any</option>
                            @foreach ($events as $event)
                                <option value="{{ $event->id }}" @selected(($filters['event_id'] ?? '') == $event->id)>{{ $event->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-muted-foreground">Kind of place</span>
                        <select name="merchant_group_id" class="{{ $field }}">
                            <option value="">Any kind</option>
                            @foreach ($merchantGroups as $group)
                                <option value="{{ $group->id }}" @selected(($filters['merchant_group_id'] ?? '') == $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-muted-foreground">Paid by</span>
                        <select name="payer_id" class="{{ $field }}">
                            <option value="">Anyone</option>
                            @foreach ($people as $person)
                                <option value="{{ $person->id }}" @selected(($filters['payer_id'] ?? '') == $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-muted-foreground">For</span>
                        <select name="beneficiary_id" class="{{ $field }}">
                            <option value="">Anyone</option>
                            @foreach ($people as $person)
                                <option value="{{ $person->id }}" @selected(($filters['beneficiary_id'] ?? '') == $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-muted-foreground">Planned</span>
                        <select name="planned_status" class="{{ $field }}">
                            <option value="">Planned or not</option>
                            @foreach ($plannedStatuses as $status)
                                <option value="{{ $status->value }}" @selected(($filters['planned_status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-muted-foreground">Purpose</span>
                        <select name="purpose" class="{{ $field }}">
                            <option value="">Any purpose</option>
                            @foreach ($purposes as $purpose)
                                <option value="{{ $purpose->value }}" @selected(($filters['purpose'] ?? '') === $purpose->value)>{{ $purpose->label() }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="sm:col-span-2 lg:col-span-2">
                        <span class="mb-1 block text-xs font-medium text-muted-foreground">Amount between</span>
                        <div class="flex items-center gap-2">
                            <input type="number" step="0.01" name="min_amount" value="{{ $filters['min_amount'] ?? '' }}"
                                   placeholder="Min ₹" aria-label="Minimum amount" class="{{ $field }}">
                            <span class="text-sm text-subtle">–</span>
                            <input type="number" step="0.01" name="max_amount" value="{{ $filters['max_amount'] ?? '' }}"
                                   placeholder="Max ₹" aria-label="Maximum amount" class="{{ $field }}">
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-border pt-3">
                    <span class="text-xs text-muted-foreground">Jump to</span>
                    @foreach ($presets as $label => $range)
                        {{-- array_merge, not +: the preset has to win over any
                             dates already in the query string, and + keeps the
                             left-hand value for keys that exist on both sides. --}}
                        <a href="{{ route('transactions.index', array_merge(array_filter($filters), $carried, ['start' => $range[0]->toDateString(), 'end' => $range[1]->toDateString(), 'page' => null])) }}"
                           class="rounded-full border border-border bg-card px-2.5 py-1 text-xs shadow-xs
                                  transition-colors hover:bg-muted">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </form>
</x-ui.card>

<x-ui.filter-chips :chips="$chips" :clear-url="route('transactions.index')" class="mb-3" />

<x-ui.card>
    {{-- What this filtered set adds up to, before the rows themselves. The
         total exists so a drill-down from a report reconciles with the figure
         that was clicked to get here. --}}
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 border-b border-border px-5 py-3">
        <div>
            <p class="text-[0.6875rem] uppercase tracking-wide text-subtle">Entries</p>
            <p class="text-sm font-semibold tabular">{{ number_format($transactions->total()) }}</p>
        </div>
        <div>
            <p class="text-[0.6875rem] uppercase tracking-wide text-subtle">Spending in this view</p>
            <p class="text-sm font-semibold tabular">{{ \App\Support\Money::inr($filteredSpending) }}</p>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <span class="hidden text-xs text-muted-foreground sm:inline">Rows</span>
            <div class="segmented">
                @foreach ([25, 50, 100, 200] as $size)
                    <a href="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => null]) }}"
                       @if ($perPage === $size) aria-current="page" @endif
                       class="tabular">{{ $size }}</a>
                @endforeach
            </div>
        </div>
    </div>

    @if ($transactions->isEmpty())
        <x-ui.empty-state icon="inbox" title="No transactions match"
            description="Nothing here for these filters. Clear them, or record your first entry.">
            <x-ui.button :href="route('transactions.index')" variant="outline" size="sm">Clear filters</x-ui.button>
            <x-ui.button :href="route('quick-entry')" size="sm" icon="plus">Record a spend</x-ui.button>
        </x-ui.empty-state>
    @else
        <div class="group/table table-scroll max-h-[calc(100vh-20rem)] scroll-thin">
            <table class="data-table">
                <thead>
                    <tr>
                        <x-ui.sort-header column="date" label="Date" />
                        <th scope="col">Details</th>
                        <th scope="col" class="hidden md:table-cell">Category</th>
                        <th scope="col" class="hidden lg:table-cell">Account</th>
                        <th scope="col" class="hidden xl:table-cell">Who</th>
                        <x-ui.sort-header column="amount" label="Amount" align="right" />
                        <th scope="col"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($transactions as $t)
                    <tr class="row-link">
                        <td class="whitespace-nowrap text-muted-foreground tabular">
                            <span class="block">{{ $t->transaction_date->format('d M Y') }}</span>
                            <span class="block text-[0.6875rem] text-subtle">{{ $t->transaction_date->format('D') }}</span>
                        </td>
                        <td>
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
                        <td class="hidden text-muted-foreground md:table-cell">
                            {{ $t->category?->name ?: '—' }}
                            @if ($t->subcategory)
                                <div class="text-xs text-subtle">{{ $t->subcategory->name }}</div>
                            @endif
                        </td>
                        <td class="hidden text-muted-foreground lg:table-cell">{{ $t->account->name }}</td>
                        <td class="hidden text-xs text-muted-foreground xl:table-cell">
                            @if ($t->payer)<div>by {{ $t->payer->name }}</div>@endif
                            @if ($t->beneficiary)<div>for {{ $t->beneficiary->name }}</div>@endif
                            @if (! $t->payer && ! $t->beneficiary)—@endif
                        </td>
                        <td class="num">
                            <x-finance.money :amount="$t->amount"
                                :tone="$t->type->countsAsSpending() ? 'strong' : 'muted'" />
                        </td>
                        <td class="w-8 pl-0 pr-3">
                            <a href="{{ route('transactions.show', $t) }}"
                               class="flex size-6 items-center justify-center rounded-md text-subtle
                                      transition-colors hover:bg-muted hover:text-foreground">
                                <x-ui.icon name="chevron-right" class="size-4" />
                                <span class="sr-only">Open this entry</span>
                            </a>
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
