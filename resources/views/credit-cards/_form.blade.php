@php($card = $card ?? null)

<div class="row g-3">
    <div class="col-md-6">
        <label for="card_name" class="form-label">Card name</label>
        <input type="text" name="card_name" id="card_name" class="form-control @error('card_name') is-invalid @enderror"
               value="{{ old('card_name', $card?->card_name) }}" placeholder="HDFC Regalia" required>
        @error('card_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="institution" class="form-label">Bank <span class="text-body-secondary">(optional)</span></label>
        <input type="text" name="institution" id="institution" class="form-control"
               value="{{ old('institution', $card?->account?->institution) }}" placeholder="HDFC">
    </div>

    <div class="col-12">
        <label class="form-label">Whose card</label>
        <div class="chip-group">
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

    <div class="col-md-6">
        <label for="credit_limit" class="form-label">Credit limit</label>
        <div class="input-group">
            <span class="input-group-text">₹</span>
            <input type="text" inputmode="decimal" name="credit_limit" id="credit_limit"
                   class="form-control money @error('credit_limit') is-invalid @enderror"
                   value="{{ old('credit_limit', $card?->credit_limit) }}" required>
            @error('credit_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-6">
        <label for="annual_fee" class="form-label">Annual fee <span class="text-body-secondary">(optional)</span></label>
        <div class="input-group">
            <span class="input-group-text">₹</span>
            <input type="text" inputmode="decimal" name="annual_fee" id="annual_fee" class="form-control money"
                   value="{{ old('annual_fee', $card?->annual_fee) }}">
        </div>
    </div>

    <div class="col-md-6">
        <label for="statement_day" class="form-label">Statement day</label>
        <input type="number" min="1" max="31" name="statement_day" id="statement_day"
               class="form-control @error('statement_day') is-invalid @enderror"
               value="{{ old('statement_day', $card?->statement_day) }}" required>
        @error('statement_day')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Day of the month your bill is generated.</div>
    </div>

    <div class="col-md-6">
        <label for="payment_due_day" class="form-label">Payment due day</label>
        <input type="number" min="1" max="31" name="payment_due_day" id="payment_due_day"
               class="form-control @error('payment_due_day') is-invalid @enderror"
               value="{{ old('payment_due_day', $card?->payment_due_day) }}" required>
        @error('payment_due_day')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Usually in the month after the statement.</div>
    </div>

    <div class="col-md-6">
        <label for="opening_balance" class="form-label">Currently owed</label>
        <div class="input-group">
            <span class="input-group-text">₹</span>
            <input type="text" inputmode="decimal" name="opening_balance" id="opening_balance"
                   class="form-control money @error('opening_balance') is-invalid @enderror"
                   value="{{ old('opening_balance', $card?->account?->opening_balance ?? '0.00') }}" required>
            @error('opening_balance')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-text">
            Enter as a positive number. This is your starting position, not a purchase —
            it will not appear in this month's spending.
        </div>
    </div>

    <div class="col-md-6">
        <label for="opening_balance_date" class="form-label">As of</label>
        <input type="date" name="opening_balance_date" id="opening_balance_date" class="form-control"
               value="{{ old('opening_balance_date', optional($card?->account?->opening_balance_date)->toDateString() ?? now()->toDateString()) }}"
               required>
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notes <span class="text-body-secondary">(optional)</span></label>
        <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes', $card?->notes) }}</textarea>
    </div>

    @if ($card)
        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1" class="form-check-input"
                       @checked(old('is_active', $card->is_active))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>
    @endif
</div>
