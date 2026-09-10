@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', $month->format('F Y'))
@section('subheading', 'Your household snapshot for this month')

@section('actions')
    <form method="GET" class="flex items-center gap-2">
        <input type="month" name="month" value="{{ $month->format('Y-m') }}"
               class="h-9 rounded-md border border-input bg-card px-3 text-sm shadow-xs
                      focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
        <x-ui.button type="submit" variant="outline" size="md">Go</x-ui.button>
    </form>
    <x-ui.button :href="route('reports.index', ['month' => $month->format('Y-m')])" variant="outline" icon="chart-line">
        Reports
    </x-ui.button>
@endsection

@section('content')

@if ($accounts->isEmpty())
    <x-ui.card class="mb-6">
        <x-ui.empty-state icon="wallet" title="Start with your accounts"
            description="Add your bank accounts, cash and cards with what each holds today. That starting position is recorded separately from spending, so this month's numbers stay clean.">
            <x-ui.button :href="route('accounts.create')" icon="plus">Add an account</x-ui.button>
        </x-ui.empty-state>
    </x-ui.card>
@endif

@php
    // Sparklines share the six-month series behind the cash-flow chart, so a
    // card and the chart under it can never disagree about a month.
    $incomeSeries = $series->pluck('income')->all();
    $spendSeries = $series->pluck('spending')->all();
    $cardSeries = $series->pluck('card_spending')->all();
@endphp

{{-- Four figures that answer "how are we doing" in a couple of seconds. --}}
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    <x-ui.stat label="Received" icon="trending-up" tone="income"
        :value="\App\Support\Money::inr($income)"
        :delta="$incomeDelta" :delta-label="'vs '.$previousLabel" delta-good="up"
        :series="$incomeSeries"
        :hint="bccomp($income, '0', 2) === 1 ? null : 'No income recorded yet'" />

    <x-ui.stat label="Spent" icon="trending-down"
        :value="\App\Support\Money::inr($spending)"
        :delta="$spendingDelta" :delta-label="'vs '.$previousLabel" delta-good="down"
        :series="$spendSeries"
        :hint="bccomp($debtRepaid, '0', 2) === 1
            ? 'Includes '.\App\Support\Money::inr($debtRepaid).' of loan EMIs'
            : 'Excludes transfers and card bills'" />

    <x-ui.stat label="On credit cards" icon="credit-card"
        :value="\App\Support\Money::inr($cardSpending)"
        :delta="$cardDelta" :delta-label="'vs '.$previousLabel" delta-good="down"
        :series="$cardSeries"
        hint="Purchases, not bill payments" />

    <x-ui.stat label="Realistically available" icon="wallet"
        :tone="$reality['is_negative'] ? 'expense' : 'default'"
        :value="\App\Support\Money::inr($reality['realistic'])"
        :href="route('upcoming.index')"
        :hint="\App\Support\Money::inr($spendableCash).' less '.\App\Support\Money::inr($reality['committed']).' committed'" />
</div>

@if ($budgetAlerts->isNotEmpty() || $spendingAnomalies->isNotEmpty())
    {{-- Only what needs attention. The full budget table lives on its own page;
         a dashboard that nags about healthy categories gets ignored. --}}
    <x-ui.card class="mt-4">
        <x-ui.card-header title="Worth a look" description="Categories running ahead of where they normally are">
            <x-slot:action>
                <a href="{{ route('budgets.index') }}"
                   class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    Budgets <x-ui.icon name="chevron-right" class="size-3.5" />
                </a>
            </x-slot:action>
        </x-ui.card-header>
        <x-ui.card-content flush>
            <div class="divide-y divide-border">
                @foreach ($budgetAlerts as $alert)
                    @php($pct = min(100, (int) $alert->used_percent))
                    <div class="flex items-center gap-4 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-sm font-medium">{{ $alert->category?->name ?? 'Uncategorised' }}</span>
                                <x-ui.badge :variant="$alert->status === 'over' ? 'destructive' : 'warning'">
                                    {{ $alert->status === 'over' ? 'Over budget' : 'Spending fast' }}
                                </x-ui.badge>
                            </div>
                            {{-- Two bars on one track: how much of the budget has
                                 gone, against how much of the month has. That
                                 comparison is the whole point of the alert. --}}
                            <div class="relative mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full {{ $alert->status === 'over' ? 'bg-destructive' : 'bg-warning' }}"
                                     style="width: {{ $pct }}%"></div>
                                <div class="absolute inset-y-0 w-px bg-foreground/40"
                                     style="left: {{ min(100, (int) $alert->month_elapsed_percent) }}%"
                                     title="{{ $alert->month_elapsed_percent }}% of the month gone"></div>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ $alert->used_percent }}% of budget used ·
                                {{ $alert->month_elapsed_percent }}% of the month gone
                            </p>
                        </div>
                        <p class="shrink-0 text-right text-sm">
                            <x-finance.money :amount="$alert->spent" tone="strong" />
                            <span class="block text-xs text-muted-foreground">
                                of <x-finance.money :amount="$alert->budget" tone="muted" />
                            </span>
                        </p>
                    </div>
                @endforeach

                @foreach ($spendingAnomalies as $a)
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-sm font-medium">{{ $a->category?->name ?? 'Uncategorised' }}</span>
                                <x-ui.badge variant="secondary">{{ $a->ratio }}× usual</x-ui.badge>
                            </div>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                normally around <x-finance.money :amount="$a->usual" tone="muted" /> a month
                            </p>
                        </div>
                        <x-finance.money :amount="$a->spent" tone="strong" class="shrink-0 text-sm" />
                    </div>
                @endforeach
            </div>
        </x-ui.card-content>
    </x-ui.card>
@endif

<div class="mt-4 grid gap-4 lg:grid-cols-12">

    {{-- The trend leads, because one month in isolation cannot tell you whether
         a figure is a problem. --}}
    <x-ui.card class="lg:col-span-8">
        <x-ui.card-header title="Cash flow" description="Six months to {{ $month->format('M Y') }}">
            <x-slot:action>
                <div class="flex items-center gap-3 text-xs">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-income"></span>
                        <span class="text-muted-foreground">Received</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-expense"></span>
                        <span class="text-muted-foreground">Spent</span>
                    </span>
                </div>
            </x-slot:action>
        </x-ui.card-header>
        <x-ui.card-content>
            <div class="h-56 sm:h-64"><canvas id="cashflowChart"></canvas></div>
        </x-ui.card-content>
    </x-ui.card>

    <x-ui.card class="lg:col-span-4">
        <x-ui.card-header title="Coming up" description="Committed over the next 30 days">
            <x-slot:action>
                <a href="{{ route('upcoming.index') }}"
                   class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    All <x-ui.icon name="chevron-right" class="size-3.5" />
                </a>
            </x-slot:action>
        </x-ui.card-header>
        <x-ui.card-content flush>
            @if ($obligations->isEmpty())
                <x-ui.empty-state icon="calendar-clock" title="Nothing committed"
                    description="No bills, EMIs or recurring charges fall in the next 30 days." />
            @else
                <ul class="divide-y divide-border">
                    @foreach ($obligations as $item)
                        <li class="flex items-center gap-3 px-5 py-2.5">
                            <div class="flex size-9 shrink-0 flex-col items-center justify-center rounded-lg
                                        border border-border bg-muted leading-none">
                                <span class="text-[0.625rem] uppercase text-muted-foreground">
                                    {{ $item->due_date->format('M') }}
                                </span>
                                <span class="text-xs font-semibold tabular">{{ $item->due_date->format('d') }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <a href="{{ $item->url }}"
                                   class="block truncate text-sm font-medium hover:underline">{{ $item->label }}</a>
                                @if ($item->is_estimated)
                                    <span class="text-xs text-muted-foreground">estimated</span>
                                @endif
                            </div>
                            <x-finance.money :amount="$item->amount" class="text-sm" tone="strong" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card-content>
    </x-ui.card>

    <x-ui.card class="lg:col-span-7">
        <x-ui.card-header title="Where it went" :description="$month->format('F')">
            <x-slot:action>
                <a href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'type' => 'expense']) }}"
                   class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    All <x-ui.icon name="chevron-right" class="size-3.5" />
                </a>
            </x-slot:action>
        </x-ui.card-header>
        <x-ui.card-content>
            @if ($byCategory->isEmpty())
                <x-ui.empty-state icon="chart-pie" title="Nothing recorded yet"
                    :description="'No spending logged for '.$month->format('F').'.'">
                    <x-ui.button :href="route('quick-entry')" variant="outline" size="sm" icon="plus">
                        Record a spend
                    </x-ui.button>
                </x-ui.empty-state>
            @else
                <div class="grid items-center gap-6 sm:grid-cols-[minmax(0,10.5rem)_1fr]">
                    <div class="relative mx-auto h-40 w-40">
                        <canvas id="categoryChart"></canvas>
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-[0.6875rem] text-muted-foreground">Total</span>
                            <span class="text-base font-semibold tabular">
                                {{ \App\Support\Money::compact($spending) }}
                            </span>
                        </div>
                    </div>
                    <ul class="space-y-0.5">
                        @foreach ($byCategory->take(7) as $i => $row)
                            @php($share = bccomp($spending, '0', 2) === 1 ? round($row->amount / $spending * 100) : 0)
                            <li>
                                <a href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'type' => 'expense', 'category_id' => $row->category_id]) }}"
                                   class="group flex items-center gap-2.5 rounded-md px-2 py-1.5 transition-colors hover:bg-muted">
                                    <span class="size-2 shrink-0 rounded-full" data-swatch="{{ $i }}"></span>
                                    <span class="min-w-0 flex-1 truncate text-[0.8125rem] group-hover:text-foreground">{{ $row->label }}</span>
                                    <span class="w-8 text-right text-xs text-muted-foreground tabular">{{ $share }}%</span>
                                    <x-finance.money :amount="$row->amount" class="w-20 text-right text-[0.8125rem]" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-ui.card-content>
    </x-ui.card>

    <x-ui.card class="lg:col-span-5">
        <x-ui.card-header title="Balances">
            <x-slot:action>
                <a href="{{ route('accounts.index') }}"
                   class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    Manage <x-ui.icon name="chevron-right" class="size-3.5" />
                </a>
            </x-slot:action>
        </x-ui.card-header>
        <x-ui.card-content flush>
            @if ($accounts->isEmpty())
                <x-ui.empty-state icon="wallet" title="No accounts yet" />
            @else
                <ul class="divide-y divide-border">
                    @foreach ($accounts as $account)
                        <li>
                            <a href="{{ route('accounts.show', $account) }}"
                               class="flex items-center gap-3 px-5 py-2.5 transition-colors hover:bg-muted">
                                <div class="min-w-0 flex-1">
                                    <span class="block truncate text-sm">{{ $account->name }}</span>
                                    <span class="block text-xs text-muted-foreground">
                                        {{ $account->type->label() }}{{ $account->owner ? ' · '.$account->owner->name : '' }}
                                    </span>
                                </div>
                                <div class="shrink-0 text-right">
                                    <x-finance.money :amount="$account->cached_balance" class="text-sm"
                                        :tone="$account->isLiability() ? 'debt' : 'strong'" />
                                    @if ($account->isLiability())
                                        <span class="block text-[0.6875rem] text-muted-foreground">owed</span>
                                    @endif
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card-content>
        <x-ui.card-footer class="flex items-center justify-between">
            <span>Net worth</span>
            <x-finance.money :amount="$netWorth['net_worth']" tone="strong" class="text-sm" />
        </x-ui.card-footer>
    </x-ui.card>

    <x-ui.card class="lg:col-span-7">
        <x-ui.card-header title="Today" :description="now()->format('D, d M')">
            <x-slot:action>
                <x-ui.button :href="route('quick-entry')" variant="outline" size="sm" icon="plus">Add</x-ui.button>
            </x-slot:action>
        </x-ui.card-header>
        <x-ui.card-content flush>
            @if ($today->isEmpty())
                <x-ui.empty-state icon="inbox" title="Nothing recorded today"
                    description="Add what you have spent while it is still fresh." />
            @else
                <ul class="divide-y divide-border">
                    @foreach ($today as $t)
                        <li>
                            <a href="{{ route('transactions.show', $t) }}"
                               class="flex items-center gap-3 px-5 py-2.5 transition-colors hover:bg-muted">
                                <div class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">
                                        {{ $t->description ?: $t->merchant?->name ?: $t->type->label() }}
                                    </span>
                                    <span class="block truncate text-xs text-muted-foreground">
                                        {{ $t->category?->name ?: 'Uncategorised' }} · {{ $t->account->name }}
                                    </span>
                                </div>
                                <x-finance.money :amount="$t->amount" class="shrink-0 text-sm"
                                    :tone="$t->type->countsAsSpending() ? 'strong' : 'muted'" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card-content>
    </x-ui.card>

    <div class="space-y-4 lg:col-span-5">
        @if (bccomp($cardsOwed, '0', 2) !== 0 || $cardDues->isNotEmpty())
            <x-ui.card>
                <x-ui.card-header title="Cards">
                    <x-slot:action>
                        <a href="{{ route('credit-cards.index') }}"
                           class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                            All <x-ui.icon name="chevron-right" class="size-3.5" />
                        </a>
                    </x-slot:action>
                </x-ui.card-header>
                <x-ui.card-content>
                    <div class="flex items-baseline justify-between">
                        <span class="text-sm text-muted-foreground">Owed across cards</span>
                        <x-finance.money :amount="$cardsOwed" tone="debt" class="text-lg font-semibold" />
                    </div>

                    @if ($cardDues->isEmpty())
                        <p class="mt-2 text-xs text-muted-foreground">No bills due in the next 30 days.</p>
                    @else
                        <ul class="mt-3 space-y-2 border-t border-border pt-3">
                            @foreach ($cardDues as $due)
                                <li class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('credit-cards.statements.show', [$due->creditCard, $due]) }}"
                                           class="text-sm hover:underline">{{ $due->creditCard->card_name }}</a>
                                        <p class="text-xs text-muted-foreground">
                                            due {{ $due->due_date->format('d M') }}
                                        </p>
                                    </div>
                                    <x-finance.money :amount="$due->balanceRemaining()" class="text-sm" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card-content>
            </x-ui.card>
        @endif

        <x-ui.card>
            <x-ui.card-header title="Planned vs unplanned" />
            <x-ui.card-content flush>
                @if ($byPlanned->isEmpty())
                    <x-ui.empty-state icon="info" title="Not tagged yet"
                        description="Tag spending as planned or unplanned to see the split." />
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($byPlanned as $row)
                            @php($share = bccomp($spending, '0', 2) === 1 ? round($row->amount / $spending * 100) : 0)
                            <li>
                                <a href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'planned_status' => $row->key, 'type' => 'expense']) }}"
                                   class="flex items-center gap-3 px-5 py-2.5 transition-colors hover:bg-muted">
                                    <span class="min-w-0 flex-1 truncate text-sm">{{ $row->label }}</span>
                                    <span class="text-xs text-muted-foreground tabular">{{ $share }}%</span>
                                    <x-finance.money :amount="$row->amount" class="w-20 text-right text-sm" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header title="Who paid" />
            <x-ui.card-content flush>
                @if ($byPayer->isEmpty())
                    <x-ui.empty-state icon="users" title="Not recorded yet"
                        description="Tag who paid to compare across the household." />
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($byPayer as $row)
                            @php($share = bccomp($spending, '0', 2) === 1 ? round($row->amount / $spending * 100) : 0)
                            <li>
                                <a href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'payer_id' => $row->key, 'type' => 'expense']) }}"
                                   class="flex items-center gap-3 px-5 py-2.5 transition-colors hover:bg-muted">
                                    <span class="min-w-0 flex-1 truncate text-sm">{{ $row->label }}</span>
                                    <span class="text-xs text-muted-foreground tabular">{{ $share }}%</span>
                                    <x-finance.money :amount="$row->amount" class="w-20 text-right text-sm" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    ftChart.area(document.getElementById('cashflowChart'), {
        labels: @json($series->pluck('short')),
        datasets: [
            { label: 'Received', data: @json($series->pluck('income')->map(fn ($a) => (float) $a)), color: ftChart.colors.income },
            { label: 'Spent', data: @json($series->pluck('spending')->map(fn ($a) => (float) $a)), color: ftChart.colors.expense },
        ],
    });

    @if ($byCategory->isNotEmpty())
        var labels = @json($byCategory->take(7)->pluck('label'));
        var data = @json($byCategory->take(7)->pluck('amount')->map(fn ($a) => (float) $a));
        var colors = ftChart.palette.slice(0, labels.length);

        // The legend list beside the chart doubles as the drill-down, so the
        // swatch colours have to be filled from the same palette the chart uses.
        document.querySelectorAll('[data-swatch]').forEach(function (el) {
            el.style.backgroundColor = colors[Number(el.dataset.swatch) % colors.length];
        });

        ftChart.donut(document.getElementById('categoryChart'), { labels: labels, data: data });
    @endif
});
</script>
@endpush
