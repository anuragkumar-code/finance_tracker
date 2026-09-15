@extends('layouts.app')

@section('title', 'Transaction')
@section('heading', $transaction->description ?: $transaction->merchant?->name ?: $transaction->type->label())
@section('subheading', $transaction->transaction_date->format('d M Y'))

@section('actions')
    @unless ($transaction->trashed())
        <x-ui.button :href="route('transactions.edit', $transaction)" variant="outline" icon="pencil">Edit</x-ui.button>
        <x-ui.button variant="outline" icon="trash-2"
            x-on:click="$dispatch('open-dialog', 'void-transaction')"
            class="text-destructive hover:bg-destructive/10">Void</x-ui.button>
    @endunless
    <x-ui.button :href="route('transactions.index')" variant="ghost">Back</x-ui.button>
@endsection

@section('content')

@if ($transaction->trashed())
    <div class="mb-4">
        <x-ui.alert variant="muted" title="This entry is voided">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span>
                    It no longer affects any balance.
                    @if ($transaction->void_reason)
                        Reason: {{ $transaction->void_reason }}
                    @endif
                </span>
                <form method="POST" action="{{ route('transactions.restore', $transaction->id) }}">
                    @csrf
                    <x-ui.button type="submit" variant="outline" size="sm" icon="undo-2">Restore</x-ui.button>
                </form>
            </div>
        </x-ui.alert>
    </div>
@endif

<div class="grid gap-4 lg:grid-cols-12">
    <div class="lg:col-span-7">
        <x-ui.card>
            <x-ui.card-content class="!py-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">{{ $transaction->type->label() }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight tabular">
                            {{ \App\Support\Money::inr($transaction->amount) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-medium text-muted-foreground">Account</p>
                        <a href="{{ route('accounts.show', $transaction->account) }}"
                           class="mt-1 block text-sm font-medium hover:underline">{{ $transaction->account->name }}</a>
                        <p class="text-xs text-muted-foreground">
                            {{ $transaction->balance_effect === \App\Enums\BalanceEffect::Increase ? 'increased' : 'decreased' }}
                            by {{ \App\Support\Money::inr($transaction->amount) }}
                        </p>
                    </div>
                </div>
            </x-ui.card-content>

            <x-ui.card-content class="border-t border-border">
                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    @foreach ([
                        'Category' => $transaction->category?->name
                            .($transaction->subcategory ? ' › '.$transaction->subcategory->name : ''),
                        'Merchant' => $transaction->merchant?->name,
                        'Paid by' => $transaction->payer?->name,
                        'For' => $transaction->beneficiary?->name,
                        'Purpose' => $transaction->purpose?->label(),
                        'Trip or event' => $transaction->event?->name,
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs text-muted-foreground">{{ $label }}</dt>
                            <dd class="mt-0.5 text-sm">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach

                    <div>
                        <dt class="text-xs text-muted-foreground">Planned</dt>
                        <dd class="mt-0.5">
                            @if ($transaction->planned_status)
                                @php
                                    // Kept out of the :variant attribute for readability.
                                    $plannedVariant = match ($transaction->planned_status->value) {
                                        'planned' => 'success', 'emergency' => 'destructive', default => 'warning',
                                    };
                                @endphp
                                <x-ui.badge :variant="$plannedVariant">{{ $transaction->planned_status->label() }}</x-ui.badge>
                            @else
                                <span class="text-sm">—</span>
                            @endif
                        </dd>
                    </div>

                    @if ($transaction->notes)
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-muted-foreground">Notes</dt>
                            <dd class="mt-0.5 text-sm">{{ $transaction->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card-content>
        </x-ui.card>
    </div>

    <div class="space-y-4 lg:col-span-5">
        @if ($counterpart)
            <x-ui.card>
                <x-ui.card-header title="The other side of this move" />
                <x-ui.card-content>
                    @php
                        $friendSide = collect([$transaction->account, $counterpart->account])
                            ->first(fn ($a) => $a->isFriendBalance());
                    @endphp
                    <p class="text-sm text-muted-foreground">
                        @if ($friendSide)
                            Money moving between your account and what {{ $friendSide->name }} owes. It is
                            neither spending nor income — it only changes who owes whom.
                        @else
                            A {{ strtolower($transaction->type->label()) }} between your own accounts, recorded
                            as two linked entries. It is not counted as spending.
                        @endif
                    </p>
                    <div class="mt-3 flex items-center justify-between gap-3 border-t border-border pt-3">
                        <div class="min-w-0">
                            <a href="{{ route('accounts.show', $counterpart->account) }}"
                               class="text-sm font-medium hover:underline">{{ $counterpart->account->name }}</a>
                            <p class="text-xs text-muted-foreground">{{ $counterpart->leg_role->label() }}</p>
                        </div>
                        <x-finance.money :amount="$counterpart->amount" class="text-sm" />
                    </div>
                </x-ui.card-content>
            </x-ui.card>
        @endif

        @php
            $movedShares = $transaction->shareAllocations;
            $movedTotal = $movedShares->reduce(fn ($c, $a) => bcadd($c, (string) $a->reduced_by, 2), '0.00');
            $paidInFull = bcadd((string) $transaction->amount, $movedTotal, 2);
        @endphp

        @if ($movedShares->isNotEmpty())
            <x-ui.card>
                <x-ui.card-header title="Shared with friends" />
                <x-ui.card-content class="space-y-3">
                    <p class="text-sm text-muted-foreground">
                        You paid {{ \App\Support\Money::inr($paidInFull) }}. The friends' shares below were moved to
                        what they owe you, so only {{ \App\Support\Money::inr($transaction->amount) }} counts as your spending.
                    </p>
                    <ul class="divide-y divide-border border-t border-border">
                        @foreach ($movedShares as $allocation)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span>{{ $allocation->settlement->person->name }}'s share</span>
                                <x-finance.money :amount="$allocation->reduced_by" tone="income" />
                            </li>
                        @endforeach
                    </ul>
                    @if ($transaction->event)
                        <x-ui.button :href="route('events.show', $transaction->event)" variant="outline" size="sm">
                            Undo from the trip page
                        </x-ui.button>
                    @else
                        @foreach ($movedShares->pluck('settlement')->unique('id') as $settlement)
                            <form method="POST" action="{{ route('settlements.undo', $settlement) }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="outline" size="sm" icon="undo-2">
                                    Undo split with {{ $settlement->person->name }}
                                </x-ui.button>
                            </form>
                        @endforeach
                    @endif
                </x-ui.card-content>
            </x-ui.card>
        @endif

        @if ($transaction->isSettlementEntry() && $transaction->settlement)
            <x-ui.card>
                <x-ui.card-header title="Written by settling up" />
                <x-ui.card-content class="space-y-3 text-sm text-muted-foreground">
                    <p>
                        This entry exists because a shared cost with {{ $transaction->settlement->person->name }}
                        was settled. It is removed by undoing that settlement, not by editing it.
                    </p>
                    @if ($transaction->settlement->event)
                        <x-ui.button :href="route('events.show', $transaction->settlement->event)" variant="outline" size="sm">
                            Open {{ $transaction->settlement->event->name }}
                        </x-ui.button>
                    @endif
                </x-ui.card-content>
            </x-ui.card>
        @endif

        @if ($transaction->splits->isNotEmpty())
            <x-ui.card>
                <x-ui.card-header title="Split" />
                <x-ui.card-content flush>
                    <ul class="divide-y divide-border">
                        @foreach ($transaction->splits as $split)
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                                <span>{{ $split->category?->name ?: ($split->notes ?: 'Part') }}</span>
                                <x-finance.money :amount="$split->amount" />
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card-content>
            </x-ui.card>
        @endif

        <x-ui.card>
            <x-ui.card-content class="text-xs text-muted-foreground">
                <p>Recorded {{ $transaction->created_at->diffForHumans() }}</p>
                @if ($transaction->updated_at->ne($transaction->created_at))
                    <p class="mt-0.5">Last edited {{ $transaction->updated_at->diffForHumans() }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    </div>
</div>

@unless ($transaction->trashed())
    <x-ui.dialog id="void-transaction" title="Void this entry?"
        description="It stops affecting balances but stays on record, so the history stays complete.">
        <form method="POST" action="{{ route('transactions.void', $transaction) }}">
            @csrf
            <div class="space-y-4 px-5 py-4">
                @if ($counterpart)
                    <x-ui.alert variant="warning">Both sides of this move will be voided together.</x-ui.alert>
                @endif
                <x-ui.input label="Why?" name="void_reason" required
                    placeholder="Entered twice, wrong amount, …" />
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-5 py-3">
                <x-ui.button type="button" variant="ghost" x-on:click="$dispatch('close-dialog', 'void-transaction')">
                    Cancel
                </x-ui.button>
                <x-ui.button type="submit" variant="destructive">Void entry</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
@endunless
@endsection
