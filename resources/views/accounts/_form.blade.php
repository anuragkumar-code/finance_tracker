@php($account = $account ?? null)

<div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.input label="Name" name="name" :value="old('name', $account?->name)"
            placeholder="HDFC Bank" required />

        <x-ui.select label="Type" name="type" :disabled="$hasActivity ?? false">
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(old('type', $account?->type?->value) === $type->value)>
                    {{ $type->label() }}
                </option>
            @endforeach
        </x-ui.select>
    </div>

    @if ($hasActivity ?? false)
        {{-- Switching an account between asset and liability would invert every
             entry already posted against it. --}}
        <input type="hidden" name="type" value="{{ $account->type->value }}">
        <x-ui.alert variant="muted">
            This account already has transactions, so its type is locked. Mark it inactive and
            create a new account if it genuinely needs to change.
        </x-ui.alert>
    @endif

    <div>
        <p class="mb-2 text-sm font-medium">Whose account</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach ($owners as $owner)
                <label class="chip">
                    <input type="radio" name="owner_id" value="{{ $owner->id }}"
                           @checked(old('owner_id', $account?->owner_id) == $owner->id)>
                    <span>{{ $owner->name }}</span>
                </label>
            @endforeach
            <label class="chip">
                <input type="radio" name="owner_id" value=""
                       @checked(old('owner_id', $account?->owner_id) === null)>
                <span>Not set</span>
            </label>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.input label="Institution" name="institution"
            :value="old('institution', $account?->institution)" placeholder="HDFC" hint="Optional" />

        <x-ui.input label="Currency" name="currency" maxlength="3"
            :value="old('currency', $account?->currency ?? 'INR')" />

        <x-ui.input label="Balance today" name="opening_balance" inputmode="decimal" prefix="₹"
            :value="old('opening_balance', $account?->opening_balance ?? '0.00')" required
            hint="For a card or loan, enter what you owe as a positive number." />

        <x-ui.input label="As of" name="opening_balance_date" type="date"
            :value="old('opening_balance_date', optional($account?->opening_balance_date)->toDateString() ?? now()->toDateString())"
            required
            hint="Your starting position. Entries dated before this are history and will not move this balance." />
    </div>

    <x-ui.textarea label="Notes" name="notes" rows="2" hint="Optional">{{ old('notes', $account?->notes) }}</x-ui.textarea>

    <div class="space-y-3 border-t border-border pt-4">
        {{-- An emergency fund is real money that must never read as spendable. --}}
        <x-ui.checkbox name="is_set_aside" :checked="old('is_set_aside', $account?->is_set_aside)"
            label="Set aside — don't count this as money we can spend"
            hint="For an emergency fund or anything ring-fenced. It is left out of every total, report and chart, but stays reachable on the Accounts page so you can transfer into it." />

        <x-ui.input label="What it is for" name="set_aside_reason"
            :value="old('set_aside_reason', $account?->set_aside_reason)"
            placeholder="Emergency fund" hint="Optional" />

        @if ($account)
            <x-ui.checkbox name="is_active" :checked="old('is_active', $account->is_active)" label="Active" />
        @endif
    </div>
</div>
