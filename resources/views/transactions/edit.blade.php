@extends('layouts.app')

@section('title', 'Edit entry')
@section('heading', 'Edit entry')
@section('subheading', 'Balances are recalculated automatically when you save')

@section('content')
@php
    $isTransfer = $transaction->type === \App\Enums\TransactionType::Transfer;
    $fromLeg = $isTransfer ? ($transaction->leg_role->isSource() ? $transaction : $counterpart) : null;
    $toLeg = $isTransfer ? ($transaction->leg_role->isSource() ? $counterpart : $transaction) : null;
@endphp

<div class="max-w-2xl">
    <form method="POST" action="{{ route('transactions.update', $transaction) }}">
        @csrf
        @method('PUT')

        <x-ui.card>
            <x-ui.card-content class="space-y-4">
                @if ($transaction->isSettlementEntry())
                    <x-ui.alert variant="warning" title="Written by settling up">
                        This entry was created when a shared cost was settled. Saving changes here is
                        refused — undo the settlement on the trip page instead.
                    </x-ui.alert>
                @elseif ($transaction->hasSharedPortion())
                    <x-ui.alert variant="info" title="Part of this was a friend's share">
                        The amount, account and date are locked while that settlement stands, because
                        the share moved to your friend has to keep matching. Category, merchant, trip
                        and note can still change.
                    </x-ui.alert>
                @endif

                @if ($isTransfer)
                    <x-ui.alert variant="info">
                        Both sides of this transfer update together, so the two amounts can never
                        disagree.
                    </x-ui.alert>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select label="From" name="from_account_id" required>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}"
                                        @selected(old('from_account_id', $fromLeg?->account_id) == $account->id)>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.select label="To" name="to_account_id" required>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}"
                                        @selected(old('to_account_id', $toLeg?->account_id) == $account->id)>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>
                @else
                    <x-ui.select label="Account" name="account_id" required
                        hint="Switching between a bank account and a card flips how this affects the balance — that is handled for you.">
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}"
                                    @selected(old('account_id', $transaction->account_id) == $account->id)>
                                {{ $account->name }}@if ($account->owner) · {{ $account->owner->name }}@endif
                            </option>
                        @endforeach
                    </x-ui.select>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Amount" name="amount" inputmode="decimal" prefix="₹"
                        :value="old('amount', $transaction->amount)" required />
                    <x-ui.input label="Date" name="transaction_date" type="date"
                        :value="old('transaction_date', $transaction->transaction_date->toDateString())" required />
                </div>

                @unless ($isTransfer)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select label="Category" name="category_id">
                            <option value="">—</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}"
                                        @selected(old('category_id', $transaction->category_id) == $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select label="Merchant" name="merchant_id">
                            <option value="">—</option>
                            @foreach ($merchants as $merchant)
                                <option value="{{ $merchant->id }}"
                                        @selected(old('merchant_id', $transaction->merchant_id) == $merchant->id)>
                                    {{ $merchant->name }}
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select label="Paid by" name="payer_id">
                            <option value="">—</option>
                            @foreach ($payers as $person)
                                <option value="{{ $person->id }}"
                                        @selected(old('payer_id', $transaction->payer_id) == $person->id)>
                                    {{ $person->name }}
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select label="For" name="beneficiary_id">
                            <option value="">—</option>
                            @foreach ($beneficiaries as $person)
                                <option value="{{ $person->id }}"
                                        @selected(old('beneficiary_id', $transaction->beneficiary_id) == $person->id)>
                                    {{ $person->name }}
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select label="Trip or event" name="event_id">
                            <option value="">—</option>
                            @foreach ($events as $event)
                                <option value="{{ $event->id }}"
                                        @selected(old('event_id', $transaction->event_id) == $event->id)>
                                    {{ $event->name }}
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select label="Planned" name="planned_status">
                            <option value="">—</option>
                            @foreach ($plannedStatuses as $status)
                                <option value="{{ $status->value }}"
                                        @selected(old('planned_status', $transaction->planned_status?->value) === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select label="Purpose" name="purpose">
                            <option value="">—</option>
                            @foreach ($purposes as $purpose)
                                <option value="{{ $purpose->value }}"
                                        @selected(old('purpose', $transaction->purpose?->value) === $purpose->value)>
                                    {{ $purpose->label() }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>
                @endunless

                <x-ui.input label="Note" name="description"
                    :value="old('description', $transaction->description)" />
            </x-ui.card-content>

            <div class="flex gap-2 border-t border-border px-5 py-3.5">
                <x-ui.button type="submit">Save changes</x-ui.button>
                <x-ui.button :href="route('transactions.show', $transaction)" variant="ghost">Cancel</x-ui.button>
            </div>
        </x-ui.card>
    </form>
</div>
@endsection
