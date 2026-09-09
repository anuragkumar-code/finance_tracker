@extends('layouts.app')

@php($isTransfer = $type === \App\Enums\TransactionType::Transfer)

@section('title', $isTransfer ? 'Move money' : 'Record income')
@section('heading', $isTransfer ? 'Move money' : 'Record income')
@section('subheading', $isTransfer
    ? 'Between accounts you already own — this is not spending'
    : 'Money coming in')

@section('content')
<div class="max-w-2xl">

    {{-- Tabs rather than three separate entry points: the three things you can
         record are one decision, not three pages to hunt for. --}}
    <div class="mb-4 inline-flex rounded-lg border border-border bg-muted p-0.5">
        @foreach ([
            ['label' => 'Income', 'url' => route('transactions.create', ['type' => 'income']), 'active' => ! $isTransfer],
            ['label' => 'Transfer', 'url' => route('transactions.create', ['type' => 'transfer']), 'active' => $isTransfer],
            ['label' => 'Spending', 'url' => route('quick-entry'), 'active' => false],
        ] as $tab)
            <a href="{{ $tab['url'] }}"
               @if ($tab['active']) aria-current="page" @endif
               class="rounded-md px-3 py-1.5 text-sm transition-colors
                      {{ $tab['active'] ? 'bg-card text-foreground shadow-xs font-medium' : 'text-muted-foreground hover:text-foreground' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>

    @if ($isTransfer)
        <form method="POST" action="{{ route('transactions.transfer.store') }}">
            @csrf
            <x-ui.card>
                <x-ui.card-content class="space-y-4">
                    <x-ui.alert variant="info">
                        Moving money between your own accounts does not make the household poorer,
                        so transfers never appear in spending totals.
                    </x-ui.alert>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select label="From" name="from_account_id" required>
                            <option value="">Choose…</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected(old('from_account_id') == $account->id)>
                                    {{ $account->name }}@if ($account->owner) · {{ $account->owner->name }}@endif
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select label="To" name="to_account_id" required>
                            <option value="">Choose…</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected(old('to_account_id') == $account->id)>
                                    {{ $account->name }}@if ($account->owner) · {{ $account->owner->name }}@endif
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.input label="Amount" name="amount" inputmode="decimal" prefix="₹"
                            :value="old('amount')" required />

                        <x-ui.input label="Date" name="transaction_date" type="date"
                            :value="old('transaction_date', now()->toDateString())" required />
                    </div>

                    <x-ui.input label="Note" name="description" :value="old('description')"
                        placeholder="Moved savings across" hint="Optional" />
                </x-ui.card-content>

                <div class="flex gap-2 border-t border-border px-5 py-3.5">
                    <x-ui.button type="submit">Record transfer</x-ui.button>
                    <x-ui.button :href="route('transactions.index')" variant="ghost">Cancel</x-ui.button>
                </div>
            </x-ui.card>
        </form>
    @else
        <form method="POST" action="{{ route('transactions.income.store') }}">
            @csrf
            <x-ui.card>
                <x-ui.card-content class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input label="Amount" name="amount" inputmode="decimal" prefix="₹"
                            :value="old('amount')" required autofocus />

                        <x-ui.select label="Into" name="account_id" required>
                            <option value="">Choose…</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected(old('account_id') == $account->id)>
                                    {{ $account->name }}@if ($account->owner) · {{ $account->owner->name }}@endif
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select label="Kind" name="category_id">
                            <option value="">—</option>
                            @foreach ($incomeCategories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.input label="Date" name="transaction_date" type="date"
                            :value="old('transaction_date', now()->toDateString())" required />

                        {{-- Whose income it is belongs on payer: that is the person
                             the money came through. --}}
                        <x-ui.select label="Earned by" name="payer_id">
                            <option value="">—</option>
                            @foreach ($payers as $person)
                                <option value="{{ $person->id }}" @selected(old('payer_id') == $person->id)>
                                    {{ $person->name }}
                                </option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select label="For" name="beneficiary_id" hint="Optional">
                            <option value="">—</option>
                            @foreach ($beneficiaries as $person)
                                <option value="{{ $person->id }}" @selected(old('beneficiary_id') == $person->id)>
                                    {{ $person->name }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <x-ui.input label="Note" name="description" :value="old('description')"
                        placeholder="September salary" hint="Optional" />
                </x-ui.card-content>

                <div class="flex gap-2 border-t border-border px-5 py-3.5">
                    <x-ui.button type="submit">Record income</x-ui.button>
                    <x-ui.button :href="route('transactions.index')" variant="ghost">Cancel</x-ui.button>
                </div>
            </x-ui.card>
        </form>
    @endif
</div>
@endsection
