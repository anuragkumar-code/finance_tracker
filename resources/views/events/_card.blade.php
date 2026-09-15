{{-- One trip or event on the index. --}}
@php
    $cost = bcadd((string) ($event->cost ?? '0'), '0', 2);
    $theyOwe = $event->settlements->where('direction', \App\Enums\SettlementDirection::TheyOwe);
    $weOwe = $event->settlements->where('direction', \App\Enums\SettlementDirection::WeOwe);
    $isCurrent = ! $event->is_archived && $event->covers(today());
    $icon = $event->kind->icon();
    $ring = $isCurrent ? 'ring-2 ring-info/40' : '';
@endphp

<a href="{{ route('events.show', $event) }}"
   class="group block rounded-xl border border-border bg-card p-4 shadow-xs transition-shadow hover:shadow-md {{ $ring }}">
    <div class="flex items-start gap-3">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
            <x-ui.icon :name="$icon" class="size-4 text-muted-foreground" />
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5">
                <p class="truncate text-sm font-semibold group-hover:underline">{{ $event->name }}</p>
                @if ($isCurrent)
                    <x-ui.badge variant="info">Now</x-ui.badge>
                @endif
            </div>
            <p class="text-xs text-muted-foreground">{{ $event->dateRange() }} · {{ $event->kind->label() }}</p>
        </div>
    </div>

    <div class="mt-4 flex items-end justify-between gap-3">
        <div>
            <p class="text-[0.6875rem] uppercase tracking-wide text-subtle">Your cost</p>
            <x-finance.money :amount="$cost" tone="strong" class="text-lg font-semibold" />
        </div>
        <p class="text-xs text-muted-foreground">
            {{ $event->expenses_count }} {{ \Illuminate\Support\Str::plural('entry', $event->expenses_count) }}
        </p>
    </div>

    @if ($theyOwe->isNotEmpty() || $weOwe->isNotEmpty() || $event->people->isNotEmpty())
        <div class="mt-3 flex flex-wrap items-center gap-1.5 border-t border-border pt-3">
            @foreach ($theyOwe as $s)
                <x-ui.badge variant="success">{{ $s->person->name }} owes {{ \App\Support\Money::inr($s->amount) }}</x-ui.badge>
            @endforeach
            @foreach ($weOwe as $s)
                <x-ui.badge variant="warning">You owe {{ $s->person->name }} {{ \App\Support\Money::inr($s->amount) }}</x-ui.badge>
            @endforeach
            @if ($event->settlements->isEmpty() && $event->people->isNotEmpty())
                <span class="truncate text-xs text-muted-foreground">
                    with {{ $event->people->pluck('name')->join(', ', ' & ') }}
                </span>
            @endif
        </div>
    @endif
</a>
