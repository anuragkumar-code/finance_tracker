@extends('layouts.app')

@section('title', 'Add statement')
@section('heading', 'Add a statement')
@section('subheading', $card->card_name.' — group the purchases your bank has billed')

@section('content')
<div class="grid gap-4 lg:grid-cols-12">
    <div class="lg:col-span-7">
        <form method="POST" action="{{ route('credit-cards.statements.store', $card) }}">
            @csrf
            <x-ui.card>
                <x-ui.card-content class="space-y-4">
                    <x-ui.alert variant="info">
                        A statement does not add to your spending — those purchases were already
                        recorded when you made them. This groups them and records what the bank says
                        you owe.
                    </x-ui.alert>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input label="Period from" name="period_start" type="date" id="period_start"
                            :value="old('period_start', $periodStart->toDateString())" required />
                        <x-ui.input label="Period to" name="period_end" type="date" id="period_end"
                            :value="old('period_end', $periodEnd->toDateString())" required />
                        <x-ui.input label="Statement date" name="statement_date" type="date"
                            :value="old('statement_date', $periodEnd->toDateString())" required />
                        <x-ui.input label="Payment due by" name="due_date" type="date"
                            :value="old('due_date', $dueDate->toDateString())" required />

                        <x-ui.input label="Statement amount" name="statement_amount" inputmode="decimal" prefix="₹"
                            :value="old('statement_amount', $preview)" required
                            hint="Pre-filled from your recorded purchases. Replace it with the figure on your real statement if they differ — the gap is worth knowing about." />

                        <x-ui.input label="Minimum due" name="minimum_due" inputmode="decimal" prefix="₹"
                            :value="old('minimum_due')" hint="Optional" />
                    </div>

                    <x-ui.textarea label="Notes" name="notes" rows="2" hint="Optional">{{ old('notes') }}</x-ui.textarea>
                </x-ui.card-content>

                <div class="flex gap-2 border-t border-border px-5 py-3.5">
                    <x-ui.button type="submit">Record statement</x-ui.button>
                    <x-ui.button :href="route('credit-cards.show', $card)" variant="ghost">Cancel</x-ui.button>
                </div>
            </x-ui.card>
        </form>
    </div>

    <div class="lg:col-span-5">
        <x-ui.card>
            <x-ui.card-header title="What will be grouped">
                <x-slot:action>
                    <span class="text-sm font-semibold tabular">{{ \App\Support\Money::inr($preview) }}</span>
                </x-slot:action>
            </x-ui.card-header>
            <x-ui.card-content flush>
                @if ($transactions->isEmpty())
                    <x-ui.empty-state icon="inbox" title="Nothing in this period"
                        description="No unbilled purchases fall inside these dates." />
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($transactions as $t)
                            <li class="flex items-center gap-3 px-5 py-2">
                                <span class="w-11 shrink-0 text-xs text-muted-foreground tabular">
                                    {{ $t->transaction_date->format('d M') }}
                                </span>
                                <span class="min-w-0 flex-1 truncate text-sm">
                                    {{ $t->description ?: $t->merchant?->name ?: 'Purchase' }}
                                </span>
                                <x-finance.money :amount="$t->amount" class="text-sm"
                                    :tone="$t->balance_effect === \App\Enums\BalanceEffect::Decrease ? 'income' : null" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card-content>
            <x-ui.card-footer>
                Change the dates and the list reloads to match what will actually be grouped.
            </x-ui.card-footer>
        </x-ui.card>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Changing the period re-queries which purchases fall inside it, so the preview
// always matches what will actually be grouped.
['period_start', 'period_end'].forEach((id) => {
    document.getElementById(id).addEventListener('change', () => {
        const start = document.getElementById('period_start').value;
        const end = document.getElementById('period_end').value;
        if (start && end) {
            window.location = '{{ route('credit-cards.statements.create', $card) }}'
                + '?period_start=' + start + '&period_end=' + end;
        }
    });
});
</script>
@endpush
