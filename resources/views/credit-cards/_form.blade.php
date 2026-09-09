@php($card = $card ?? null)

<div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.input label="Card name" name="card_name" :value="old('card_name', $card?->card_name)"
            placeholder="HDFC Regalia" required />

        <x-ui.input label="Bank" name="institution"
            :value="old('institution', $card?->account?->institution)" placeholder="HDFC" hint="Optional" />
    </div>

    <div>
        <p class="mb-2 text-sm font-medium">Whose card</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach ($owners as $owner)
                <label class="chip">
                    <input type="radio" name="owner_id" value="{{ $owner->id }}"
                           @checked(old('owner_id', $card?->account?->owner_id) == $owner->id)>
                    <span>{{ $owner->name }}</span>
                </label>
            @endforeach
            <label class="chip">
                <input type="radio" name="owner_id" value=""
                       @checked(old('owner_id', $card?->account?->owner_id) === null)>
                <span>Not set</span>
            </label>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.input label="Credit limit" name="credit_limit" inputmode="decimal" prefix="₹"
            :value="old('credit_limit', $card?->credit_limit)" required />

        <x-ui.input label="Annual fee" name="annual_fee" inputmode="decimal" prefix="₹"
            :value="old('annual_fee', $card?->annual_fee)" hint="Optional" />

        <x-ui.input label="Statement day" name="statement_day" type="number" min="1" max="31"
            :value="old('statement_day', $card?->statement_day)" required
            hint="Day of the month your bill is generated." />

        <x-ui.input label="Payment due day" name="payment_due_day" type="number" min="1" max="31"
            :value="old('payment_due_day', $card?->payment_due_day)" required
            hint="Falls the same month if after the statement day, otherwise the next." />

        <x-ui.input label="Currently owed" name="opening_balance" inputmode="decimal" prefix="₹"
            :value="old('opening_balance', $card?->account?->opening_balance ?? '0.00')" required
            hint="A positive number. This is your starting position, not a purchase — it will not appear in this month's spending." />

        <x-ui.input label="As of" name="opening_balance_date" type="date"
            :value="old('opening_balance_date', optional($card?->account?->opening_balance_date)->toDateString() ?? now()->toDateString())"
            required />
    </div>

    <x-ui.textarea label="Notes" name="notes" rows="2" hint="Optional">{{ old('notes', $card?->notes) }}</x-ui.textarea>

    @if ($card)
        <div class="border-t border-border pt-4">
            <x-ui.checkbox name="is_active" :checked="old('is_active', $card->is_active)" label="Active" />
        </div>
    @endif
</div>
