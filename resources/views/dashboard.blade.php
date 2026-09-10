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
                    @php
                        $pct = min(100, (int) $alert->used_percent);
                        $over = $alert->status === 'over';

                        // Worked out here rather than inline in the attribute.
                        // Blade's component tag compiler parses attributes with a
                        // regex, and a ternary carrying its own colons inside a
                        // :bound attribute can make it give up on the tag — which
                        // it does silently, emitting the literal <x-ui.badge> and
                        // leaving the surrounding component tree unbalanced.
                        $alertVariant = $over ? 'destructive' : 'warning';
                        $alertLabel = $over ? 'Over budget' : 'Spending fast';
                        $alertBar = $over ? 'bg-destructive' : 'bg-warning';
                    @endphp
                    <div class="flex items-center gap-4 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-sm font-medium">{{ $alert->category?->name ?? 'Uncategorised' }}</span>
                                <x-ui.badge :variant="$alertVariant">{{ $alertLabel }}</x-ui.badge>
                            </div>
                            {{-- Two bars on one track: how much of the budget has
                                 gone, against how much of the month has. That
                                 comparison is the whole point of the alert. --}}
                            <div class="relative mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full {{ $alertBar }}"
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

@php
    // Grouped for display. Sorted by size within each group, because a balances
    // list ordered alphabetically buries the one card carrying ₹20,949 in the
    // middle of nine that carry nothing.
    $bankAccounts = $accounts->reject->isLiability()
        ->sortByDesc(fn ($a) => (float) $a->cached_balance)->values();

    $cardAccounts = $accounts->filter->isLiability()
        ->sortByDesc(fn ($a) => (float) $a->cached_balance)->values();

    // Cards at zero are the normal, healthy state — they are worth confirming
    // exist, but not worth a row each above the ones you actually owe on.
    $cardsWithBalance = $cardAccounts->filter(fn ($a) => bccomp($a->cached_balance, '0', 2) !== 0)->values();
    $cardsAtZero = $cardAccounts->filter(fn ($a) => bccomp($a->cached_balance, '0', 2) === 0)->values();

    // Subtotals are summed from the rows actually rendered, so a group total can
    // never disagree with the lines underneath it.
    $sum = fn ($set) => $set->reduce(fn ($carry, $a) => bcadd($carry, $a->cached_balance, 2), '0.00');
    $bankTotal = $sum($bankAccounts);
    $cardTotal = $sum($cardAccounts);
@endphp

{{--
    Two independent columns rather than a twelve-column grid of rows.

    In a grid, every card in a row stretches to the tallest one — so a Balances
    panel listing sixteen accounts forced a half-page of white space beside a
    donut with three categories in it. Stacking each column separately lets both
    flow to their own natural height.
--}}
<div class="mt-4 grid items-start gap-4 lg:grid-cols-12">

    <div class="space-y-4 lg:col-span-8">

        {{-- The trend leads, because one month in isolation cannot tell you
             whether a figure is a problem. --}}
        <x-ui.card>
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

        <x-ui.card>
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
                                       class="group flex items-center gap-3 rounded-md px-2 py-1.5 transition-colors hover:bg-muted">
                                        <span class="size-2 shrink-0 rounded-full" data-swatch="{{ $i }}"></span>
                                        <span class="min-w-0 flex-1 truncate text-[0.8125rem]">{{ $row->label }}</span>
                                        {{-- A share bar reads faster than the bare
                                             percentage it replaces the need to compare. --}}
                                        <span class="hidden h-1 w-24 shrink-0 overflow-hidden rounded-full bg-muted sm:block">
                                            <span class="block h-full rounded-full" data-bar="{{ $i }}"
                                                  style="width: {{ $share }}%"></span>
                                        </span>
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

        <x-ui.card>
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
    </div>

    <div class="space-y-4 lg:col-span-4">

        <x-ui.card>
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

        {{--
            Balances.

            Sixteen accounts as one flat alphabetical list made this the tallest
            thing on the page, and most of it said nothing: nine of the rows were
            cards sitting at zero. Now the two totals lead, each group carries its
            own subtotal, the rows are ordered by size, and the cards at zero
            collapse to a single line. The list is capped and scrolls internally
            so adding a tenth card cannot stretch the page again.
        --}}
        <x-ui.card x-data="{ showZero: false }">
            <x-ui.card-header title="Balances">
                <x-slot:action>
                    <a href="{{ route('accounts.index') }}"
                       class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                        Manage <x-ui.icon name="chevron-right" class="size-3.5" />
                    </a>
                </x-slot:action>
            </x-ui.card-header>

            @if ($accounts->isEmpty())
                <x-ui.empty-state icon="wallet" title="No accounts yet" />
            @else
                <div class="grid grid-cols-2 divide-x divide-border border-b border-border">
                    <div class="px-5 py-3">
                        <p class="text-xs text-muted-foreground">In bank &amp; cash</p>
                        <x-finance.money :amount="$bankTotal" tone="strong" class="mt-0.5 block text-lg font-semibold" />
                    </div>
                    <div class="px-5 py-3">
                        <p class="text-xs text-muted-foreground">Owed on cards</p>
                        <x-finance.money :amount="$cardTotal" tone="debt" class="mt-0.5 block text-lg font-semibold" />
                    </div>
                </div>

                <div class="scroll-thin max-h-[22rem] overflow-y-auto">
                    @if ($bankAccounts->isNotEmpty())
                        <p class="sticky top-0 z-10 flex items-center justify-between gap-2 border-b border-border
                                  bg-muted/70 px-5 py-1.5 text-[0.6875rem] font-semibold uppercase
                                  tracking-wider text-subtle backdrop-blur">
                            <span>Bank &amp; cash</span>
                            <span class="tabular">{{ \App\Support\Money::compact($bankTotal) }}</span>
                        </p>
                        <ul class="divide-y divide-border">
                            @foreach ($bankAccounts as $account)
                                <li>
                                    <a href="{{ route('accounts.show', $account) }}"
                                       class="flex items-center gap-3 px-5 py-2 transition-colors hover:bg-muted">
                                        <div class="min-w-0 flex-1">
                                            <span class="block truncate text-[0.8125rem]">{{ $account->name }}</span>
                                            @if ($account->owner)
                                                <span class="block text-xs text-muted-foreground">{{ $account->owner->name }}</span>
                                            @endif
                                        </div>
                                        <x-finance.money :amount="$account->cached_balance"
                                            tone="strong" class="text-[0.8125rem]" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($cardAccounts->isNotEmpty())
                        <p class="sticky top-0 z-10 flex items-center justify-between gap-2 border-y border-border
                                  bg-muted/70 px-5 py-1.5 text-[0.6875rem] font-semibold uppercase
                                  tracking-wider text-subtle backdrop-blur">
                            <span>Credit cards</span>
                            <span class="tabular">{{ \App\Support\Money::compact($cardTotal) }}</span>
                        </p>
                        <ul class="divide-y divide-border">
                            @foreach ($cardsWithBalance as $account)
                                <li>
                                    <a href="{{ route('accounts.show', $account) }}"
                                       class="flex items-center gap-3 px-5 py-2 transition-colors hover:bg-muted">
                                        <div class="min-w-0 flex-1">
                                            <span class="block truncate text-[0.8125rem]">{{ $account->name }}</span>
                                            @if ($account->owner)
                                                <span class="block text-xs text-muted-foreground">{{ $account->owner->name }}</span>
                                            @endif
                                        </div>
                                        <x-finance.money :amount="$account->cached_balance"
                                            tone="debt" class="text-[0.8125rem]" />
                                    </a>
                                </li>
                            @endforeach

                            @if ($cardsAtZero->isNotEmpty())
                                <li>
                                    <button type="button" x-on:click="showZero = !showZero"
                                            class="flex w-full items-center gap-2 px-5 py-2 text-left text-xs
                                                   text-muted-foreground transition-colors hover:bg-muted hover:text-foreground">
                                        <x-ui.icon name="chevron-right" class="size-3.5 transition-transform"
                                            x-bind:class="showZero && 'rotate-90'" />
                                        <span x-text="showZero ? 'Hide' : 'Show'">Show</span>
                                        <span>{{ $cardsAtZero->count() }} {{ \Illuminate\Support\Str::plural('card', $cardsAtZero->count()) }} with nothing owed</span>
                                    </button>
                                </li>

                                {{-- One <li> inside the template, not a <div>: an
                                     Alpine x-if template needs a single root, and
                                     a ul may only contain list items. --}}
                                <template x-if="showZero">
                                    <li class="divide-y divide-border border-t border-border">
                                        @foreach ($cardsAtZero as $account)
                                            <a href="{{ route('accounts.show', $account) }}"
                                               class="flex items-center gap-3 px-5 py-2 transition-colors hover:bg-muted">
                                                <div class="min-w-0 flex-1">
                                                    <span class="block truncate text-[0.8125rem] text-muted-foreground">{{ $account->name }}</span>
                                                    @if ($account->owner)
                                                        <span class="block text-xs text-subtle">{{ $account->owner->name }}</span>
                                                    @endif
                                                </div>
                                                <x-finance.money :amount="$account->cached_balance"
                                                    tone="muted" class="text-[0.8125rem]" />
                                            </a>
                                        @endforeach
                                    </li>
                                </template>
                            @endif
                        </ul>
                    @endif
                </div>
            @endif

            <x-ui.card-footer class="flex items-center justify-between">
                <span>Net worth</span>
                <x-finance.money :amount="$netWorth['net_worth']" tone="strong" class="text-sm" />
            </x-ui.card-footer>
        </x-ui.card>

        {{-- Only when a bill is actually due. What is owed across cards already
             appears in Balances above, so with no dues this panel would be
             repeating a figure the reader has just passed. --}}
        @if ($cardDues->isNotEmpty())
            <x-ui.card>
                <x-ui.card-header title="Card bills due" description="In the next 30 days">
                    <x-slot:action>
                        <a href="{{ route('credit-cards.index') }}"
                           class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                            All <x-ui.icon name="chevron-right" class="size-3.5" />
                        </a>
                    </x-slot:action>
                </x-ui.card-header>
                <x-ui.card-content flush>
                    <ul class="divide-y divide-border">
                        @foreach ($cardDues as $due)
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                                <div class="min-w-0">
                                    <a href="{{ route('credit-cards.statements.show', [$due->creditCard, $due]) }}"
                                       class="block truncate text-sm hover:underline">{{ $due->creditCard->card_name }}</a>
                                    <p class="text-xs text-muted-foreground">due {{ $due->due_date->format('d M') }}</p>
                                </div>
                                <x-finance.money :amount="$due->balanceRemaining()" class="text-sm" tone="strong" />
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card-content>
            </x-ui.card>
        @endif

        {{-- Two small cuts of the same month's spending. Side by side in one
             card rather than stacked in two, since each is only a few rows. --}}
        @if ($byPlanned->isNotEmpty() || $byPayer->isNotEmpty())
            <x-ui.card>
                <x-ui.card-header title="This month's split" />
                <x-ui.card-content flush>
                    @if ($byPlanned->isNotEmpty())
                        <p class="border-b border-border bg-muted/50 px-5 py-1.5 text-[0.6875rem]
                                  font-semibold uppercase tracking-wider text-subtle">Planned vs unplanned</p>
                        <ul class="divide-y divide-border">
                            @foreach ($byPlanned as $row)
                                @php($share = bccomp($spending, '0', 2) === 1 ? round($row->amount / $spending * 100) : 0)
                                <li>
                                    <a href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'planned_status' => $row->key, 'type' => 'expense']) }}"
                                       class="flex items-center gap-3 px-5 py-2 transition-colors hover:bg-muted">
                                        <span class="min-w-0 flex-1 truncate text-[0.8125rem]">{{ $row->label }}</span>
                                        <span class="text-xs text-muted-foreground tabular">{{ $share }}%</span>
                                        <x-finance.money :amount="$row->amount" class="w-20 text-right text-[0.8125rem]" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($byPayer->isNotEmpty())
                        <p class="border-y border-border bg-muted/50 px-5 py-1.5 text-[0.6875rem]
                                  font-semibold uppercase tracking-wider text-subtle">Who paid</p>
                        <ul class="divide-y divide-border">
                            @foreach ($byPayer as $row)
                                @php($share = bccomp($spending, '0', 2) === 1 ? round($row->amount / $spending * 100) : 0)
                                <li>
                                    <a href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'payer_id' => $row->key, 'type' => 'expense']) }}"
                                       class="flex items-center gap-3 px-5 py-2 transition-colors hover:bg-muted">
                                        <span class="min-w-0 flex-1 truncate text-[0.8125rem]">{{ $row->label }}</span>
                                        <span class="text-xs text-muted-foreground tabular">{{ $share }}%</span>
                                        <x-finance.money :amount="$row->amount" class="w-20 text-right text-[0.8125rem]" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card-content>
            </x-ui.card>
        @endif
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
        // swatch and share-bar colours have to be filled from the same palette
        // the chart uses — a legend that disagrees with its chart is worse than
        // no legend at all.
        document.querySelectorAll('[data-swatch]').forEach(function (el) {
            el.style.backgroundColor = colors[Number(el.dataset.swatch) % colors.length];
        });

        document.querySelectorAll('[data-bar]').forEach(function (el) {
            el.style.backgroundColor = colors[Number(el.dataset.bar) % colors.length];
        });

        ftChart.donut(document.getElementById('categoryChart'), { labels: labels, data: data });
    @endif
});
</script>
@endpush
