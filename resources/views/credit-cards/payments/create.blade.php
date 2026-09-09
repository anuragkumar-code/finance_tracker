@extends('layouts.app')

@section('title', 'Pay card bill')
@section('heading', 'Pay '.$card->card_name)
@section('subheading', 'Clears what you owe — this is not counted as spending')

@section('content')
<div class="grid gap-4 lg:grid-cols-12">
    <div class="lg:col-span-7">
        <form method="POST" action="{{ route('credit-cards.payments.store', $card) }}">
            @csrf
            <x-ui.card>
                <x-ui.card-content class="space-y-4">
                    <x-ui.alert variant="info">
                        Paying this bill moves money out of your bank account and reduces what you
                        owe on the card. The purchases were already counted as spending when you made
                        them, so this payment will <strong class="text-foreground">not</strong> be
                        counted again.
                    </x-ui.alert>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input label="Amount" name="amount" inputmode="decimal" prefix="₹"
                            :value="old('amount', $suggestedAmount)" required autofocus
                            :hint="'Currently owed on this card: '.\App\Support\Money::inr($card->outstanding())" />

                        <x-ui.input label="Paid on" name="payment_date" type="date"
                            :value="old('payment_date', now()->toDateString())" required />

                        <x-ui.select label="Paid from" name="source_account_id" required>
                            <option value="">Choose…</option>
                            @foreach ($sourceAccounts as $account)
                                <option value="{{ $account->id }}" @selected(old('source_account_id') == $account->id)>
                                    {{ $account->name }} ({{ \App\Support\Money::inr($account->cached_balance) }})
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select label="Against statement" name="statement_id"
                            hint="Links the payment so the bill is marked paid. Optional.">
                            <option value="">Not tied to a statement</option>
                            @foreach ($statements as $s)
                                <option value="{{ $s->id }}" @selected(old('statement_id', $statement?->id) == $s->id)>
                                    {{ $s->period_end->format('d M Y') }} — {{ \App\Support\Money::inr($s->balanceRemaining()) }} left
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <x-ui.textarea label="Notes" name="notes" rows="2" hint="Optional">{{ old('notes') }}</x-ui.textarea>
                </x-ui.card-content>

                <div class="flex gap-2 border-t border-border px-5 py-3.5">
                    <x-ui.button type="submit">Record payment</x-ui.button>
                    <x-ui.button :href="route('credit-cards.show', $card)" variant="ghost">Cancel</x-ui.button>
                </div>
            </x-ui.card>
        </form>
    </div>

    <div class="lg:col-span-5">
        <x-ui.card>
            <x-ui.card-header title="What this will do" />
            <x-ui.card-content flush>
                <ul class="divide-y divide-border">
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                        <span>Your bank balance</span>
                        <span class="inline-flex items-center gap-1 text-expense">
                            <x-ui.icon name="arrow-down-right" class="size-3.5" /> goes down
                        </span>
                    </li>
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                        <span>Owed on {{ $card->card_name }}</span>
                        <span class="inline-flex items-center gap-1 text-income">
                            <x-ui.icon name="arrow-down-right" class="size-3.5" /> goes down
                        </span>
                    </li>
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm font-medium">
                        <span>Your spending total</span>
                        <span class="text-muted-foreground">unchanged</span>
                    </li>
                </ul>
            </x-ui.card-content>
        </x-ui.card>
    </div>
</div>
@endsection
