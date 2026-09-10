@extends('layouts.app')

@section('title', 'Card report')
@section('heading', 'Cards')
@section('subheading', $month->format('F Y').' — what was charged, and what was paid')

@section('actions')
    <form method="GET" class="flex items-center gap-2">
        <input type="month" name="month" value="{{ $month->format('Y-m') }}"
               class="h-9 rounded-md border border-input bg-card px-3 text-sm
                      focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
        <x-ui.button type="submit" variant="outline">Go</x-ui.button>
    </form>
    <x-ui.button :href="route('reports.index')" variant="outline">Back</x-ui.button>
@endsection

@section('content')

@if ($rows->isEmpty())
    <x-ui.card>
        <x-ui.empty-state icon="credit-card" title="No cards recorded"
            description="Add a card to see purchases and bill payments side by side.">
            <x-ui.button :href="route('credit-cards.create')" icon="plus">Add a card</x-ui.button>
        </x-ui.empty-state>
    </x-ui.card>
@else

{{-- Spec 19F is explicit that purchase volume and bill payments must be shown
     separately, never merged into one "card spending" number. --}}
<x-ui.card>
    <x-ui.card-header title="Purchases vs bill payments" />
    <x-ui.card-content flush>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left">
                        <th scope="col" class="px-5 py-2.5 text-xs font-medium text-muted-foreground">Card</th>
                        <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">Charged</th>
                        <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">Bills paid</th>
                        <th scope="col" class="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">Owed now</th>
                        <th scope="col" class="hidden px-5 py-2.5 text-right text-xs font-medium text-muted-foreground sm:table-cell">Available</th>
                        <th scope="col" class="hidden px-5 py-2.5 text-xs font-medium text-muted-foreground lg:table-cell" style="width:12%">Used</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                @foreach ($rows as $row)
                    <tr class="transition-colors hover:bg-muted/60">
                        <td class="px-5 py-3">
                            <a href="{{ route('credit-cards.show', $row->card) }}"
                               class="font-medium hover:underline">{{ $row->card->card_name }}</a>
                            <p class="text-xs text-muted-foreground">
                                @if ($row->card->account->owner){{ $row->card->account->owner->name }} · @endif
                                bills {{ $row->card->statement_day }}, due {{ $row->card->payment_due_day }}
                            </p>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'type' => 'expense', 'account_id' => $row->card->account_id]) }}"
                               class="hover:underline">
                                <x-finance.money :amount="$row->spending" tone="strong" />
                            </a>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <x-finance.money :amount="$row->payments" tone="muted" />
                        </td>
                        <td class="px-5 py-3 text-right">
                            <x-finance.money :amount="$row->outstanding" tone="debt" />
                        </td>
                        <td class="hidden px-5 py-3 text-right sm:table-cell">
                            <x-finance.money :amount="$row->available" />
                        </td>
                        <td class="hidden px-5 py-3 lg:table-cell">
                            <x-ui.progress :value="$row->utilisation"
                                :variant="$row->utilisation >= 70 ? 'destructive' : ($row->utilisation >= 40 ? 'warning' : 'success')"
                                :label="$row->card->card_name.' credit used'" />
                            <p class="mt-1 text-xs text-muted-foreground tabular">{{ $row->utilisation }}%</p>
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-border font-medium">
                        <td class="px-5 py-3">Total</td>
                        <td class="px-5 py-3 text-right">
                            <x-finance.money :amount="$rows->reduce(fn ($c, $r) => bcadd($c, $r->spending, 2), '0.00')" tone="strong" />
                        </td>
                        <td class="px-5 py-3 text-right">
                            <x-finance.money :amount="$rows->reduce(fn ($c, $r) => bcadd($c, $r->payments, 2), '0.00')" tone="muted" />
                        </td>
                        <td class="px-5 py-3 text-right">
                            <x-finance.money :amount="$rows->reduce(fn ($c, $r) => bcadd($c, $r->outstanding, 2), '0.00')" tone="debt" />
                        </td>
                        <td colspan="2" class="hidden sm:table-cell"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-ui.card-content>
</x-ui.card>

<x-ui.card class="mt-4">
    <x-ui.card-header title="Card spending trend" description="Charged to cards over recent months" />
    <x-ui.card-content>
        <div class="h-52"><canvas id="cardTrend"></canvas></div>
    </x-ui.card-content>
</x-ui.card>

<x-ui.card class="mt-4">
    <x-ui.card-content class="text-sm text-muted-foreground">
        <span class="font-medium text-foreground">Why these two columns are separate.</span>
        "Charged" is what you bought on the card this month — that is your spending. "Bills paid" is
        money moved from your bank to clear what you already owed; it is not spending, and adding the
        two together would count the same purchases twice.
    </x-ui.card-content>
</x-ui.card>

@endif
@endsection

@push('scripts')
@if ($rows->isNotEmpty())
<script>
new Chart(document.getElementById('cardTrend'), {
    type: 'bar',
    data: {
        labels: @json($series->pluck('label')),
        datasets: [{
            data: @json($series->pluck('card_spending')->map(fn ($v) => (float) $v)),
            backgroundColor: 'oklch(0.68 0.15 70 / 0.85)',
            borderRadius: 4,
            maxBarThickness: 32,
        }],
    },
    options: {
        scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: {
                beginAtZero: true,
                border: { display: false },
                grid: { color: 'oklch(0.923 0.005 248)' },
                ticks: { callback: (v) => window.ftMoney(v) },
            },
        },
        plugins: { tooltip: { callbacks: { label: (c) => window.ftMoney(c.parsed.y) } } },
    },
});
</script>
@endif
@endpush
