@php($account = $account ?? null)

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Name</label>
        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $account?->name) }}" placeholder="HDFC Bank" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="type" class="form-label">Type</label>
        <select name="type" id="type" class="form-select @error('type') is-invalid @enderror"
                @disabled(($hasActivity ?? false))>
            @foreach ($types as $type)
                <option value="{{ $type->value }}"
                        @selected(old('type', $account?->type?->value) === $type->value)>
                    {{ $type->label() }}
                </option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if ($hasActivity ?? false)
            {{-- Changing an account between asset and liability would invert every
                 entry already posted against it. --}}
            <input type="hidden" name="type" value="{{ $account->type->value }}">
            <div class="form-text">
                This account already has transactions, so its type is locked.
                Mark it inactive and create a new account if it needs to change.
            </div>
        @endif
    </div>

    <div class="col-md-6">
        <label for="institution" class="form-label">Institution <span class="text-body-secondary">(optional)</span></label>
        <input type="text" name="institution" id="institution" class="form-control"
               value="{{ old('institution', $account?->institution) }}" placeholder="HDFC">
    </div>

    <div class="col-md-6">
        <label for="currency" class="form-label">Currency</label>
        <input type="text" name="currency" id="currency" class="form-control" maxlength="3"
               value="{{ old('currency', $account?->currency ?? 'INR') }}">
    </div>

    <div class="col-md-6">
        <label for="opening_balance" class="form-label">Balance today</label>
        <div class="input-group">
            <span class="input-group-text">₹</span>
            <input type="text" inputmode="decimal" name="opening_balance" id="opening_balance"
                   class="form-control money @error('opening_balance') is-invalid @enderror"
                   value="{{ old('opening_balance', $account?->opening_balance ?? '0.00') }}" required>
            @error('opening_balance')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-text" id="openingHelp">
            For a credit card or loan, enter what you currently owe as a positive number.
        </div>
    </div>

    <div class="col-md-6">
        <label for="opening_balance_date" class="form-label">As of</label>
        <input type="date" name="opening_balance_date" id="opening_balance_date"
               class="form-control @error('opening_balance_date') is-invalid @enderror"
               value="{{ old('opening_balance_date', optional($account?->opening_balance_date)->toDateString() ?? now()->toDateString()) }}"
               required>
        @error('opening_balance_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">
            Your starting position. Entries dated before this are treated as history and
            will not move this balance.
        </div>
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notes <span class="text-body-secondary">(optional)</span></label>
        <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes', $account?->notes) }}</textarea>
    </div>

    @if ($account)
        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1" class="form-check-input"
                       @checked(old('is_active', $account->is_active))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>
    @endif
</div>
