@extends('layouts.app')

@section('title', 'Loans')
@section('heading', 'Loans')
@section('subheading', 'EMIs, what is left, and how far along you are')

@section('actions')
    <x-ui.button :href="route('loans.create')" icon="plus">Add loan</x-ui.button>
@endsection

@section('content')

@if ($loans->isEmpty())
    <x-ui.card>
        <x-ui.empty-state icon="landmark" title="No loans yet"
            description="Add a loan with its EMI, how many months it runs and when it started. That is all — the schedule, what you have paid and what is left are worked out from there.">
            <x-ui.button :href="route('loans.create')" icon="plus">Add your first loan</x-ui.button>
        </x-ui.empty-state>
    </x-ui.card>
@else

<div class="grid gap-3 sm:grid-cols-2 lg:gap-4">
    <x-ui.stat label="Still to pay" icon="landmark" tone="debt"
        :value="\App\Support\Money::inr($totalRemaining)"
        hint="Cash across remaining EMIs — includes future interest, not principal only" />

    <x-ui.stat label="Every month" icon="calendar-clock"
        :value="\App\Support\Money::inr($monthlyEmi)"
        hint="Combined EMIs across running loans" />
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    @foreach ($loans as $loan)
        @php($progress = $loan->progressPercent())
        <x-ui.card>
            <x-ui.card-content class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <a href="{{ route('loans.show', $loan) }}"
                               class="text-base font-semibold hover:underline">{{ $loan->name }}</a>
                            @if ($loan->owner)
                                <x-ui.badge variant="outline">{{ $loan->owner->name }}</x-ui.badge>
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ $loan->lender ?: 'Loan' }} · due on the {{ $loan->due_day }}
                        </p>
                    </div>
                    <x-ui.badge :variant="$loan->status->value === 'active' ? 'default' : 'secondary'">
                        {{ $loan->status->label() }}
                    </x-ui.badge>
                </div>

                <div class="grid grid-cols-3 gap-3 border-y border-border py-3">
                    <div>
                        <p class="text-xs text-muted-foreground">EMI</p>
                        <x-finance.money :amount="$loan->emi_amount" tone="strong" class="text-sm" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Paid</p>
                        <x-finance.money :amount="$loan->paidAmount()" compact tone="income" class="text-sm" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Left</p>
                        <x-finance.money :amount="$loan->remainingAmount()" compact tone="debt" class="text-sm" />
                    </div>
                </div>

                <div>
                    <div class="mb-1.5 flex items-baseline justify-between text-xs text-muted-foreground">
                        <span>{{ $loan->paidCount() }} of {{ $loan->total_months }} EMIs</span>
                        <span>ends {{ $loan->end_date->format('M Y') }}</span>
                    </div>
                    <x-ui.progress :value="$progress" variant="success"
                        :label="$loan->name.' progress'" />
                </div>

                @if ($loan->overduePayments()->exists())
                    <p class="text-xs text-destructive">
                        {{ $loan->overduePayments()->count() }} EMI(s) past due and not yet recorded.
                    </p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endforeach
</div>
@endif
@endsection
