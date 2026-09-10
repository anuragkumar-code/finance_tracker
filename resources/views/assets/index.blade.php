@extends('layouts.app')

@section('title', 'Assets')
@section('heading', 'What you own')
@section('subheading', 'Land, vehicles and anything else outside your accounts')

@section('content')

<div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:gap-4">
    <x-ui.stat label="Valued assets" icon="building-2" tone="income"
        :value="\App\Support\Money::inr($netWorth['asset_value'])"
        :hint="$netWorth['unvalued_assets'] > 0
            ? $netWorth['unvalued_assets'].' asset(s) have no value recorded'
            : null" />

    <x-ui.stat label="In accounts" icon="wallet"
        :value="\App\Support\Money::inr($netWorth['account_assets'])"
        hint="bank, cash &amp; investments" />

    <x-ui.stat label="Net worth" icon="scale"
        :tone="bccomp($netWorth['net_worth'], '0', 2) === -1 ? 'expense' : 'default'"
        :value="\App\Support\Money::inr($netWorth['net_worth'])"
        :hint="'after '.\App\Support\Money::inr($netWorth['liabilities']).' owed'" />
</div>

@if ($netWorth['unvalued_assets'] > 0)
    {{-- An incomplete picture is only safe if it says so. --}}
    <div class="mt-4">
        <x-ui.alert variant="muted" title="Net worth is more pessimistic than your real position">
            It counts every rupee you owe, but only assets you have put a value on. With
            {{ $netWorth['unvalued_assets'] }} asset(s) unvalued, the figure above understates what
            you actually have. Add a value whenever you want it counted — leaving it blank is a fine
            choice too.
        </x-ui.alert>
    </div>
@endif

<div class="mt-4 grid gap-4 lg:grid-cols-12">
    <div class="lg:col-span-7">
        <x-ui.card>
            <x-ui.card-header title="Assets" />
            <x-ui.card-content flush>
                @forelse ($assets as $asset)
                    <div x-data="{ editing: false }"
                         class="border-b border-border last:border-0 {{ $asset->is_active ? '' : 'opacity-60' }}">
                        <div class="flex items-start justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-sm font-medium">{{ $asset->name }}</span>
                                    <x-ui.badge variant="secondary">{{ $asset->typeLabel() }}</x-ui.badge>
                                    @if ($asset->owner)
                                        <x-ui.badge variant="outline">{{ $asset->owner->name }}</x-ui.badge>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    @if ($asset->linkedLoan)
                                        Bought with
                                        <a href="{{ route('loans.show', $asset->linkedLoan) }}"
                                           class="underline underline-offset-2 hover:text-foreground">{{ $asset->linkedLoan->name }}</a>
                                        — {{ \App\Support\Money::inr($asset->linkedLoan->remainingAmount()) }} still owed
                                    @elseif ($asset->acquired_on)
                                        Acquired {{ $asset->acquired_on->format('M Y') }}
                                    @else
                                        No loan linked
                                    @endif
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                @if ($asset->hasValue())
                                    <x-finance.money :amount="$asset->current_value" tone="strong" class="text-sm" />
                                    @if ($asset->valued_on)
                                        <p class="text-xs text-muted-foreground">
                                            valued {{ $asset->valued_on->format('M Y') }}
                                        </p>
                                    @endif
                                @else
                                    <p class="text-xs text-muted-foreground">no value set</p>
                                @endif
                                <button type="button" x-on:click="editing = !editing"
                                        class="mt-0.5 text-xs text-muted-foreground hover:text-foreground">Edit</button>
                            </div>
                        </div>

                        <div x-show="editing" x-cloak class="border-t border-border bg-muted/40 px-5 py-4">
                            <form method="POST" action="{{ route('assets.update', $asset) }}"
                                  class="grid gap-3 sm:grid-cols-2">
                                @csrf @method('PUT')
                                <x-ui.input label="Name" name="name" :value="$asset->name" required />

                                <x-ui.select label="Type" name="type">
                                    @foreach ($types as $value => $label)
                                        <option value="{{ $value }}" @selected($asset->type === $value)>{{ $label }}</option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.input label="Value" name="current_value" inputmode="decimal" prefix="₹"
                                    :value="$asset->current_value" hint="Optional" />

                                <x-ui.select label="Whose" name="owner_id">
                                    <option value="">—</option>
                                    @foreach ($owners as $owner)
                                        <option value="{{ $owner->id }}" @selected($asset->owner_id == $owner->id)>
                                            {{ $owner->name }}
                                        </option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.select label="Bought with loan" name="linked_loan_id">
                                    <option value="">—</option>
                                    @foreach ($loans as $loan)
                                        <option value="{{ $loan->id }}" @selected($asset->linked_loan_id == $loan->id)>
                                            {{ $loan->name }}
                                        </option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.input label="Acquired" name="acquired_on" type="date"
                                    :value="optional($asset->acquired_on)->toDateString()" />

                                <div class="flex items-end">
                                    <x-ui.checkbox name="is_active" :checked="$asset->is_active" label="Active" />
                                </div>

                                <div class="sm:col-span-2">
                                    <x-ui.button type="submit" size="sm">Save</x-ui.button>
                                    <x-ui.button type="button" variant="ghost" size="sm" x-on:click="editing = false">
                                        Cancel
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state icon="building-2" title="Nothing recorded yet"
                        description="If you have a loan against something — land, a vehicle — adding it here shows what the debt bought." />
                @endforelse
            </x-ui.card-content>
        </x-ui.card>
    </div>

    <div class="lg:col-span-5">
        <x-ui.card>
            <x-ui.card-header title="Add an asset" />
            <form method="POST" action="{{ route('assets.store') }}">
                @csrf
                <x-ui.card-content class="space-y-4">
                    <x-ui.input label="What is it" name="name" placeholder="Land at ..." required />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select label="Type" name="type">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select label="Whose" name="owner_id">
                            <option value="">—</option>
                            @foreach ($owners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <x-ui.input label="Value" name="current_value" inputmode="decimal" prefix="₹"
                        hint="Leave blank if you would rather not put a number on it. The asset is still recorded; it just will not count towards net worth." />

                    <x-ui.select label="Bought with loan" name="linked_loan_id" hint="Optional">
                        <option value="">—</option>
                        @foreach ($loans as $loan)
                            <option value="{{ $loan->id }}">{{ $loan->name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input label="Acquired" name="acquired_on" type="date" hint="Optional" />
                </x-ui.card-content>

                <div class="border-t border-border px-5 py-3.5">
                    <x-ui.button type="submit" class="w-full">Add asset</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
@endsection
