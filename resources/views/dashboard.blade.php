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

@if (! $hasAccounts)
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
    $spendSeries = $series->pluck('spending')->all();
    $cardSeries = $series->pluck('card_spending')->all();
    $investedSeries = $series->pluck('invested')->all();
@endphp

{{-- Four figures that answer "how are we doing" in a couple of seconds. --}}
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    <x-ui.stat label="Invested" icon="trending-up" tone="income"
        :value="\App\Support\Money::inr($invested)"
        :series="$investedSeries"
        hint="Money moved, not spent" />

    @php
        // While a month is running, the only fair comparison is the same number
        // of days into the previous one.
        $spentHint = $isCurrentMonth
            ? 'By day '.$dayOfMonth.' of '.$previousMonthLabel.': '.\App\Support\Money::inr($spentByNowLastMonth)
            : 'Whole month';

        if (! $comparable) {
            $spentHint = 'Nothing recorded that far back to compare with';
        }

        $spentDeltaLabel = $isCurrentMonth ? 'vs same point in '.$previousMonthLabel : 'vs '.$previousLabel;
    @endphp

    <x-ui.stat label="Spent" icon="trending-down"
        :value="\App\Support\Money::inr($spending)"
        :delta="$spentSoFarDelta" :delta-label="$spentDeltaLabel" delta-good="down"
        :series="$spendSeries"
        :hint="$spentHint" />

    <x-ui.stat label="On credit cards" icon="credit-card"
        :value="\App\Support\Money::inr($cardSpending)"
        :delta="$cardDelta" :delta-label="'vs '.$previousLabel" delta-good="down"
        :series="$cardSeries"
        hint="Charged this month" />

    @php
        $remainingHint = bccomp($committedRemaining, '0', 2) === 1
            ? \App\Support\Money::inr($committedRemaining).' still to go this month'
            : 'Nothing left to go out this month';
    @endphp

    <x-ui.stat label="Already committed" icon="calendar-clock"
        :value="\App\Support\Money::inr($split['committed'])"
        :href="route('upcoming.index')"
        :hint="$remainingHint" />
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

                        // Block @php only in this file, never the inline @php(...)
                        // form: Blade pairs an inline @php( with the next @endphp
                        // and swallows everything between them uncompiled, which
                        // either fails as "unexpected end of file" or renders the
                        // page scrambled with no error at all.
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
    $committedShare = $split['committed_share'];
    $discretionaryShare = 100 - $committedShare;
@endphp

<div class="mt-4 grid items-start gap-4 lg:grid-cols-12">
    {{--
        Committed against chosen.

        Around three fifths of this household's spending is EMIs, rent and
        recurring charges that do not change month to month. Shown as one total,
        an ordinary month looks alarmingly variable; split, the part worth
        thinking about is obvious.
    --}}
    <x-ui.card class="lg:col-span-5">
        <x-ui.card-header title="Committed vs chosen" :description="$month->format('F')" />
        <x-ui.card-content class="space-y-4">
            <div class="flex h-2.5 overflow-hidden rounded-full bg-muted">
                <div class="bg-debt" style="width: {{ $committedShare }}%"></div>
                <div class="bg-primary/70" style="width: {{ $discretionaryShare }}%"></div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <span class="size-2 rounded-full bg-debt"></span> Already committed
                    </p>
                    <x-finance.money :amount="$split['committed']" tone="strong" class="mt-0.5 block text-lg font-semibold" />
                    <p class="text-xs text-muted-foreground">{{ $committedShare }}% · EMIs, rent, bills</p>
                </div>
                <div>
                    <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <span class="size-2 rounded-full bg-primary/70"></span> Your choices
                    </p>
                    <x-finance.money :amount="$split['discretionary']" tone="strong" class="mt-0.5 block text-lg font-semibold" />
                    <p class="text-xs text-muted-foreground">{{ $discretionaryShare }}% · the part you can move</p>
                </div>
            </div>
        </x-ui.card-content>
    </x-ui.card>

    {{-- "Month on month" is really asking what changed, so the movement is
         reported rather than two columns to compare by eye. --}}
    <x-ui.card class="lg:col-span-7">
        <x-ui.card-header title="What changed"
            :description="$isCurrentMonth
                ? 'Against the same days of '.$previousMonthLabel
                : 'Against '.$previousLabel" />
        <x-ui.card-content flush>
            @if (! $comparable)
                <div class="px-5 py-6 text-sm text-muted-foreground">
                    There is nothing recorded that far back to compare against yet. This fills in
                    once you have two full months of entries.
                </div>
            @elseif ($movers->isEmpty())
                <div class="px-5 py-6 text-sm text-muted-foreground">Nothing moved much either way.</div>
            @else
                <ul class="divide-y divide-border">
                    @foreach ($movers as $mover)
                        @php
                            $rose = bccomp($mover->delta, '0', 2) === 1;
                            $moverTone = $rose ? 'expense' : 'income';
                            $absolute = $rose ? $mover->delta : bcsub('0', $mover->delta, 2);
                            $moverLink = route('transactions.index', [
                                'start' => $start, 'end' => $end, 'type' => 'expense',
                                'category_id' => $mover->category_id,
                            ]);
                        @endphp
                        <li>
                            <a href="{{ $moverLink }}"
                               class="flex items-center gap-3 px-5 py-2.5 transition-colors hover:bg-muted">
                                <x-ui.icon :name="$rose ? 'arrow-up' : 'arrow-down'"
                                    class="size-3.5 shrink-0 {{ $rose ? 'text-expense' : 'text-income' }}" />
                                <span class="min-w-0 flex-1 truncate text-sm">{{ $mover->label }}</span>
                                <span class="shrink-0 text-xs text-muted-foreground tabular">
                                    {{ \App\Support\Money::compact($mover->before) }}
                                    →
                                    {{ \App\Support\Money::compact($mover->now) }}
                                </span>
                                <x-finance.money :amount="$absolute" :tone="$moverTone" class="w-24 text-right text-sm" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card-content>
    </x-ui.card>
</div>

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
            <x-ui.card-header title="Spending trend" description="Six months to {{ $month->format('M Y') }}">
                <x-slot:action>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-expense"></span>
                            <span class="text-muted-foreground">Spent</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-[var(--chart-1)]"></span>
                            <span class="text-muted-foreground">On cards</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-subtle"></span>
                            <span class="text-muted-foreground">Committed</span>
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
                                @php
                                    $share = bccomp($spending, '0', 2) === 1 ? round($row->amount / $spending * 100) : 0;
                                @endphp
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
            How the month was paid for.

            This replaced "owed on cards". The app holds no view of what is owed
            on a card any more — that is what the card's own statement is for —
            but which way the money left is exactly the question the household
            does want answered.
        --}}
        @if ($byPaymentMode->isNotEmpty())
            <x-ui.card>
                <x-ui.card-header title="How you paid" :description="$month->format('F')" />
                <x-ui.card-content flush>
                    <ul class="divide-y divide-border">
                        @foreach ($byPaymentMode as $mode)
                            @php
                                $share = bccomp($spending, '0', 2) === 1
                                    ? (int) round((float) $mode->amount / (float) $spending * 100)
                                    : 0;
                            @endphp
                            <li class="px-5 py-2.5">
                                <div class="flex items-center justify-between gap-3">
                                    <a href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'type' => 'expense', 'account_type' => $mode->key]) }}"
                                       class="min-w-0 truncate text-sm hover:underline">
                                        {{ $mode->label }}
                                        <span class="text-xs text-muted-foreground">· {{ $mode->count }}</span>
                                    </a>
                                    <span class="flex shrink-0 items-baseline gap-3">
                                        <span class="text-xs text-muted-foreground tabular">{{ $share }}%</span>
                                        <x-finance.money :amount="$mode->amount" tone="strong" class="text-sm" />
                                    </span>
                                </div>
                                <div class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-primary/70" style="width: {{ $share }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card-content>
            </x-ui.card>
        @endif

        @if ($friendBalances->isNotEmpty())
            <x-ui.card>
                <x-ui.card-header title="Friends" description="Still to be squared up">
                    <x-slot:action>
                        <a href="{{ route('friends.index') }}"
                           class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                            All <x-ui.icon name="chevron-right" class="size-3.5" />
                        </a>
                    </x-slot:action>
                </x-ui.card-header>
                <x-ui.card-content flush>
                    <ul class="divide-y divide-border">
                        @foreach ($friendBalances as $row)
                            @php
                                $owesUs = bccomp($row->balance, '0', 2) === 1;
                                $friendAmount = $owesUs ? $row->balance : bcsub('0', $row->balance, 2);
                                $friendTone = $owesUs ? 'income' : 'debt';
                                $friendText = $owesUs ? 'owes you' : 'you owe';
                            @endphp
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                                <span class="min-w-0 truncate text-sm">
                                    {{ $row->person->name }}
                                    <span class="text-xs text-muted-foreground">{{ $friendText }}</span>
                                </span>
                                <x-finance.money :amount="$friendAmount" :tone="$friendTone" class="text-sm" />
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
                                @php
                                    $share = bccomp($spending, '0', 2) === 1 ? round($row->amount / $spending * 100) : 0;
                                @endphp
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
                                @php
                                    $share = bccomp($spending, '0', 2) === 1 ? round($row->amount / $spending * 100) : 0;
                                @endphp
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
            { label: 'Spent', data: @json($series->pluck('spending')->map(fn ($a) => (float) $a)), color: ftChart.colors.expense },
            { label: 'On cards', data: @json($series->pluck('card_spending')->map(fn ($a) => (float) $a)), color: ftChart.palette[0], fill: false },
            { label: 'Committed', data: @json($series->pluck('committed')->map(fn ($a) => (float) $a)), color: ftChart.colors.subtle, fill: false },
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
