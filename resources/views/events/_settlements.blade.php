{{-- Squaring up: what has been settled, with whom, and each friend's running balance. --}}
<x-ui.card>
    <x-ui.card-header title="Settled up" description="Shares moved to or from friends">
        <x-slot:action>
            <button type="button" x-on:click="$dispatch('open-dialog', 'settle-up')"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                <x-ui.icon name="plus" class="size-3.5" /> Settle
            </button>
        </x-slot:action>
    </x-ui.card-header>

    @if ($event->settlements->isEmpty())
        <x-ui.card-content>
            <p class="text-sm text-muted-foreground">
                Nothing settled yet. When the trip is over, work out what each friend owes you
                (or you owe them) and enter the final figure — only your share stays as spending.
            </p>
        </x-ui.card-content>
    @else
        <ul class="divide-y divide-border">
            @foreach ($event->settlements->sortByDesc('id') as $settlement)
                @php
                    $theyOweThis = $settlement->direction === \App\Enums\SettlementDirection::TheyOwe;
                    $settlementText = $theyOweThis
                        ? $settlement->person->name.' owes you'
                        : 'You owe '.$settlement->person->name;
                    $settlementTone = $theyOweThis ? 'income' : 'debt';
                    $dialogId = 'undo-settlement-'.$settlement->id;
                @endphp
                <li class="flex items-center gap-3 px-5 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $settlementText }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ $settlement->settled_on->format('d M Y') }}
                            @if ($settlement->notes) · {{ $settlement->notes }} @endif
                        </p>
                    </div>
                    <x-finance.money :amount="$settlement->amount" :tone="$settlementTone" class="text-sm font-medium" />
                    <button type="button" x-on:click="$dispatch('open-dialog', '{{ $dialogId }}')"
                            class="shrink-0 rounded p-1 text-subtle hover:bg-muted hover:text-foreground"
                            aria-label="Undo this settlement">
                        <x-ui.icon name="undo-2" class="size-3.5" />
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($friendBalances->isNotEmpty())
        <div class="border-t border-border bg-muted/40 px-5 py-3">
            <p class="text-[0.6875rem] font-semibold uppercase tracking-wider text-subtle">Balances right now</p>
            <ul class="mt-2 space-y-1.5">
                @foreach ($friendBalances as $row)
                    @php
                        $sign = bccomp($row->balance, '0', 2);
                        $balanceText = match ($sign) {
                            1 => 'owes you',
                            -1 => 'you owe',
                            default => 'all square',
                        };
                        $absolute = $sign === -1 ? bcsub('0', $row->balance, 2) : $row->balance;
                        $balanceTone = match ($sign) {
                            1 => 'income',
                            -1 => 'debt',
                            default => 'muted',
                        };
                    @endphp
                    <li class="flex items-center justify-between gap-3 text-sm">
                        <span class="truncate">{{ $row->person->name }} <span class="text-xs text-muted-foreground">{{ $balanceText }}</span></span>
                        @if ($sign !== 0)
                            <x-finance.money :amount="$absolute" :tone="$balanceTone" />
                        @endif
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('friends.index') }}"
               class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground">
                Record a repayment <x-ui.icon name="chevron-right" class="size-3" />
            </a>
        </div>
    @endif
</x-ui.card>

@foreach ($event->settlements as $settlement)
    <x-ui.dialog :id="'undo-settlement-'.$settlement->id" title="Undo this settlement?"
        description="Every entry goes back exactly as it was before you settled.">
        <form method="POST" action="{{ route('settlements.undo', $settlement) }}">
            @csrf
            @method('DELETE')
            <div class="px-5 py-4 text-sm text-muted-foreground">
                The share moved for {{ $settlement->person->name }}
                ({{ \App\Support\Money::inr($settlement->amount) }}) is put back, and their balance
                changes by the same amount. Do this if the figure was wrong — then settle again.
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-5 py-3">
                <x-ui.button type="button" variant="ghost"
                    x-on:click="$dispatch('close-dialog', 'undo-settlement-{{ $settlement->id }}')">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="destructive">Undo settlement</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
@endforeach
