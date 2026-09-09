@extends('layouts.app')

@section('title', 'Quick Entry')
@section('heading', 'What did you spend?')
@section('subheading', 'Amount and where it came from is all that is needed. Everything else is optional.')

@section('content')
@php
    $allAccounts = $bankAccounts->concat($cardAccounts);
@endphp

@if ($allAccounts->isEmpty())
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

            {{-- Step 1: the amount. Always the first thing anyone knows. --}}
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <span class="display-6 text-body-secondary me-2">₹</span>
                        <input type="text" inputmode="decimal" name="amount" id="amount"
                               class="form-control amount-input @error('amount') is-invalid @enderror"
                               placeholder="0" value="{{ old('amount') }}" autocomplete="off" autofocus>
                    </div>
                    @error('amount')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Step 2: which account. The screen this page lives or dies by —
                 with seventeen accounts, a flat list is unusable, so they are
                 split by kind with the recently used ones surfaced first. --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Where did the money come from?</span>
                    <input type="search" id="accountFilter" class="form-control form-control-sm w-auto"
                           placeholder="Filter accounts…" autocomplete="off" style="max-width:12rem;">
                </div>
                <div class="card-body">
                    @error('account_id')
                        <div class="alert alert-danger py-2 small">{{ $message }}</div>
                    @enderror

                    @if ($recentAccounts->isNotEmpty())
                        <div class="account-group mb-3">
                            <div class="stat-label mb-2">Recently used</div>
                            <div class="chip-group">
                                @foreach ($recentAccounts as $account)
                                    @include('partials._account-chip', ['account' => $account])
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($bankAccounts->isNotEmpty())
                        <div class="account-group mb-3">
                            <div class="stat-label mb-2">Bank &amp; cash</div>
                            <div class="chip-group">
                                @foreach ($bankAccounts as $account)
                                    @include('partials._account-chip', ['account' => $account])
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($cardAccounts->isNotEmpty())
                        <div class="account-group">
                            <div class="stat-label mb-2">Credit cards</div>
                            <div class="chip-group">
                                @foreach ($cardAccounts as $account)
                                    @include('partials._account-chip', ['account' => $account, 'isCard' => true])
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="text-body-secondary small mt-3 d-none" id="noAccountMatch">
                        No account matches that.
                    </div>
                </div>
            </div>

            {{-- Step 3: what it was. --}}
            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="merchantName" class="stat-label d-block mb-2">Where did you buy it?</label>
                        <input type="text" name="merchant_name" id="merchantName"
                               class="form-control" list="merchantList" autocomplete="off"
                               placeholder="Blinkit, Amazon, the shop down the road…"
                               value="{{ old('merchant_name') }}">
                        <input type="hidden" name="merchant_id" id="merchantId" value="{{ old('merchant_id') }}">
                        <datalist id="merchantList">
                            @foreach ($merchants as $merchant)
                                <option value="{{ $merchant->name }}"></option>
                            @endforeach
                        </datalist>
                        <div class="form-text" id="merchantHint">
                            Well-known names like Blinkit or Amazon are sorted into quick commerce
                            and online shopping automatically, so those reports fill themselves in.
                        </div>
                    </div>

                    <div>
                        <label class="stat-label d-block mb-2">What kind of spend?</label>
                        <div class="chip-group" id="categoryChips">
                            @foreach ($categories as $category)
                                <label class="chip">
                                    <input type="radio" name="category_id" value="{{ $category->id }}"
                                           @checked(old('category_id') == $category->id)>
                                    <span>{{ $category->name }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-2 d-none" id="subcategoryWrap">
                            <label class="stat-label d-block mb-2">More specifically</label>
                            <div class="chip-group" id="subcategoryChips"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 4: who. Optional, but one tap each. --}}
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="stat-label d-block mb-2">
                                Who paid <span class="text-body-secondary fw-normal">(optional)</span>
                            </label>
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
                            <label class="stat-label d-block mb-2">
                                Who it was for <span class="text-body-secondary fw-normal">(optional)</span>
                            </label>
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
                        <label for="transaction_date" class="stat-label d-block mb-2">When</label>
                        <input type="date" name="transaction_date" id="transaction_date" class="form-control"
                               value="{{ old('transaction_date', now()->toDateString()) }}">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="stat-label d-block mb-2">
                            Note <span class="text-body-secondary fw-normal">(optional)</span>
                        </label>
                        <input type="text" name="description" id="description" class="form-control"
                               placeholder="What was it for?" value="{{ old('description') }}">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-lg">Save</button>
                </div>
            </div>

            {{-- Pushed below the fold: useful for reports, but never in the way
                 of getting an entry recorded. --}}
            <div class="card mb-3">
                <div class="card-header">
                    <button class="btn btn-link p-0 text-decoration-none" type="button"
                            data-bs-toggle="collapse" data-bs-target="#extraDetail">
                        Add detail for reports
                    </button>
                </div>
                <div class="collapse {{ old('planned_status') || old('purpose') ? 'show' : '' }}" id="extraDetail">
                    <div class="card-body pt-0">
                        <div class="mb-3">
                            <label class="stat-label d-block mb-2">Did you expect this?</label>
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

                        <div>
                            <label class="stat-label d-block mb-2">Could you have skipped it?</label>
                            <div class="chip-group">
                                @foreach ($purposes as $purpose)
                                    <label class="chip">
                                        <input type="radio" name="purpose" value="{{ $purpose->value }}"
                                               @checked(old('purpose') === $purpose->value)>
                                        <span>{{ $purpose->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="form-text">
                                Feeds the "needs vs wants" split in reports. Skip it if you are not sure.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body small text-body-secondary">
                    <div class="fw-semibold text-body mb-1">Paying by credit card?</div>
                    Pick the card above. The spend counts today and the card's balance goes up —
                    paying that bill later is a separate step and is never counted as spending twice.
                </div>
            </div>
        </div>
    </div>
</form>
@endif
@endsection

@push('head')
<style>
    /* Account chips carry more than a name, so they need room to breathe. */
    .account-chip span {
        display: inline-flex;
        flex-direction: column;
        align-items: flex-start;
        line-height: 1.2;
        padding: .4rem .8rem;
    }
    .account-chip .acct-name { font-weight: 500; }
    .account-chip .acct-meta { font-size: .72rem; opacity: .7; }
    .account-chip input:checked + span .acct-meta { opacity: .85; }
    .account-group.is-empty { display: none; }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    // Subcategories only appear once a parent is chosen, so the form never
    // shows choices that do not apply yet.
    const subcategories = @json($categories->mapWithKeys(fn ($c) => [
        $c->id => $c->children->map(fn ($child) => ['id' => $child->id, 'name' => $child->name])->values()
    ]));
    const oldSubcategory = @json(old('subcategory_id'));

    function renderSubcategories(parentId) {
        const children = subcategories[parentId] || [];
        const $wrap = $('#subcategoryWrap');
        const $chips = $('#subcategoryChips').empty();

        if (!children.length) { $wrap.addClass('d-none'); return; }

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

    const initialCategory = $('input[name="category_id"]:checked').val();
    if (initialCategory) renderSubcategories(initialCategory);

    // Filtering by name beats scrolling a wall of chips.
    $('#accountFilter').on('input', function () {
        const term = $(this).val().trim().toLowerCase();
        let visible = 0;

        $('.account-chip').each(function () {
            const match = !term || $(this).data('search').indexOf(term) !== -1;
            $(this).toggleClass('d-none', !match);
            if (match) visible++;
        });

        $('.account-group').each(function () {
            const anyVisible = $(this).find('.account-chip').not('.d-none').length > 0;
            $(this).toggleClass('is-empty', !anyVisible);
        });

        $('#noAccountMatch').toggleClass('d-none', visible > 0);
    });

    // Choosing an account suggests who paid. Only fills a blank — either partner
    // can pay from a joint account, so this must never overrule a choice.
    $(document).on('change', 'input[name="account_id"]', function () {
        const ownerId = $(this).data('owner');
        if (ownerId && !$('input[name="payer_id"]:checked').length) {
            $('input[name="payer_id"][value="' + ownerId + '"]').prop('checked', true);
        }
    });

    // Merchant memory: a known name fills in what it usually is.
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
                $('input[name="account_id"][value="' + defaults.account_id + '"]')
                    .prop('checked', true).trigger('change');
                applied.push('account');
            }
            if (defaults.payer_id && !$('input[name="payer_id"]:checked').length) {
                $('input[name="payer_id"][value="' + defaults.payer_id + '"]').prop('checked', true);
                applied.push('who paid');
            }
            if (defaults.beneficiary_id && !$('input[name="beneficiary_id"]:checked').length) {
                $('input[name="beneficiary_id"][value="' + defaults.beneficiary_id + '"]').prop('checked', true);
            }

            if (applied.length) {
                $('#merchantHint').text('Filled in ' + applied.join(', ') + ' from last time — change anything that differs.');
            }
        });
    }

    $('#merchantName').on('change blur', function () {
        const id = merchantsByName[$(this).val().trim().toLowerCase()];
        $('#merchantId').val(id || '');
        if (id) applyMerchantDefaults(id);
    });

    // A double tap must not record the same spend twice.
    $('#quickEntryForm').on('submit', function () {
        $(this).find('button[type="submit"]').prop('disabled', true).text('Saving…');
    });
});
</script>
@endpush
