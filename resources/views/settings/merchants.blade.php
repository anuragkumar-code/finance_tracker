@extends('layouts.app')

@section('title', 'Merchants')
@section('heading', 'Merchants')
@section('subheading', 'The master list behind the quick-entry picker')

@section('content')
@php
    // Merchants sit under their group in the order the master defines, with
    // anything unfiled collecting at the end where it asks to be dealt with.
    $byGroup = $merchants->groupBy(fn ($merchant) => $merchant->group?->name ?? 'Ungrouped');

    $sections = $groups->pluck('name')
        ->filter(fn ($name) => $byGroup->has($name))
        ->push('Ungrouped')
        ->unique()
        ->filter(fn ($name) => $byGroup->has($name))
        ->values();

    // Channel badge styling, worked out here rather than inline in the
    // attribute: a ternary inside a :bound attribute can make Blade's component
    // compiler give up on the tag silently.
    $channelVariant = fn ($channel) => match ($channel?->value) {
        'quick_commerce' => 'destructive',
        'ecommerce' => 'default',
        'food_delivery' => 'warning',
        'subscription' => 'info',
        default => 'secondary',
    };
@endphp

<div class="grid gap-4 lg:grid-cols-12">
    <div class="space-y-4 lg:col-span-8">
        @foreach ($sections as $sectionName)
            @php($items = $byGroup->get($sectionName))
            <x-ui.card>
                <x-ui.card-header :title="$sectionName"
                    :description="$items->count().' '.\Illuminate\Support\Str::plural('merchant', $items->count())" />
                <x-ui.card-content flush>
                    @foreach ($items as $merchant)
                        @php($inactiveClass = $merchant->is_active ? '' : 'opacity-60')
                        <div x-data="{ editing: false }"
                             class="border-b border-border last:border-0 {{ $inactiveClass }}">
                            <div class="flex items-start justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="text-sm font-medium">{{ $merchant->name }}</span>
                                        <x-ui.badge :variant="$channelVariant($merchant->channel)">
                                            {{ $merchant->channel?->label() ?? 'Unclassified' }}
                                        </x-ui.badge>
                                    </div>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        {{ $merchant->defaultCategory?->name ?: 'No category' }}
                                        @if ($merchant->defaultAccount) · {{ $merchant->defaultAccount->name }} @endif
                                        @if ($merchant->defaultBeneficiary) · for {{ $merchant->defaultBeneficiary->name }} @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="text-xs text-muted-foreground tabular">
                                        {{ $merchant->transactions_count }} entries
                                    </span>
                                    <button type="button" x-on:click="editing = !editing"
                                            class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                            aria-label="Edit {{ $merchant->name }}">
                                        <x-ui.icon name="pencil" class="size-3.5" />
                                    </button>
                                </div>
                            </div>

                            <div x-show="editing" x-cloak class="border-t border-border bg-muted/40 px-5 py-4">
                                <form method="POST" action="{{ route('settings.merchants.update', $merchant) }}"
                                      class="grid gap-3 sm:grid-cols-2">
                                    @csrf @method('PUT')
                                    <x-ui.input label="Name" name="name" :value="$merchant->name" required />

                                    <x-ui.select label="Group" name="merchant_group_id"
                                        hint="What kind of place this is">
                                        <option value="">Ungrouped</option>
                                        @foreach ($groups as $group)
                                            <option value="{{ $group->id }}" @selected($merchant->merchant_group_id == $group->id)>
                                                {{ $group->name }}
                                            </option>
                                        @endforeach
                                    </x-ui.select>

                                    <x-ui.select label="How you buy" name="channel"
                                        hint="Separate from group — this is what drives the quick-commerce figures.">
                                        @foreach ($channels as $channel)
                                            <option value="{{ $channel->value }}" @selected($merchant->channel === $channel)>
                                                {{ $channel->label() }}
                                            </option>
                                        @endforeach
                                    </x-ui.select>

                                    <x-ui.select label="Category" name="default_category_id">
                                        <option value="">—</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected($merchant->default_category_id == $category->id)>
                                                {{ $category->full_name }}
                                            </option>
                                        @endforeach
                                    </x-ui.select>

                                    <x-ui.select label="Account" name="default_account_id">
                                        <option value="">—</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}" @selected($merchant->default_account_id == $account->id)>
                                                {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </x-ui.select>

                                    <x-ui.select label="For" name="default_beneficiary_id">
                                        <option value="">—</option>
                                        @foreach ($beneficiaries as $person)
                                            <option value="{{ $person->id }}" @selected($merchant->default_beneficiary_id == $person->id)>
                                                {{ $person->name }}
                                            </option>
                                        @endforeach
                                    </x-ui.select>

                                    <div class="flex items-end">
                                        <x-ui.checkbox name="is_active" :checked="$merchant->is_active" label="Active" />
                                    </div>

                                    <div class="flex gap-2 sm:col-span-2">
                                        <x-ui.button type="submit" size="sm">Save</x-ui.button>
                                        <x-ui.button type="button" variant="ghost" size="sm" x-on:click="editing = false">
                                            Cancel
                                        </x-ui.button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </x-ui.card-content>
            </x-ui.card>
        @endforeach

        @if ($merchants->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="store" title="No merchants yet"
                    description="They are created as you record spending, or you can add one on the right." />
            </x-ui.card>
        @endif
    </div>

    <div class="space-y-4 lg:col-span-4">
        {{--
            The group master.

            Groups are what turn the merchant picker from one long alphabetical
            list into something scannable, so they are curated here rather than
            being invented ad hoc while entering a transaction.
        --}}
        <x-ui.card x-data="{ adding: false }">
            <x-ui.card-header title="Groups" description="Families of merchant, in picker order">
                <x-slot:action>
                    <button type="button" x-on:click="adding = !adding"
                            class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                        <x-ui.icon name="plus" class="size-3.5" />
                        New
                    </button>
                </x-slot:action>
            </x-ui.card-header>

            <div x-show="adding" x-cloak class="border-b border-border bg-muted/40 px-5 py-4">
                <form method="POST" action="{{ route('settings.merchant-groups.store') }}" class="space-y-3">
                    @csrf
                    <x-ui.input label="Name" name="name" placeholder="Cabs & rides" required />
                    <x-ui.select label="Usual channel" name="default_channel"
                        hint="Applied to new merchants filed here. Leave blank if they vary.">
                        <option value="">—</option>
                        @foreach ($channels as $channel)
                            <option value="{{ $channel->value }}">{{ $channel->label() }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input label="Order" name="sort_order" type="number" value="100"
                        hint="Lower numbers appear first in the picker." />
                    <div class="flex gap-2">
                        <x-ui.button type="submit" size="sm">Add group</x-ui.button>
                        <x-ui.button type="button" variant="ghost" size="sm" x-on:click="adding = false">
                            Cancel
                        </x-ui.button>
                    </div>
                </form>
            </div>

            <x-ui.card-content flush>
                @foreach ($groups as $group)
                    @php($groupInactive = $group->is_active ? '' : 'opacity-60')
                    <div x-data="{ editing: false }"
                         class="border-b border-border last:border-0 {{ $groupInactive }}">
                        <div class="flex items-center justify-between gap-3 px-5 py-2.5">
                            <div class="min-w-0">
                                <span class="block truncate text-sm font-medium">{{ $group->name }}</span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ $group->merchants_count }} {{ \Illuminate\Support\Str::plural('merchant', $group->merchants_count) }}
                                    @if ($group->default_channel) · {{ $group->default_channel->label() }} @endif
                                </span>
                            </div>
                            <button type="button" x-on:click="editing = !editing"
                                    class="shrink-0 rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                    aria-label="Edit {{ $group->name }}">
                                <x-ui.icon name="pencil" class="size-3.5" />
                            </button>
                        </div>

                        <div x-show="editing" x-cloak class="border-t border-border bg-muted/40 px-5 py-4">
                            <form method="POST" action="{{ route('settings.merchant-groups.update', $group) }}"
                                  class="space-y-3">
                                @csrf @method('PUT')
                                <x-ui.input label="Name" name="name" :value="$group->name" required />
                                <x-ui.select label="Usual channel" name="default_channel">
                                    <option value="">—</option>
                                    @foreach ($channels as $channel)
                                        <option value="{{ $channel->value }}" @selected($group->default_channel === $channel)>
                                            {{ $channel->label() }}
                                        </option>
                                    @endforeach
                                </x-ui.select>
                                <x-ui.input label="Order" name="sort_order" type="number" :value="$group->sort_order" />
                                <x-ui.checkbox name="is_active" :checked="$group->is_active" label="Active" />
                                <div class="flex gap-2">
                                    <x-ui.button type="submit" size="sm">Save</x-ui.button>
                                    <x-ui.button type="button" variant="ghost" size="sm" x-on:click="editing = false">
                                        Cancel
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
            </x-ui.card-content>
            <x-ui.card-footer>
                Hiding a group takes it out of the picker but leaves the merchants in it where they are.
            </x-ui.card-footer>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header title="Add a merchant" />
            <form method="POST" action="{{ route('settings.merchants.store') }}">
                @csrf
                <x-ui.card-content class="space-y-4">
                    <x-ui.input label="Name" name="name" placeholder="Amazon" required />

                    <x-ui.select label="Group" name="merchant_group_id">
                        <option value="">Ungrouped</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select label="How you buy from them" name="channel"
                        hint="Drives the quick-commerce and online-shopping figures in reports.">
                        @foreach ($channels as $channel)
                            <option value="{{ $channel->value }}" @selected($channel->value === 'offline')>
                                {{ $channel->label() }} — {{ $channel->hint() }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select label="Usual category" name="default_category_id">
                        <option value="">—</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->full_name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.button type="submit" class="w-full">Add merchant</x-ui.button>
                </x-ui.card-content>
            </form>
        </x-ui.card>

        <x-ui.alert variant="muted" icon="info" title="Group and channel are different things">
            The group says what kind of place it is; the channel says how you buy. Blinkit can sit
            under E-commerce and still count as quick commerce in reports — which is the point of
            keeping the two apart.
        </x-ui.alert>
    </div>
</div>
@endsection
