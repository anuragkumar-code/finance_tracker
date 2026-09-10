@extends('layouts.app')

@section('title', 'Recurring')
@section('heading', 'Recurring commitments')
@section('subheading', 'Rent, bills, subscriptions, family support — expected, never assumed')

@section('content')
<div class="grid gap-4 lg:grid-cols-12">
    <div class="space-y-4 lg:col-span-7">

        @if ($due->isNotEmpty())
            <x-ui.card>
                <x-ui.card-header title="Due now and soon"
                    description="Nothing is recorded until you confirm it" />
                <x-ui.card-content flush>
                    <ul class="divide-y divide-border">
                        @foreach ($due as $occurrence)
                            <li class="flex flex-wrap items-center gap-3 px-5 py-3
                                       {{ $occurrence->isOverdue() ? 'bg-warning/[0.07]' : '' }}">
                                <div class="w-14 shrink-0">
                                    <p class="text-sm font-medium tabular">{{ $occurrence->due_date->format('d M') }}</p>
                                    @if ($occurrence->isOverdue())
                                        <p class="text-xs text-destructive">overdue</p>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">{{ $occurrence->recurringTransaction->name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ $occurrence->recurringTransaction->account->name }}
                                    </p>
                                </div>
                                <x-finance.money :amount="$occurrence->amount" tone="strong" class="text-sm" />
                                <div class="flex shrink-0 gap-1.5">
                                    <x-ui.button variant="outline" size="sm"
                                        x-on:click="$dispatch('open-dialog', 'confirm-{{ $occurrence->id }}')">
                                        Confirm
                                    </x-ui.button>
                                    <form method="POST" action="{{ route('recurring.occurrences.skip', $occurrence) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="ghost" size="sm">Skip</x-ui.button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card-content>
                <x-ui.card-footer>
                    The app never assumes a payment went out — each date waits here until you say
                    it happened.
                </x-ui.card-footer>
            </x-ui.card>
        @endif

        <x-ui.card>
            <x-ui.card-header title="All commitments" />
            <x-ui.card-content flush>
                @forelse ($commitments as $commitment)
                    <div x-data="{ editing: false }"
                         class="border-b border-border last:border-0 {{ $commitment->is_active ? '' : 'opacity-60' }}">
                        <div class="flex items-start justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-sm font-medium">{{ $commitment->name }}</span>
                                    <x-ui.badge variant="secondary">{{ $commitment->frequency->label() }}</x-ui.badge>
                                    @unless ($commitment->is_active)
                                        <x-ui.badge variant="outline">Stopped</x-ui.badge>
                                    @endunless
                                </div>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ $commitment->account->name }}@if ($commitment->category) · {{ $commitment->category->name }} @endif
                                    · next {{ $commitment->next_due_date->format('d M Y') }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <x-finance.money :amount="$commitment->amount" tone="strong" class="text-sm" />
                                <button type="button" x-on:click="editing = !editing"
                                        class="mt-0.5 block text-xs text-muted-foreground hover:text-foreground">
                                    Edit
                                </button>
                            </div>
                        </div>

                        <div x-show="editing" x-cloak class="border-t border-border bg-muted/40 px-5 py-4">
                            <form method="POST" action="{{ route('recurring.update', $commitment) }}"
                                  class="grid gap-3 sm:grid-cols-2">
                                @csrf @method('PUT')
                                <x-ui.input label="Name" name="name" :value="$commitment->name" required />
                                <x-ui.input label="Amount" name="amount" inputmode="decimal" prefix="₹"
                                    :value="$commitment->amount" required />

                                <x-ui.select label="How often" name="frequency">
                                    @foreach ($frequencies as $frequency)
                                        <option value="{{ $frequency->value }}" @selected($commitment->frequency === $frequency)>
                                            {{ $frequency->label() }}
                                        </option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.input label="Next due" name="next_due_date" type="date"
                                    :value="$commitment->next_due_date->toDateString()" required />

                                <x-ui.select label="Account" name="account_id" required>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}" @selected($commitment->account_id == $account->id)>
                                            {{ $account->name }}
                                        </option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.select label="Category" name="category_id">
                                    <option value="">—</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected($commitment->category_id == $category->id)>
                                            {{ $category->full_name }}
                                        </option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.select label="Type" name="type">
                                    <option value="expense" @selected($commitment->type->value === 'expense')>Money out</option>
                                    <option value="income" @selected($commitment->type->value === 'income')>Money in</option>
                                </x-ui.select>

                                <div class="flex items-end gap-2">
                                    <x-ui.checkbox name="is_active" :checked="$commitment->is_active" label="Active" />
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
                    <x-ui.empty-state icon="repeat" title="No commitments yet"
                        description="Add rent, utilities, subscriptions or regular family support so they show up in Upcoming before they hit your account." />
                @endforelse
            </x-ui.card-content>
        </x-ui.card>
    </div>

    <div class="lg:col-span-5">
        <x-ui.card>
            <x-ui.card-header title="Add a commitment" />
            <form method="POST" action="{{ route('recurring.store') }}">
                @csrf
                <x-ui.card-content class="space-y-4">
                    <x-ui.input label="What is it" name="name" placeholder="Rent" required />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input label="Amount" name="amount" inputmode="decimal" prefix="₹" required />
                        <x-ui.select label="Type" name="type">
                            <option value="expense">Money out</option>
                            <option value="income">Money in</option>
                        </x-ui.select>

                        <x-ui.select label="How often" name="frequency">
                            @foreach ($frequencies as $frequency)
                                <option value="{{ $frequency->value }}" @selected($frequency->value === 'monthly')>
                                    {{ $frequency->label() }}
                                </option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input label="Next due" name="next_due_date" type="date"
                            :value="now()->toDateString()" required />
                    </div>

                    <x-ui.select label="Account" name="account_id" required>
                        <option value="">Choose…</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select label="Category" name="category_id">
                        <option value="">—</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->full_name }}</option>
                        @endforeach
                    </x-ui.select>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select label="For" name="beneficiary_id">
                            <option value="">—</option>
                            @foreach ($beneficiaries as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input label="Ends" name="end_date" type="date" hint="Optional" />
                    </div>
                </x-ui.card-content>

                <div class="border-t border-border px-5 py-3.5">
                    <x-ui.button type="submit" class="w-full">Add commitment</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>

@foreach ($due as $occurrence)
    <x-ui.dialog id="confirm-{{ $occurrence->id }}"
        :title="'Confirm '.$occurrence->recurringTransaction->name">
        <form method="POST" action="{{ route('recurring.occurrences.confirm', $occurrence) }}">
            @csrf
            <div class="space-y-4 px-5 py-4">
                <p class="text-sm text-muted-foreground">
                    This records the real transaction against
                    {{ $occurrence->recurringTransaction->account->name }}. Adjust the amount if the
                    actual bill differed.
                </p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Actual amount" name="amount" inputmode="decimal" prefix="₹"
                        :value="$occurrence->amount" />
                    <x-ui.input label="Paid on" name="paid_on" type="date"
                        :value="$occurrence->due_date->toDateString()" />
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-5 py-3">
                <x-ui.button type="button" variant="ghost"
                    x-on:click="$dispatch('close-dialog', 'confirm-{{ $occurrence->id }}')">Cancel</x-ui.button>
                <x-ui.button type="submit">Record it</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
@endforeach
@endsection
