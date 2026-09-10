@extends('layouts.app')

@section('title', 'Merchants')
@section('heading', 'Merchants')
@section('subheading', 'What each place usually means, so quick entry can fill itself in')

@section('content')
<div class="grid gap-4 lg:grid-cols-12">
    <div class="lg:col-span-8">
        <x-ui.card>
            <x-ui.card-header title="All merchants" />
            <x-ui.card-content flush>
                @forelse ($merchants as $merchant)
                    <div x-data="{ editing: false }"
                         class="border-b border-border last:border-0 {{ $merchant->is_active ? '' : 'opacity-60' }}">
                        <div class="flex items-start justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-sm font-medium">{{ $merchant->name }}</span>
                                    <x-ui.badge :variant="match($merchant->channel->value) {
                                        'quick_commerce' => 'destructive',
                                        'ecommerce' => 'default',
                                        'food_delivery' => 'warning',
                                        'subscription' => 'info',
                                        default => 'secondary',
                                    }">{{ $merchant->channel->label() }}</x-ui.badge>
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

                                <x-ui.select label="How you buy" name="channel">
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
                @empty
                    <x-ui.empty-state icon="store" title="No merchants yet"
                        description="They are created automatically as you type them into Quick Entry." />
                @endforelse
            </x-ui.card-content>
            <x-ui.card-footer>
                A channel you set by hand is never overwritten by the name-matching guesser.
            </x-ui.card-footer>
        </x-ui.card>
    </div>

    <div class="lg:col-span-4">
        <x-ui.card>
            <x-ui.card-header title="Add a merchant" />
            <form method="POST" action="{{ route('settings.merchants.store') }}">
                @csrf
                <x-ui.card-content class="space-y-4">
                    <x-ui.input label="Name" name="name" placeholder="Amazon" required />

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

                    <x-ui.select label="Usual account" name="default_account_id">
                        <option value="">—</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.button type="submit" class="w-full">Add merchant</x-ui.button>
                </x-ui.card-content>
            </form>
        </x-ui.card>
    </div>
</div>
@endsection
