{{--
    Every spend filed under the event.

    An entry a friend's share was taken out of shows the amount it has now (the
    household's share) with what was moved underneath, so the figure on screen
    and the one on the card statement can both be recognised.
--}}
<x-ui.card>
    <x-ui.card-header title="Spends" :description="$expenses->count().' '.\Illuminate\Support\Str::plural('entry', $expenses->count())">
        <x-slot:action>
            <a href="{{ route('transactions.index', ['event_id' => $event->id]) }}"
               class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                In ledger <x-ui.icon name="chevron-right" class="size-3.5" />
            </a>
        </x-slot:action>
    </x-ui.card-header>

    @if ($expenses->isEmpty())
        <x-ui.empty-state icon="inbox" title="Nothing recorded under this yet"
            description="Record spends in Quick Entry and pick this trip, or edit an existing entry to file it here.">
            <x-ui.button :href="route('quick-entry', ['event' => $event->id])" size="sm" icon="plus">Add a spend</x-ui.button>
        </x-ui.empty-state>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Details</th>
                        <th scope="col" class="hidden md:table-cell">Paid with</th>
                        <th scope="col" class="num">Your share</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($expenses as $t)
                        @php
                            $moved = $t->shareAllocations->reduce(fn ($c, $a) => bcadd($c, (string) $a->reduced_by, 2), '0.00');
                            $wasShared = bccomp($moved, '0', 2) === 1;
                            $fromFriend = $t->account->isFriendBalance();
                            $label = $t->description ?: $t->merchant?->name ?: ($t->subcategory?->name ?? $t->category?->name ?? 'Spend');
                            $categoryLabel = $t->subcategory ? $t->category?->name.' › '.$t->subcategory->name : ($t->category?->name ?? 'Uncategorised');
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap text-muted-foreground tabular">{{ $t->transaction_date->format('d M') }}</td>
                            <td>
                                <a href="{{ route('transactions.show', $t) }}" class="font-medium hover:underline">{{ $label }}</a>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                                    <span>{{ $categoryLabel }}</span>
                                    @if ($wasShared)
                                        <x-ui.badge variant="success">
                                            {{ \App\Support\Money::inr($moved) }} was a friend's share
                                        </x-ui.badge>
                                    @endif
                                    @if ($fromFriend)
                                        <x-ui.badge variant="warning">Paid by {{ $t->account->name }}</x-ui.badge>
                                    @endif
                                </div>
                            </td>
                            <td class="hidden text-muted-foreground md:table-cell">{{ $t->account->name }}</td>
                            <td class="num">
                                <x-finance.money :amount="$t->amount" tone="strong" />
                                @if ($wasShared)
                                    <span class="block text-[0.6875rem] text-subtle">
                                        of {{ \App\Support\Money::inr(bcadd((string) $t->amount, $moved, 2)) }} paid
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-ui.card-footer class="flex items-center justify-between">
            <span>Your cost</span>
            <x-finance.money :amount="$ourCost" tone="strong" class="text-sm" />
        </x-ui.card-footer>
    @endif
</x-ui.card>
