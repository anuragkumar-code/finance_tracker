@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', $month->format('F Y'))
@section('subheading', 'Your household snapshot for this month')

@section('actions')
    <form method="GET" class="flex items-center gap-2">
        <input type="month" name="month" value="{{ $month->format('Y-m') }}"
               class="h-9 rounded-md border border-input bg-card px-3 text-sm
                      focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
        <x-ui.button type="submit" variant="outline" size="md">Go</x-ui.button>
    </form>
    <x-ui.button :href="route('quick-entry')" icon="plus">Quick Entry</x-ui.button>
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

{{-- Four figures that answer "how are we doing" in a couple of seconds. --}}
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    <x-ui.stat label="Received" icon="trending-up" tone="income"
        :value="\App\Support\Money::inr($income)"
        :hint="bccomp($income, '0', 2) === 1 ? 'Income this month' : 'No income recorded yet'" />

    <x-ui.stat label="Spent" icon="trending-down"
        :value="\App\Support\Money::inr($spending)"
        :hint="bccomp($debtRepaid, '0', 2) === 1
            ? 'Includes '.\App\Support\Money::inr($debtRepaid).' of loan EMIs'
            : 'Excludes transfers and card bills'" />

    <x-ui.stat label="On credit cards" icon="credit-card"
        :value="\App\Support\Money::inr($cardSpending)"
        hint="Purchases, not bill payments" />

    <x-ui.stat label="Realistically available" icon="wallet"
        :tone="$reality['is_negative'] ? 'expense' : 'default'"
        :value="\App\Support\Money::inr($reality['realistic'])"
        :hint="\App\Support\Money::inr($spendableCash).' less '.\App\Support\Money::inr($reality['committed']).' committed'" />
</div>

@if ($budgetAlerts->isNotEmpty() || $spendingAnomalies->isNotEmpty())
    {{-- Only what needs attention. The full budget table lives on its own page;
         a dashboard that nags about healthy categories gets ignored. --}}
    <x-ui.card class="mt-4">
        <x-ui.card-header title="Worth a look">
            <x-slot:action>
                <a href="{{ route('budgets.index') }}"
                   class="text-sm text-muted-foreground hover:text-foreground">Budgets</a>
            </x-slot:action>
        </x-ui.card-header>
        <x-ui.card-content class="divide-y divide-border !py-0">
            @foreach ($budgetAlerts as $alert)
                <div class="flex items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-sm font-medium">{{ $alert->category?->name ?? 'Uncategorised' }}</span>
                            <x-ui.badge :variant="$alert->status === 'over' ? 'destructive' : 'warning'">
                                {{ $alert->status === 'over' ? 'Over budget' : 'Spending fast' }}
                            </x-ui.badge>
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ $alert->used_percent }}% of budget used ·
                            {{ $alert->month_elapsed_percent }}% of the month gone
                        </p>
                    </div>
                    <p class="shrink-0 text-sm">
                        <x-finance.money :amount="$alert->spent" tone="strong" />
                        <span class="text-muted-foreground">/ <x-finance.money :amount="$alert->budget" tone="muted" /></span>
                    </p>
                </div>
            @endforeach

            @foreach ($spendingAnomalies as $a)
                <div class="flex items-center justify-between gap-3 py-3">
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
        </x-ui.card-content>
    </x-ui.card>
@endif

<div class="mt-4 grid gap-4 lg:grid-cols-12">

    <div class="space-y-4 lg:col-span-7">

        @if ($obligations->isNotEmpty())
            <x-ui.card>
                <x-ui.card-header title="Coming up" description="Committed over the next 30 days">
                    <x-slot:action>
                        <a href="{{ route('upcoming.index') }}"
                           class="text-sm text-muted-foreground hover:text-foreground">See all</a>
                    </x-slot:action>
                </x-ui.card-header>
                <x-ui.card-content flush>
                    <ul class="divide-y divide-border">
                        @foreach ($obligations as $item)
                            <li class="flex items-center gap-3 px-5 py-2.5">
                                <div class="w-12 shrink-0 text-xs font-medium text-muted-foreground tabular">
                                    {{ $item->due_date->format('d M') }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ $item->url }}"
                                       class="text-sm font-medium hover:underline">{{ $item->label }}</a>
                                    @if ($item->is_estimated)
                                        <x-ui.badge variant="outline" class="ml-1">Estimate</x-ui.badge>
                                    @endif
                                </div>
                                <x-finance.money :amount="$item->amount" class="text-sm" tone="strong" />
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card-content>
            </x-ui.card>
        @endif

        <x-ui.card>
            <x-ui.card-header title="Where it went">
                <x-slot:action>
                    <a href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'type' => 'expense']) }}"
                       class="text-sm text-muted-foreground hover:text-foreground">View all</a>
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
                    <div class="grid items-center gap-5 sm:grid-cols-[minmax(0,11rem)_1fr]">
                        <div class="relative mx-auto h-44 w-44">
                            <canvas id="categoryChart"></canvas>
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-[0.6875rem] text-muted-foreground">Total</span>
                                <span class="text-sm font-semibold tabular">
                                    {{ \App\Support\Money::compact($spending) }}
                                </span>
                            </div>
                        </div>
                        <ul class="space-y-1.5">
                            @foreach ($byCategory->take(7) as $i => $row)
                                @php($share = bccomp($spending, '0', 2) === 1 ? round($row->amount / $spending * 100) : 0)
                                <li>
                                    <a href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'type' => 'expense', 'category_id' => $row->category_id]) }}"
                                       class="group flex items-center gap-2.5 rounded-md px-1.5 py-1 hover:bg-muted">
                                        <span class="size-2 shrink-0 rounded-full" data-swatch="{{ $i }}"></span>
                                        <span class="min-w-0 flex-1 truncate text-sm group-hover:underline">{{ $row->label }}</span>
                                        <span class="text-xs text-muted-foreground tabular">{{ $share }}%</span>
                                        <x-finance.money :amount="$row->amount" class="w-20 text-right text-sm" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header title="Today" />
            <x-ui.card-content flush>
                @if ($today->isEmpty())
                    <x-ui.empty-state icon="inbox" title="Nothing recorded today"
                        description="Add what you have spent while it is still fresh.">
                        <x-ui.button :href="route('quick-entry')" variant="outline" size="sm" icon="plus">
                            Add a spend
                        </x-ui.button>
                    </x-ui.empty-state>
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($today as $t)
                            <li class="flex items-center gap-3 px-5 py-2.5">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('transactions.show', $t) }}"
                                       class="text-sm font-medium hover:underline">
                                        {{ $t->description ?: $t->merchant?->name ?: $t->type->label() }}
                                    </a>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ $t->category?->name ?: 'Uncategorised' }} · {{ $t->account->name }}
                                    </p>
                                </div>
                                <x-finance.money :amount="$t->amount" class="text-sm"
                                    :tone="$t->type->countsAsSpending() ? 'strong' : 'muted'" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    </div>

    <div class="space-y-4 lg:col-span-5">

        <x-ui.card>
            <x-ui.card-header title="Balances">
                <x-slot:action>
                    <a href="{{ route('accounts.index') }}"
                       class="text-sm text-muted-foreground hover:text-foreground">Manage</a>
                </x-slot:action>
            </x-ui.card-header>
            <x-ui.card-content flush>
                @if ($accounts->isEmpty())
                    <x-ui.empty-state icon="wallet" title="No accounts yet" />
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($accounts as $account)
                            <li class="flex items-center gap-3 px-5 py-2.5">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('accounts.show', $account) }}"
                                       class="text-sm hover:underline">{{ $account->name }}</a>
                                    <p class="text-xs text-muted-foreground">
                                        {{ $account->type->label() }}{{ $account->owner ? ' · '.$account->owner->name : '' }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <x-finance.money :amount="$account->cached_balance" class="text-sm"
                                        :tone="$account->isLiability() ? 'debt' : 'strong'" />
                                    @if ($account->isLiability())
                                        <p class="text-[0.6875rem] text-muted-foreground">owed</p>
                                    @endif
                                </div>
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

        @if (bccomp($cardsOwed, '0', 2) !== 0 || $cardDues->isNotEmpty())
            <x-ui.card>
                <x-ui.card-header title="Cards">
                    <x-slot:action>
                        <a href="{{ route('credit-cards.index') }}"
                           class="text-sm text-muted-foreground hover:text-foreground">View all</a>
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
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                                <a href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'planned_status' => $row->key, 'type' => 'expense']) }}"
                                   class="text-sm hover:underline">{{ $row->label }}</a>
                                <x-finance.money :amount="$row->amount" class="text-sm" />
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
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                                <a href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'payer_id' => $row->key, 'type' => 'expense']) }}"
                                   class="text-sm hover:underline">{{ $row->label }}</a>
                                <x-finance.money :amount="$row->amount" class="text-sm" />
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
@if ($byCategory->isNotEmpty())
<script>
(function () {
    const labels = @json($byCategory->take(7)->pluck('label'));
    const data = @json($byCategory->take(7)->pluck('amount')->map(fn ($a) => (float) $a));
    const colors = window.ftChartPalette.slice(0, labels.length);

    // The legend list beside the chart doubles as the drill-down, so the swatch
    // colours have to be filled from the same palette the chart uses.
    document.querySelectorAll('[data-swatch]').forEach((el) => {
        el.style.backgroundColor = colors[Number(el.dataset.swatch) % colors.length];
    });

    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 0, hoverOffset: 4 }] },
        options: {
            cutout: '72%',
            responsive: true,
            plugins: {
                tooltip: { callbacks: { label: (c) => c.label + ': ' + window.ftMoney(c.parsed) } },
            },
        },
    });
})();
</script>
@endif
@endpush
