{{-- Where the event's money went, and on which days. --}}
<x-ui.card>
    <x-ui.card-header title="Where it went" />
    <x-ui.card-content>
        @if ($breakdown->isEmpty())
            <p class="text-sm text-muted-foreground">Appears once spends are recorded.</p>
        @else
            <ul class="space-y-3">
                @foreach ($breakdown as $row)
                    <li>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="truncate">{{ $row->label }}</span>
                            <span class="flex shrink-0 items-baseline gap-2">
                                <span class="text-xs text-muted-foreground tabular">{{ $row->share }}%</span>
                                <x-finance.money :amount="$row->amount" />
                            </span>
                        </div>
                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-primary/70" style="width: {{ $row->share }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card-content>

    @if ($byDay->count() > 1)
        <div class="border-t border-border px-5 py-3">
            <p class="text-[0.6875rem] font-semibold uppercase tracking-wider text-subtle">By day</p>
            <ul class="mt-2 space-y-1.5">
                @foreach ($byDay as $day)
                    <li class="flex items-center justify-between gap-3 text-sm">
                        <span>{{ $day->date->format('D, d M') }}
                            <span class="text-xs text-muted-foreground">· {{ $day->count }}</span></span>
                        <x-finance.money :amount="$day->amount" />
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($event->people->isNotEmpty())
        <div class="border-t border-border px-5 py-3">
            <p class="text-[0.6875rem] font-semibold uppercase tracking-wider text-subtle">Who came</p>
            <p class="mt-1 text-sm">{{ $event->people->pluck('name')->join(', ', ' & ') }}</p>
        </div>
    @endif
</x-ui.card>
