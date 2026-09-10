@extends('layouts.app')

@section('title', $loan->name)
@section('heading', $loan->name)
@section('subheading', ($loan->lender ?: 'Loan')
    .($loan->owner ? ' · '.$loan->owner->name : '')
    .' · '.$loan->total_months.' months from '.$loan->start_date->format('M Y'))

@section('actions')
    <x-ui.button :href="route('loans.edit', $loan)" variant="outline" icon="pencil">Edit</x-ui.button>
@endsection

@section('content')

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    <x-ui.stat label="Monthly EMI" :value="\App\Support\Money::inr($loan->emi_amount)" />
    <x-ui.stat label="Paid so far" tone="income" :value="\App\Support\Money::compact($loan->paidAmount())"
        :hint="$loan->paidCount().' of '.$loan->total_months" />
    <x-ui.stat label="Still to pay" tone="debt" :value="\App\Support\Money::compact($loan->remainingAmount())"
        :hint="$loan->remainingCount().' EMIs left'" />
    <div class="rounded-xl border border-border bg-card px-5 py-4 shadow-xs">
        <p class="text-xs font-medium text-muted-foreground">Finishes</p>
        <p class="mt-1.5 text-2xl font-semibold tracking-tight">{{ $loan->end_date->format('M Y') }}</p>
        <div class="mt-1.5">
            <x-ui.badge :variant="$loan->status->value === 'active' ? 'default' : 'secondary'">
                {{ $loan->status->label() }}
            </x-ui.badge>
        </div>
    </div>
</div>

<x-ui.card class="mt-4">
    <x-ui.card-content>
        <div class="mb-1.5 flex items-baseline justify-between text-xs text-muted-foreground">
            <span>{{ $loan->progressPercent() }}% of the tenure done</span>
            <span>{{ \App\Support\Money::inr($loan->totalPayable()) }} total over {{ $loan->total_months }} months</span>
        </div>
        <x-ui.progress :value="$loan->progressPercent()" variant="success" label="Loan progress" />
        <p class="mt-2 text-xs text-muted-foreground">
            "Still to pay" is cash across the remaining EMIs, which includes future interest — it is
            not the principal outstanding your lender would quote for a foreclosure.
        </p>
    </x-ui.card-content>
</x-ui.card>

@if ($overdue->isNotEmpty())
    <div class="mt-4">
        <x-ui.alert variant="warning" :title="$overdue->count().' EMI(s) are past their due date and not yet recorded'">
            Confirm them below once the money has actually left your account.
        </x-ui.alert>
    </div>
@endif

<div class="mt-4 grid gap-4 lg:grid-cols-12">
    <div class="lg:col-span-7">
        <x-ui.card>
            <x-ui.card-header title="Coming up" />
            <x-ui.card-content flush>
                @if ($upcoming->isEmpty())
                    <x-ui.empty-state icon="check-circle" title="All instalments recorded"
                        description="Nothing left to confirm on this loan." />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left">
                                    <th scope="col" class="px-5 py-2.5 text-xs font-medium text-muted-foreground">#</th>
                                    <th scope="col" class="px-5 py-2.5 text-xs font-medium text-muted-foreground">Due</th>
                                    <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">Amount</th>
                                    <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">Record</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                            @foreach ($upcoming as $instalment)
                                <tr class="{{ $instalment->isOverdue() ? 'bg-warning/[0.07]' : '' }} transition-colors hover:bg-muted/60">
                                    <td class="px-5 py-3 text-muted-foreground tabular">{{ $instalment->period_number }}</td>
                                    <td class="px-5 py-3">
                                        {{ $instalment->due_date->format('d M Y') }}
                                        @if ($instalment->isOverdue())
                                            <x-ui.badge variant="destructive" class="ml-1">Overdue</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <x-finance.money :amount="$instalment->amount" />
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <x-ui.button variant="outline" size="sm"
                                            x-on:click="$dispatch('open-dialog', 'pay-{{ $instalment->id }}')">
                                            Mark paid
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    </div>

    <div class="lg:col-span-5">
        <x-ui.card>
            <x-ui.card-header title="Recorded" />
            <x-ui.card-content flush>
                @if ($recent->isEmpty())
                    <x-ui.empty-state icon="inbox" title="Nothing recorded yet" />
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($recent as $instalment)
                            <li class="flex items-center gap-3 px-5 py-2.5">
                                <span class="w-6 shrink-0 text-xs text-muted-foreground tabular">
                                    {{ $instalment->period_number }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm">{{ $instalment->due_date->format('M Y') }}</p>
                                    @unless ($instalment->transaction_id)
                                        <p class="text-xs text-muted-foreground">before tracking</p>
                                    @endunless
                                </div>
                                <x-finance.money :amount="$instalment->amount" class="text-sm" />
                                @if ($instalment->transaction_id)
                                    <button type="button"
                                            x-on:click="$dispatch('open-dialog', 'unpay-{{ $instalment->id }}')"
                                            class="shrink-0 rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                            aria-label="Undo EMI {{ $instalment->period_number }}">
                                        <x-ui.icon name="undo-2" class="size-3.5" />
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    </div>
</div>

@foreach ($upcoming as $instalment)
    <x-ui.dialog id="pay-{{ $instalment->id }}" title="Record EMI {{ $instalment->period_number }}">
        <form method="POST" action="{{ route('loans.instalments.pay', [$loan, $instalment]) }}">
            @csrf
            <div class="space-y-4 px-5 py-4">
                <p class="text-sm text-muted-foreground">
                    This records the payment leaving your account and counts it in this month's
                    spending.
                </p>
                <x-ui.select label="Paid from" name="account_id" required>
                    <option value="">Choose…</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected($loan->payment_account_id == $account->id)>
                            {{ $account->name }}
                        </option>
                    @endforeach
                </x-ui.select>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Paid on" name="payment_date" type="date"
                        :value="$instalment->due_date->toDateString()" required />
                    <x-ui.input label="Amount" name="amount" inputmode="decimal" prefix="₹"
                        :value="$instalment->amount" />
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-5 py-3">
                <x-ui.button type="button" variant="ghost"
                    x-on:click="$dispatch('close-dialog', 'pay-{{ $instalment->id }}')">Cancel</x-ui.button>
                <x-ui.button type="submit">Record EMI</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
@endforeach

@foreach ($recent->where('transaction_id', '!=', null) as $instalment)
    <x-ui.dialog id="unpay-{{ $instalment->id }}" title="Undo EMI {{ $instalment->period_number }}?">
        <form method="POST" action="{{ route('loans.instalments.unpay', [$loan, $instalment]) }}">
            @csrf
            <div class="space-y-4 px-5 py-4">
                <p class="text-sm text-muted-foreground">
                    The entry will be voided and your balance restored. The instalment goes back to
                    being due.
                </p>
                <x-ui.input label="Why?" name="void_reason" required placeholder="Recorded by mistake" />
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-5 py-3">
                <x-ui.button type="button" variant="ghost"
                    x-on:click="$dispatch('close-dialog', 'unpay-{{ $instalment->id }}')">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="destructive">Undo</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
@endforeach
@endsection
