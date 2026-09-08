@extends('layouts.app')

@section('title', 'Quick Entry')
@section('heading', 'What happened?')
@section('subheading', 'Record a spend in a few seconds. Only the amount and account are required.')

@section('content')
@if ($accounts->isEmpty())
    <div class="card">
        <div class="card-body empty-state">
            <p class="mb-3">You need at least one account before you can record spending.</p>
            <a href="{{ route('accounts.create') }}" class="btn btn-primary">Add your first account</a>
        </div>
    </div>
@else
<form method="POST" action="{{ route('quick-entry.store') }}" id="quickEntryForm">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">

                    {{-- Amount first: it is the one field always needed. --}}
                    <div class="d-flex align-items-center mb-4">
                        <span class="display-6 text-body-secondary me-2">₹</span>
                        <input type="text" inputmode="decimal" name="amount" id="amount"
                               class="form-control amount-input @error('amount') is-invalid @enderror"
                               placeholder="0" value="{{ old('amount') }}" autocomplete="off" autofocus>
                    </div>

                    <div class="mb-4">
                        <label class="stat-label d-block mb-2">Category</label>
                        <div class="chip-group" id="categoryChips">
                            @foreach ($categories as $category)
                                <label class="chip">
                                    <input type="radio" name="category_id" value="{{ $category->id }}"
                                           data-name="{{ $category->name }}"
                                           @checked(old('category_id') == $category->id)>
                                    <span>{{ $category->name }}</span>
                                </label>
                            @endforeach
                        </div>

                        {{-- Subcategories appear only once a parent is chosen (spec: contextual). --}}
                        <div class="mt-2 d-none" id="subcategoryWrap">
                            <label class="stat-label d-block mb-2">More specifically</label>
                            <div class="chip-group" id="subcategoryChips"></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="stat-label d-block mb-2">Paid using</label>
                        <div class="chip-group">
                            @foreach ($accounts as $account)
                                <label class="chip">
                                    {{-- data-owner lets the form default "Paid by" to
                                         whoever owns the account that was chosen. --}}
                                    <input type="radio" name="account_id" value="{{ $account->id }}"
                                           data-owner="{{ $account->owner_id }}"
                                           @checked(old('account_id') == $account->id)>
                                    <span>
                                        {{ $account->name }}
                                        @if ($account->owner)
                                            <small class="text-body-secondary">· {{ $account->owner->name }}</small>
                                        @endif
                                        @if ($account->isLiability())
                                            <span class="badge text-bg-warning ms-1">card</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('account_id')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="stat-label d-block mb-2">Where</label>
                        <input type="text" name="merchant_name" id="merchantName"
                               class="form-control" list="merchantList" autocomplete="off"
                               placeholder="Amazon, Big Bazaar, ..." value="{{ old('merchant_name') }}">
                        <input type="hidden" name="merchant_id" id="merchantId" value="{{ old('merchant_id') }}">
                        <datalist id="merchantList">
                            @foreach ($merchants as $merchant)
                                <option value="{{ $merchant->name }}" data-id="{{ $merchant->id }}"></option>
                            @endforeach
                        </datalist>
                        <div class="form-text" id="merchantHint"></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="stat-label d-block mb-2">Paid by</label>
                            <div class="chip-group">
                                @foreach ($payers as $payer)
                                    <label class="chip">
                                        <input type="radio" name="payer_id" value="{{ $payer->id }}"
                                               @checked(old('payer_id') == $payer->id)>
                                        <span>{{ $payer->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="stat-label d-block mb-2">For</label>
                            <div class="chip-group">
                                @foreach ($beneficiaries as $person)
                                    <label class="chip">
                                        <input type="radio" name="beneficiary_id" value="{{ $person->id }}"
                                               @checked(old('beneficiary_id') == $person->id)>
                                        <span>{{ $person->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="transaction_date" class="form-label stat-label">When</label>
                        <input type="date" name="transaction_date" id="transaction_date" class="form-control"
                               value="{{ old('transaction_date', now()->toDateString()) }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label stat-label">Was it planned?</label>
                        <div class="chip-group">
                            @foreach ($plannedStatuses as $status)
                                <label class="chip">
                                    <input type="radio" name="planned_status" value="{{ $status->value }}"
                                           @checked(old('planned_status') === $status->value)>
                                    <span>{{ $status->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="purpose" class="form-label stat-label">Purpose</label>
                        <select name="purpose" id="purpose" class="form-select">
                            <option value="">—</option>
                            @foreach ($purposes as $purpose)
                                <option value="{{ $purpose->value }}" @selected(old('purpose') === $purpose->value)>
                                    {{ $purpose->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label stat-label">Note</label>
                        <input type="text" name="description" id="description" class="form-control"
                               placeholder="Optional" value="{{ old('description') }}">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Save</button>
                </div>
            </div>

            <div class="card">
                <div class="card-body small text-body-secondary">
                    <div class="fw-semibold text-body mb-1">Spending on a credit card?</div>
                    Choose the card as the account. The spend is recorded today and the card's balance goes up —
                    paying the bill later is a separate step that will not be counted as spending again.
                </div>
            </div>
        </div>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
$(function () {
    // Subcategories are driven by the chosen parent, so the form only ever
    // shows choices that actually apply.
    const subcategories = @json($categories->mapWithKeys(fn ($c) => [
        $c->id => $c->children->map(fn ($child) => ['id' => $child->id, 'name' => $child->name])->values()
    ]));

    const oldSubcategory = @json(old('subcategory_id'));

    function renderSubcategories(parentId) {
        const children = subcategories[parentId] || [];
        const $wrap = $('#subcategoryWrap');
        const $chips = $('#subcategoryChips').empty();

        if (!children.length) {
            $wrap.addClass('d-none');
            return;
        }

        children.forEach(function (child) {
            const checked = String(oldSubcategory) === String(child.id) ? 'checked' : '';
            $chips.append(
                '<label class="chip"><input type="radio" name="subcategory_id" value="' + child.id + '" ' + checked + '>' +
                '<span>' + $('<div>').text(child.name).html() + '</span></label>'
            );
        });

        $wrap.removeClass('d-none');
    }

    $('#categoryChips').on('change', 'input[name="category_id"]', function () {
        renderSubcategories($(this).val());
    });

    // Choosing an account suggests who paid: money out of Khushboo's account was
    // almost always paid by Khushboo. Only fills a blank — never overrides a
    // choice already made, since one partner can pay from a joint account.
    $('input[name="account_id"]').on('change', function () {
        const ownerId = $(this).data('owner');

        if (ownerId && !$('input[name="payer_id"]:checked').length) {
            $('input[name="payer_id"][value="' + ownerId + '"]').prop('checked', true);
        }
    });

    const initialCategory = $('input[name="category_id"]:checked').val();
    if (initialCategory) renderSubcategories(initialCategory);

    // Merchant memory: typing a known merchant pre-fills what it usually is.
    const merchantsByName = {};
    @foreach ($merchants as $merchant)
        merchantsByName[@json(strtolower($merchant->name))] = {{ $merchant->id }};
    @endforeach

    function applyMerchantDefaults(id) {
        $.getJSON('{{ url('merchants') }}/' + id + '/defaults', function (defaults) {
            const applied = [];

            if (defaults.category_id && !$('input[name="category_id"]:checked').length) {
                $('input[name="category_id"][value="' + defaults.category_id + '"]')
                    .prop('checked', true).trigger('change');
                applied.push('category');
            }
            if (defaults.subcategory_id) {
                $('input[name="subcategory_id"][value="' + defaults.subcategory_id + '"]').prop('checked', true);
            }
            if (defaults.account_id && !$('input[name="account_id"]:checked').length) {
                $('input[name="account_id"][value="' + defaults.account_id + '"]').prop('checked', true);
                applied.push('account');
            }
            if (defaults.payer_id && !$('input[name="payer_id"]:checked').length) {
                $('input[name="payer_id"][value="' + defaults.payer_id + '"]').prop('checked', true);
                applied.push('payer');
            }
            if (defaults.beneficiary_id && !$('input[name="beneficiary_id"]:checked').length) {
                $('input[name="beneficiary_id"][value="' + defaults.beneficiary_id + '"]').prop('checked', true);
                applied.push('who it was for');
            }

            $('#merchantHint').text(
                applied.length ? 'Filled in ' + applied.join(', ') + ' from last time — change anything that is different.' : ''
            );
        });
    }

    $('#merchantName').on('change blur', function () {
        const name = $(this).val().trim().toLowerCase();
        const id = merchantsByName[name];

        $('#merchantId').val(id || '');
        $('#merchantHint').text('');

        if (id) applyMerchantDefaults(id);
    });

    // Guard against a double-submit creating the same spend twice.
    $('#quickEntryForm').on('submit', function () {
        $(this).find('button[type="submit"]').prop('disabled', true).text('Saving…');
    });
});
</script>
@endpush
