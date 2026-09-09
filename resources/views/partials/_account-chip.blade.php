{{--
    One selectable account in Quick Entry.

    Name on top, owner and kind underneath — with two "BOB Bank" accounts in the
    household, the owner is what tells them apart, so it cannot be an
    afterthought squeezed onto one line.

    data-search powers the filter box; data-owner lets the form suggest a payer.
--}}
@php
    $isCard = $isCard ?? $account->isLiability();
    $ownerName = $account->owner?->name;
@endphp

<label class="chip account-chip"
       data-search="{{ strtolower($account->name.' '.$ownerName.' '.$account->type->label()) }}">
    <input type="radio" name="account_id" value="{{ $account->id }}"
           data-owner="{{ $account->owner_id }}"
           @checked(old('account_id') == $account->id)>
    <span>
        <span class="acct-name">{{ $account->name }}</span>
        <span class="acct-meta">
            {{ $ownerName ?: 'no owner set' }}{{ $isCard ? ' · card' : '' }}
        </span>
    </span>
</label>
