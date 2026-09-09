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
                                <x-ui.badge :variant="match($transaction->planned_status->value) {
                                    'planned' => 'success', 'emergency' => 'destructive', default => 'warning',
                                }">{{ $transaction->planned_status->label() }}</x-ui.badge>
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
                    <p class="text-sm text-muted-foreground">
                        A {{ strtolower($transaction->type->label()) }} between your own accounts, recorded
                        as two linked entries. It is not counted as spending.
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
