@extends('layouts.app')

@section('title', 'Reconcile')
@section('heading', 'Check against reality')
@section('subheading', 'Compare what the app says with what your bank actually shows')

@section('actions')
    <x-ui.button :href="route('export.transactions')" variant="outline" icon="download">Export CSV</x-ui.button>
@endsection

@section('content')

@if ($openGaps->isNotEmpty())
    <div class="mb-4">
        <x-ui.alert variant="warning" :title="$openGaps->count().' account(s) did not match when last checked'">
            A gap almost always means an entry is missing, not that the app is wrong. Look for the
            missing transaction first — adjusting should be the last resort.
        </x-ui.alert>
    </div>
@endif

<div class="grid gap-4 lg:grid-cols-12">
    <div class="space-y-4 lg:col-span-7">

        <x-ui.card>
            <x-ui.card-header title="Where each account stands" />
            <x-ui.card-content flush>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Account</th>
                                <th scope="col" class="num">App says</th>
                                <th scope="col" class="hidden sm:table-cell">Last checked</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('accounts.show', $row->account) }}"
                                       class="hover:underline">{{ $row->account->name }}</a>
                                    <p class="text-xs text-muted-foreground">{{ $row->account->type->label() }}</p>
                                </td>
                                <td class="num">
                                    <x-finance.money :amount="$row->system_balance"
                                        :tone="$row->account->isLiability() ? 'debt' : null" />
                                </td>
                                <td class="hidden sm:table-cell">
                                    @if ($row->last)
                                        <span class="text-sm">{{ $row->last->reconciliation_date->format('d M Y') }}</span>
                                        <p class="text-xs text-muted-foreground">
                                            {{ $row->days_since === 0 ? 'today' : $row->days_since.' days ago' }}
                                        </p>
                                    @else
                                        <span class="text-sm text-muted-foreground">never</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($row->last)
                                        <x-ui.badge :variant="match($row->last->status) {
                                            'reconciled' => 'success',
                                            'discrepancy' => 'warning',
                                            default => 'info',
                                        }">{{ $row->last->statusLabel() }}</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="outline">Not checked</x-ui.badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header title="History" />
            <x-ui.card-content flush>
                @forelse ($recent as $r)
                    <div class="border-b border-border px-5 py-3 last:border-0">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-sm font-medium">{{ $r->account->name }}</span>
                                    <x-ui.badge :variant="match($r->status) {
                                        'reconciled' => 'success',
                                        'discrepancy' => 'warning',
                                        default => 'info',
                                    }">{{ $r->statusLabel() }}</x-ui.badge>
                                </div>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ $r->reconciliation_date->format('d M Y') }} · bank said
                                    {{ \App\Support\Money::inr($r->actual_balance) }}, app said
                                    {{ \App\Support\Money::inr($r->system_balance) }}
                                </p>
                                @if ($r->note)
                                    <p class="mt-1 text-xs">{{ $r->note }}</p>
                                @endif
                            </div>
                            <div class="shrink-0 text-right">
                                @if ($r->matched())
                                    <span class="text-sm text-muted-foreground">matched</span>
                                @else
                                    <x-finance.money :amount="$r->difference" tone="expense" class="text-sm" />
                                @endif

                                @if ($r->needsAttention())
                                    <div class="mt-1">
                                        <x-ui.button variant="outline" size="sm"
                                            x-on:click="$dispatch('open-dialog', 'adjust-{{ $r->id }}')">
                                            Adjust
                                        </x-ui.button>
                                    </div>
                                @elseif ($r->adjustment)
                                    <a href="{{ route('transactions.show', $r->adjustment) }}"
                                       class="mt-1 block text-xs text-muted-foreground hover:text-foreground">
                                        view adjustment
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state icon="scale" title="Nothing checked yet"
                        description="Pick an account, type in the balance your bank shows, and the app will tell you whether it agrees." />
                @endforelse
            </x-ui.card-content>
        </x-ui.card>
    </div>

    <div class="space-y-4 lg:col-span-5">
        <x-ui.card>
            <x-ui.card-header title="Check an account" />
            <form method="POST" action="{{ route('reconciliations.store') }}">
                @csrf
                <x-ui.card-content class="space-y-4">
                    <x-ui.select label="Account" name="account_id" required>
                        <option value="">Choose…</option>
                        @foreach ($rows as $row)
                            <option value="{{ $row->account->id }}">
                                {{ $row->account->name }} (app: {{ \App\Support\Money::inr($row->system_balance) }})
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input label="What your bank shows" name="actual_balance" inputmode="decimal"
                        prefix="₹" :value="old('actual_balance')" required
                        hint="For a credit card, enter what you owe as a positive number." />

                    <x-ui.input label="As of" name="reconciliation_date" type="date"
                        :value="old('reconciliation_date', now()->toDateString())" required />

                    <x-ui.input label="Note" name="note" :value="old('note')" hint="Optional" />

                    <x-ui.button type="submit" class="w-full">Compare</x-ui.button>
                </x-ui.card-content>
            </form>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header title="Backup" />
            <x-ui.card-content class="space-y-3">
                <p class="text-sm text-muted-foreground">
                    Everything stays on this machine. These files are for your own backup, or for
                    opening the ledger in a spreadsheet.
                </p>
                <div class="grid gap-2">
                    <x-ui.button :href="route('export.transactions')" variant="outline" size="sm" icon="file-text">
                        Export all transactions
                    </x-ui.button>
                    <x-ui.button :href="route('export.accounts')" variant="outline" size="sm" icon="file-text">
                        Export account balances
                    </x-ui.button>
                </div>
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.alert variant="muted" icon="lock" title="Why the app will not just fix itself">
            When the numbers disagree, it records the gap instead of quietly changing its own
            balance. A balance you can trace back to real entries is worth more than one that always
            looks right. Adjust only after you have looked for the missing entry.
        </x-ui.alert>
    </div>
</div>

@foreach ($recent as $r)
    @continue(! $r->needsAttention())
    <x-ui.dialog id="adjust-{{ $r->id }}" :title="'Adjust '.$r->account->name.'?'">
        <form method="POST" action="{{ route('reconciliations.adjust', $r) }}">
            @csrf
            <div class="space-y-4 px-5 py-4">
                <p class="text-sm text-muted-foreground">
                    This posts a visible entry of
                    <strong class="text-foreground tabular">{{ \App\Support\Money::inr($r->difference) }}</strong>
                    so the app matches your bank. It appears in the account history like any other
                    transaction, and is not counted as spending.
                </p>

                <x-ui.alert variant="warning">
                    Before adjusting: is there an entry you forgot to record? That is the usual
                    cause, and recording it is better than papering over it.
                </x-ui.alert>

                <x-ui.input label="Why do they differ?" name="reason" required
                    placeholder="Bank charge I had not recorded" />
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-5 py-3">
                <x-ui.button type="button" variant="ghost"
                    x-on:click="$dispatch('close-dialog', 'adjust-{{ $r->id }}')">Cancel</x-ui.button>
                <x-ui.button type="submit">Post adjustment</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
@endforeach
@endsection
