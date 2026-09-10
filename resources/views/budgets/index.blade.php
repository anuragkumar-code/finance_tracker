@extends('layouts.app')

@section('title', 'Budgets')
@section('heading', 'Budgets')
@section('subheading', $month->format('F Y'))

@section('actions')
    <form method="GET" class="flex items-center gap-2">
        <input type="month" name="month" value="{{ $month->format('Y-m') }}"
               class="h-9 rounded-md border border-input bg-card px-3 text-sm
                      focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
        <x-ui.button type="submit" variant="outline">Go</x-ui.button>
    </form>
@endsection

@section('content')

@unless ($hasEnoughHistory)
    {{-- Spec section 22: budgets set before behaviour is understood are
         arbitrary. Say so rather than quietly offering made-up targets. --}}
    <div class="mb-4">
        <x-ui.alert variant="info" :title="'You have '.$monthsOfHistory.' month(s) of spending recorded'">
            You can set any budget you already know — rent does not need a study. But the app will
            not <em>suggest</em> amounts until it has about {{ $monthsNeeded }} months to work from,
            because a target invented from a few entries is a guess wearing a number's clothes.
        </x-ui.alert>
    </div>
@endunless

@if ($anomalies->isNotEmpty())
    {{-- Judged against each category's own history, so a household that always
         spends heavily on rent is not warned about rent every month. --}}
    <div class="mb-4">
        <x-ui.alert variant="warning" title="Spending more than usual this month">
            <ul class="mt-1 space-y-0.5">
                @foreach ($anomalies as $a)
                    <li>
                        <span class="font-medium text-foreground">{{ $a->category?->name ?? 'Uncategorised' }}</span>
                        — {{ \App\Support\Money::inr($a->spent) }} against a usual
                        {{ \App\Support\Money::inr($a->usual) }}
                        ({{ $a->ratio }}×, from {{ $a->months }} month(s) of history)
                    </li>
                @endforeach
            </ul>
        </x-ui.alert>
    </div>
@endif

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
    <x-ui.stat label="Budgeted" icon="chart-pie" :value="\App\Support\Money::inr($totals['budgeted'])" />
    <x-ui.stat label="Spent against it" :value="\App\Support\Money::inr($totals['spent'])" />
    <x-ui.stat label="Left"
        :tone="bccomp($totals['remaining'], '0', 2) === -1 ? 'expense' : 'income'"
        :value="\App\Support\Money::inr($totals['remaining'])" />
    <x-ui.stat label="Outside any budget" :value="\App\Support\Money::inr($totals['unbudgeted'])"
        hint="spending with no target set" />
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-12">
    <div class="lg:col-span-8">
        <x-ui.card>
            <x-ui.card-header title="Budget vs actual" />
            <x-ui.card-content flush>
                @if ($rows->isEmpty())
                    <x-ui.empty-state icon="chart-pie" title="Nothing to compare yet"
                        description="No budgets set and nothing spent this month." />
                @else
                    <ul class="divide-y divide-border">
                        @foreach ($rows as $row)
                            @php
                                $barTone = match ($row->status) {
                                    'over' => 'destructive',
                                    'ahead_of_pace', 'close' => 'warning',
                                    default => 'success',
                                };
                            @endphp
                            <li class="px-5 py-3">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <a href="{{ route('transactions.index', [
                                               'start' => $month->copy()->startOfMonth()->toDateString(),
                                               'end' => $month->copy()->endOfMonth()->toDateString(),
                                               'type' => 'expense',
                                               'category_id' => $row->category_id,
                                           ]) }}" class="text-sm font-medium hover:underline">
                                            {{ $row->category?->name ?? 'Uncategorised' }}
                                        </a>
                                        @if ($row->status === 'over')
                                            <x-ui.badge variant="destructive">Over</x-ui.badge>
                                        @elseif ($row->status === 'ahead_of_pace')
                                            <x-ui.badge variant="warning">Spending fast</x-ui.badge>
                                        @elseif ($row->status === 'unbudgeted')
                                            <x-ui.badge variant="outline">No budget</x-ui.badge>
                                        @endif
                                    </div>

                                    <div class="flex items-baseline gap-2 text-sm">
                                        <x-finance.money :amount="$row->spent" tone="strong" />
                                        @if ($row->budget !== null)
                                            <span class="text-muted-foreground">
                                                of <x-finance.money :amount="$row->budget" tone="muted" />
                                            </span>
                                        @endif
                                        @if ($row->budget !== null)
                                            <form method="POST" action="{{ route('budgets.destroy', $row->category_id) }}">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        class="rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                        aria-label="Remove budget for {{ $row->category?->name }}">
                                                    <x-ui.icon name="trash-2" class="size-3.5" />
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                @if ($row->used_percent !== null)
                                    <div class="mt-2">
                                        <x-ui.progress :value="$row->used_percent" :variant="$barTone"
                                            :label="($row->category?->name ?? 'Category').' budget used'" />
                                        {{-- Pace against the calendar, not in a vacuum: 70% spent on
                                             the 5th is a different thing from the 25th. --}}
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            {{ $row->used_percent }}% used ·
                                            {{ $row->month_elapsed_percent }}% of month gone ·
                                            <x-finance.money :amount="$row->remaining" tone="muted" /> left
                                        </p>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card-content>
            <x-ui.card-footer>
                "% of month gone" sits next to "% used" on purpose: spending 70% of a budget on the
                5th is a very different thing from the same figure on the 25th.
            </x-ui.card-footer>
        </x-ui.card>
    </div>

    <div class="space-y-4 lg:col-span-4">
        <x-ui.card>
            <x-ui.card-header title="Set a budget" />
            <form method="POST" action="{{ route('budgets.store') }}">
                @csrf
                <x-ui.card-content class="space-y-4">
                    <x-ui.select label="Category" name="category_id" id="category_id" required>
                        <option value="">Choose…</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input label="Monthly amount" name="amount" id="amount" inputmode="decimal"
                        prefix="₹" :value="old('amount')" required
                        hint="Applies from this month onwards. Earlier months keep whatever they were judged against." />

                    <div id="suggestionHint" class="hidden rounded-md border border-border bg-muted/60 px-3 py-2 text-xs"></div>

                    <input type="hidden" name="month" value="{{ $month->copy()->startOfMonth()->toDateString() }}">
                    <x-ui.button type="submit" class="w-full">Save budget</x-ui.button>
                </x-ui.card-content>
            </form>
        </x-ui.card>

        <x-ui.alert variant="muted" icon="info" title="How these are judged">
            A budget covers ordinary spending in that category. Transfers, card bill payments and
            money moved to your emergency fund are not spending, so they never count against a
            budget. Loan EMIs do, because this household chose to count them as spending.
        </x-ui.alert>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const select = document.getElementById('category_id');
    const amount = document.getElementById('amount');
    const hint = document.getElementById('suggestionHint');

    // Offer the household's own average when there is enough history to mean
    // something — and say plainly when there is not.
    select.addEventListener('change', () => {
        if (!select.value) { hint.classList.add('hidden'); return; }

        fetch('{{ url('budgets') }}/' + select.value + '/suggestion', { headers: { 'Accept': 'application/json' } })
            .then((r) => r.json())
            .then((data) => {
                hint.classList.remove('hidden');

                if (!data.amount) {
                    hint.textContent = data.reason;
                    return;
                }

                hint.innerHTML = '';
                const text = document.createElement('span');
                text.textContent = 'Your average over the last ' + data.months + ' months is ₹'
                    + Number(data.amount).toLocaleString('en-IN') + '. ';

                const use = document.createElement('button');
                use.type = 'button';
                use.className = 'font-medium underline underline-offset-2';
                use.textContent = 'Use it';
                use.addEventListener('click', () => { amount.value = data.amount; });

                hint.append(text, use);
            })
            .catch(() => hint.classList.add('hidden'));
    });
})();
</script>
@endpush
