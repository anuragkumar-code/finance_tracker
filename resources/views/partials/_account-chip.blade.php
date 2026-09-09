{{--
    One selectable account in Quick Entry.

    Name on top, owner and kind underneath — the household has two accounts both
    called "BOB Bank", so the owner is what tells them apart and cannot be
    squeezed onto the end of one line.

    x-show wires into the filter box on the parent card; data-owner lets the form
    suggest a payer from whoever owns the account.
--}}
@php
    $isCard = $isCard ?? $account->isLiability();
    $ownerName = $account->owner?->name;
    $search = strtolower($account->name.' '.$ownerName.' '.$account->type->label());
@endphp

<label class="chip"
       x-show="filter === '' || '{{ $search }}'.includes(filter.toLowerCase())"
       x-bind:hidden="!(filter === '' || '{{ $search }}'.includes(filter.toLowerCase()))">
    <input type="radio" name="account_id" value="{{ $account->id }}"
           data-owner="{{ $account->owner_id }}"
           @checked(old('account_id') == $account->id)>
    <span>
        <span>{{ $account->name }}</span>
        <span class="chip-meta">
            {{ $ownerName ?: 'no owner' }}{{ $isCard ? ' · card' : '' }}
        </span>
    </span>
</label>
