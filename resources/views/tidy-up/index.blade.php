@extends('layouts.app')

@section('title', 'Tidy up')
@section('heading', 'Tidy up')
@section('subheading', 'Entries that are still missing a label, biggest first')

@section('content')
@php
    $needsTier = \App\Services\TransactionCompletenessService::NEEDS;
    $richerTier = \App\Services\TransactionCompletenessService::RICHER;
    $thisMonth = $completeness->last();
    $field = 'h-9 w-full rounded-md border border-input bg-card px-3 text-[0.8125rem] shadow-xs '
        .'focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25';
@endphp

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    <x-ui.stat label="Needs you" icon="alert-triangle"
        :value="(string) $counts[$needsTier]"
        :tone="$counts[$needsTier] > 0 ? 'expense' : 'income'"
        hint="Category, planned, purpose" />

    <x-ui.stat label="Could be richer" icon="sliders-horizontal"
        :value="(string) $counts[$richerTier]"
        hint="Merchant, subcategory, who" />

    <x-ui.stat :label="$thisMonth->label.' tagged'" icon="check-circle"
        :value="$thisMonth->percent.'%'"
        :hint="$thisMonth->missing > 0 ? $thisMonth->missing.' of '.$thisMonth->total.' still open' : 'All done'" />

    <x-ui.stat label="Why it matters" icon="info" value="Reports"
        hint="Untagged spend cannot be reported on" />
</div>

<div class="mt-5 flex flex-wrap items-center justify-between gap-3">
    <div class="segmented">
        <a href="{{ route('tidy-up.index', ['sort' => $sort]) }}"
           @if ($tier === $needsTier) aria-current="page" @endif>
            Needs you
            <span class="ml-1 text-xs text-muted-foreground tabular">{{ $counts[$needsTier] }}</span>
        </a>
        <a href="{{ route('tidy-up.index', ['tier' => $richerTier, 'sort' => $sort]) }}"
           @if ($tier === $richerTier) aria-current="page" @endif>
            Could be richer
            <span class="ml-1 text-xs text-muted-foreground tabular">{{ $counts[$richerTier] }}</span>
        </a>
    </div>

    <div class="flex items-center gap-2">
        <span class="text-xs text-muted-foreground">Sort</span>
        <div class="segmented">
            <a href="{{ route('tidy-up.index', ['tier' => $tier]) }}"
               @if ($sort === 'amount') aria-current="page" @endif>Biggest</a>
            <a href="{{ route('tidy-up.index', ['tier' => $tier, 'sort' => 'date']) }}"
               @if ($sort === 'date') aria-current="page" @endif>Newest</a>
        </div>
    </div>
</div>

@if ($entries->isEmpty())
    <x-ui.card class="mt-4">
        <x-ui.empty-state icon="check-circle" title="Nothing to tidy here"
            :description="$tier === $needsTier
                ? 'Every entry has a category, a planned status and a purpose. Reports are reading the full picture.'
                : 'Every hand-typed entry names its merchant and who it was for.'">
            <x-ui.button :href="route('reports.index')" variant="outline" size="sm">See the reports</x-ui.button>
        </x-ui.empty-state>
    </x-ui.card>
@else
    <div class="mt-4 space-y-3">
        @foreach ($entries as $entry)
            @php
                $entryGaps = $gaps[$entry->id] ?? [];
                $children = $entry->category?->children ?? collect();
            @endphp

            <x-ui.card x-data="{ open: false }">
                <div class="flex flex-wrap items-center gap-3 px-5 py-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <a href="{{ route('transactions.show', $entry) }}"
                               class="text-sm font-medium hover:underline">
                                {{ $entry->description ?: $entry->merchant?->name ?: 'Untitled spend' }}
                            </a>
                            @foreach ($entryGaps as $gap)
                                <x-ui.badge :variant="$gap['tier'] === $needsTier ? 'warning' : 'secondary'">
                                    {{ $gap['label'] }}
                                </x-ui.badge>
                            @endforeach
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ $entry->transaction_date->format('d M Y') }} · {{ $entry->account->name }}
                            @if ($entry->category) · {{ $entry->category->name }} @endif
                        </p>
                    </div>

                    <x-finance.money :amount="$entry->amount" tone="strong" class="text-sm" />

                    <x-ui.button type="button" size="sm" variant="outline" x-on:click="open = !open">
                        <span x-text="open ? 'Close' : 'Fill in'">Fill in</span>
                    </x-ui.button>
                </div>

                <div x-show="open" x-cloak class="border-t border-border bg-muted/40 px-5 py-4">
                    <form method="POST" action="{{ route('tidy-up.update', $entry) }}"
                          x-data="{ category: '{{ $entry->category_id }}' }">
                        @csrf
                        @method('PUT')

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-muted-foreground">Category</span>
                                <select name="category_id" x-model="category" class="{{ $field }}">
                                    <option value="">—</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected($entry->category_id == $category->id)>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            {{-- Only the chosen category's own children are offered, so a
                                 subcategory can never end up under the wrong parent. --}}
                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-muted-foreground">Subcategory</span>
                                <select name="subcategory_id" class="{{ $field }}">
                                    <option value="">—</option>
                                    @foreach ($categories as $category)
                                        @foreach ($category->children as $child)
                                            <option value="{{ $child->id }}"
                                                    x-show="category === '{{ $category->id }}'"
                                                    @selected($entry->subcategory_id == $child->id)>
                                                {{ $child->name }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-muted-foreground">Merchant</span>
                                <select name="merchant_id" class="{{ $field }}">
                                    <option value="">—</option>
                                    @foreach ($merchants as $merchant)
                                        <option value="{{ $merchant->id }}" @selected($entry->merchant_id == $merchant->id)>
                                            {{ $merchant->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-muted-foreground">Planned?</span>
                                <select name="planned_status" class="{{ $field }}">
                                    <option value="">—</option>
                                    @foreach ($plannedStatuses as $status)
                                        <option value="{{ $status->value }}" @selected($entry->planned_status?->value === $status->value)>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-muted-foreground">Purpose</span>
                                <select name="purpose" class="{{ $field }}">
                                    <option value="">—</option>
                                    @foreach ($purposes as $purpose)
                                        <option value="{{ $purpose->value }}" @selected($entry->purpose?->value === $purpose->value)>
                                            {{ $purpose->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-muted-foreground">Who paid</span>
                                <select name="payer_id" class="{{ $field }}">
                                    <option value="">—</option>
                                    @foreach ($payers as $person)
                                        <option value="{{ $person->id }}" @selected($entry->payer_id == $person->id)>
                                            {{ $person->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-medium text-muted-foreground">Who it was for</span>
                                <select name="beneficiary_id" class="{{ $field }}">
                                    <option value="">—</option>
                                    @foreach ($beneficiaries as $person)
                                        <option value="{{ $person->id }}" @selected($entry->beneficiary_id == $person->id)>
                                            {{ $person->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block sm:col-span-2">
                                <span class="mb-1 block text-xs font-medium text-muted-foreground">Note</span>
                                <input type="text" name="description" value="{{ $entry->description }}"
                                       class="{{ $field }}" placeholder="What was it for?">
                            </label>
                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            <x-ui.button type="submit" size="sm">Save</x-ui.button>
                            <x-ui.button type="button" variant="ghost" size="sm" x-on:click="open = false">Cancel</x-ui.button>
                            <span class="ml-auto text-xs text-muted-foreground">
                                Amount, account and date are not touched here
                            </span>
                        </div>
                    </form>
                </div>
            </x-ui.card>
        @endforeach
    </div>

    @if ($entries->hasPages())
        <div class="mt-4">{{ $entries->links() }}</div>
    @endif
@endif

<x-ui.card class="mt-6">
    <x-ui.card-header title="How complete each month is" description="Measured on category, planned and purpose" />
    <x-ui.card-content flush>
        <ul class="divide-y divide-border">
            @foreach ($completeness as $month)
                <li class="flex items-center gap-4 px-5 py-2.5">
                    <span class="w-20 shrink-0 text-sm">{{ $month->label }}</span>
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full {{ $month->percent === 100 ? 'bg-income' : 'bg-warning' }}"
                             style="width: {{ $month->percent }}%"></div>
                    </div>
                    <span class="w-10 shrink-0 text-right text-sm tabular">{{ $month->percent }}%</span>
                    <span class="w-24 shrink-0 text-right text-xs text-muted-foreground">
                        {{ $month->total }} {{ \Illuminate\Support\Str::plural('entry', $month->total) }}
                    </span>
                </li>
            @endforeach
        </ul>
    </x-ui.card-content>
</x-ui.card>
@endsection
