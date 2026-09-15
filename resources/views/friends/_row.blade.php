{{-- One friend: their balance, and the three things that can happen to it. --}}
@php
    $person = $row->person;
    $sign = bccomp($row->balance, '0', 2);
    $theyOwe = $sign === 1;
    $weOwe = $sign === -1;
    $absolute = $weOwe ? bcsub('0', $row->balance, 2) : $row->balance;
    $statusText = match ($sign) {
        1 => 'owes you',
        -1 => 'you owe',
        default => 'all square',
    };
    $tone = match ($sign) {
        1 => 'income',
        -1 => 'debt',
        default => 'muted',
    };
    $key = 'friend-'.$person->id;
    $today = now()->toDateString();
@endphp

<li>
<div class="flex flex-wrap items-center gap-3 px-5 py-3.5">
    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-semibold text-muted-foreground">
        {{ mb_strtoupper(mb_substr($person->name, 0, 1)) }}
    </span>

    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium">{{ $person->name }}</p>
        <p class="text-xs text-muted-foreground">
            {{ $person->relationship ?: 'Friend' }}
            @if ($row->account)
                · <a href="{{ route('accounts.show', $row->account) }}" class="hover:text-foreground hover:underline">history</a>
            @endif
        </p>
    </div>

    <div class="text-right">
        @if ($sign !== 0)
            <x-finance.money :amount="$absolute" :tone="$tone" class="text-sm font-semibold" />
        @endif
        <p class="text-xs text-muted-foreground">{{ $statusText }}</p>
    </div>

    <div class="flex w-full shrink-0 justify-end gap-1.5 sm:w-auto">
        @if ($theyOwe)
            <x-ui.button size="sm" icon="download" x-on:click="$dispatch('open-dialog', '{{ $key }}-repay')">Got paid back</x-ui.button>
            <x-ui.button size="sm" variant="ghost" x-on:click="$dispatch('open-dialog', '{{ $key }}-writeoff')">Write off</x-ui.button>
        @elseif ($weOwe)
            <x-ui.button size="sm" x-on:click="$dispatch('open-dialog', '{{ $key }}-payback')">Paid them back</x-ui.button>
        @endif
    </div>
</div>

@if ($theyOwe)
    <x-ui.dialog :id="$key.'-repay'" :title="$person->name.' paid you back'"
        description="Money arriving in your account. It clears what they owe and is not counted as income.">
        <form method="POST" action="{{ route('friends.repayment', $person) }}">
            @csrf
            <div class="space-y-4 px-5 py-4">
                <x-ui.select label="Into" name="account_id" required>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}{{ $account->owner ? ' · '.$account->owner->name : '' }}</option>
                    @endforeach
                </x-ui.select>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Amount" name="amount" inputmode="decimal" prefix="₹" :value="$absolute" required
                        :hint="'They owe '.\App\Support\Money::inr($absolute)" />
                    <x-ui.input label="Date" name="date" type="date" :value="$today" required />
                </div>
                <x-ui.input label="Note" name="note" hint="Optional — e.g. UPI, cash" />
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-5 py-3">
                <x-ui.button type="button" variant="ghost" x-on:click="$dispatch('close-dialog', '{{ $key }}-repay')">Cancel</x-ui.button>
                <x-ui.button type="submit">Record repayment</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>

    <x-ui.dialog :id="$key.'-writeoff'" :title="'Write off what '.$person->name.' owes?'"
        description="For money that is not coming back. It becomes your own spending.">
        <form method="POST" action="{{ route('friends.write-off', $person) }}">
            @csrf
            <div class="space-y-4 px-5 py-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Amount" name="amount" inputmode="decimal" prefix="₹" :value="$absolute" required />
                    <x-ui.input label="Date" name="date" type="date" :value="$today" required />
                </div>
                <x-ui.select label="Count it under" name="category_id">
                    <option value="">Uncategorised</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($holiday && $holiday->id === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select label="Trip or event" name="event_id" hint="Optional">
                    <option value="">None</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}">{{ $event->name }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-5 py-3">
                <x-ui.button type="button" variant="ghost" x-on:click="$dispatch('close-dialog', '{{ $key }}-writeoff')">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="destructive">Write off</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
@endif

@if ($weOwe)
    <x-ui.dialog :id="$key.'-payback'" :title="'You paid '.$person->name.' back'"
        description="Money leaving your account to clear what you owe. Not counted as spending again.">
        <form method="POST" action="{{ route('friends.payback', $person) }}">
            @csrf
            <div class="space-y-4 px-5 py-4">
                <x-ui.select label="From" name="account_id" required>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}{{ $account->owner ? ' · '.$account->owner->name : '' }}</option>
                    @endforeach
                </x-ui.select>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Amount" name="amount" inputmode="decimal" prefix="₹" :value="$absolute" required
                        :hint="'You owe '.\App\Support\Money::inr($absolute)" />
                    <x-ui.input label="Date" name="date" type="date" :value="$today" required />
                </div>
                <x-ui.input label="Note" name="note" hint="Optional" />
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-5 py-3">
                <x-ui.button type="button" variant="ghost" x-on:click="$dispatch('close-dialog', '{{ $key }}-payback')">Cancel</x-ui.button>
                <x-ui.button type="submit">Record payment</x-ui.button>
            </div>
        </form>
    </x-ui.dialog>
@endif
</li>
