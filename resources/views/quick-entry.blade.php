@extends('layouts.app')

@section('title', 'Quick Entry')
@section('heading', 'What did you spend?')
@section('subheading', 'The amount and the account are all that is needed — everything else is optional.')

@section('content')
@php
    $allAccounts = $bankAccounts->concat($cardAccounts);

    $searchable = fn ($account) => strtolower(
        $account->name.' '.($account->owner?->name ?? '').' '.$account->type->label()
    );

    $accountGroups = collect([
        ['label' => 'Recently used', 'items' => $recentAccounts],
        ['label' => 'Bank & cash', 'items' => $bankAccounts],
        ['label' => 'Credit cards', 'items' => $cardAccounts],
    ])
        ->reject(fn ($group) => $group['items']->isEmpty())
        ->map(fn ($group) => $group + [
            'haystacks' => $group['items']->map($searchable)->values()->toJson(),
        ]);
@endphp

@if ($allAccounts->isEmpty())
    <x-ui.card>
        <x-ui.empty-state icon="wallet" title="No accounts yet"
            description="You need at least one account before you can record spending.">
            <x-ui.button :href="route('accounts.create')" icon="plus">Add your first account</x-ui.button>
        </x-ui.empty-state>
    </x-ui.card>
@else
<form method="POST" action="{{ route('quick-entry.store') }}"
      x-data="{ saving: false }" x-on:submit="saving = true"
      class="grid gap-4 lg:grid-cols-12">
    @csrf

    <div class="space-y-4 lg:col-span-8">

        {{-- The amount dominates: it is the one thing the user always knows,
             and it should be typeable the instant the page loads. --}}
        <x-ui.card>
            <x-ui.card-content class="!py-6">
                <label for="amount" class="text-xs font-medium text-muted-foreground">Amount</label>
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-3xl font-medium text-muted-foreground">₹</span>
                    <input type="text" inputmode="decimal" name="amount" id="amount"
                           value="{{ old('amount') }}" placeholder="0" autocomplete="off" autofocus
                           class="w-full border-0 bg-transparent p-0 text-4xl font-semibold tabular
                                  tracking-tight text-foreground placeholder:text-muted-foreground/40
                                  focus:outline-none focus:ring-0">
                </div>
                @error('amount')
                    <p class="mt-2 text-sm text-destructive">{{ $message }}</p>
                @enderror
            </x-ui.card-content>
        </x-ui.card>

        {{-- The screen this page lives or dies by. With seventeen accounts a
             flat list is unusable, so they are grouped, the recently used are
             surfaced first, and a filter box handles the rest. --}}
        <x-ui.card x-data="{ filter: '' }">
            <x-ui.card-header title="Where did the money come from?">
                <x-slot:action>
                    <div class="relative">
                        <x-ui.icon name="search"
                            class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <input type="search" x-model="filter" placeholder="Filter"
                               aria-label="Filter accounts"
                               class="h-8 w-36 rounded-md border border-input bg-card pl-8 pr-2 text-sm
                                      focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
                    </div>
                </x-slot:action>
            </x-ui.card-header>

            <x-ui.card-content class="space-y-4">
                @error('account_id')
                    <p class="text-sm text-destructive">{{ $message }}</p>
                @enderror

                @foreach ($accountGroups as $group)
                    {{-- The heading hides when the filter excludes every account
                         inside it, so no empty section is left behind. --}}
                    <div x-show="filter === '' || {{ $group['haystacks'] }}.some(h => h.includes(filter.toLowerCase()))">
                        <p class="mb-2 text-xs font-medium text-muted-foreground">{{ $group['label'] }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($group['items'] as $account)
                                @include('partials._account-chip', [
                                    'account' => $account,
                                    'isCard' => $account->isLiability(),
                                ])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-content class="space-y-5">
                <div x-on:merchant-chosen="window.ftApplyMerchantDefaults($event.detail.id)">
                    <x-ui.merchant-picker :merchants="$merchants" :groups="$merchantGroups" />
                    <p class="mt-1.5 text-xs text-muted-foreground" id="merchantHint">
                        Picking a merchant fills in what it usually is — category, account,
                        who paid — from the last time you bought there.
                    </p>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium">What kind of spend?</p>
                    <div class="flex flex-wrap gap-1.5" id="categoryChips">
                        @foreach ($categories as $category)
                            <label class="chip">
                                <input type="radio" name="category_id" value="{{ $category->id }}"
                                       @checked(old('category_id') == $category->id)>
                                <span>{{ $category->name }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-3 hidden" id="subcategoryWrap">
                        <p class="mb-2 text-xs font-medium text-muted-foreground">More specifically</p>
                        <div class="flex flex-wrap gap-1.5" id="subcategoryChips"></div>
                    </div>
                </div>
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-content class="grid gap-5 sm:grid-cols-2">
                <div>
                    <p class="mb-2 text-sm font-medium">
                        Who paid <span class="font-normal text-muted-foreground">· optional</span>
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($payers as $payer)
                            <label class="chip">
                                <input type="radio" name="payer_id" value="{{ $payer->id }}"
                                       @checked(old('payer_id') == $payer->id)>
                                <span>{{ $payer->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="mb-2 text-sm font-medium">
                        Who it was for <span class="font-normal text-muted-foreground">· optional</span>
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($beneficiaries as $person)
                            <label class="chip">
                                <input type="radio" name="beneficiary_id" value="{{ $person->id }}"
                                       @checked(old('beneficiary_id') == $person->id)>
                                <span>{{ $person->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </x-ui.card-content>
        </x-ui.card>
    </div>

    <div class="space-y-4 lg:col-span-4">
        <x-ui.card>
            <x-ui.card-content class="space-y-4">
                <x-ui.input label="When" name="transaction_date" type="date"
                    :value="old('transaction_date', now()->toDateString())" />

                <x-ui.input label="Note" name="description" :value="old('description')"
                    placeholder="What was it for?" hint="Optional" />

                {{-- Disabled on submit: a double tap must not record the spend
                     twice, and the label says what is happening. --}}
                <x-ui.button type="submit" size="lg" class="w-full"
                    x-bind:disabled="saving">
                    <span x-show="!saving">Save transaction</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </x-ui.button>
            </x-ui.card-content>
        </x-ui.card>

        {{-- Reporting detail, folded away. Nothing optional should stand between
             the household and a saved entry. --}}
        <x-ui.card x-data="{ open: {{ old('planned_status') || old('purpose') ? 'true' : 'false' }} }">
            <button type="button" x-on:click="open = !open"
                    class="flex w-full items-center justify-between gap-2 px-5 py-3.5 text-left">
                <span class="text-sm font-medium">Add detail for reports</span>
                <x-ui.icon name="chevron-down" class="size-4 text-muted-foreground transition-transform"
                    x-bind:class="open && 'rotate-180'" />
            </button>

            <div x-show="open" x-cloak class="space-y-4 border-t border-border px-5 py-4">
                <div>
                    <p class="mb-2 text-sm font-medium">Did you expect this?</p>
                    <div class="flex flex-wrap gap-1.5">
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
                    <p class="mb-2 text-sm font-medium">Could you have skipped it?</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($purposes as $purpose)
                            <label class="chip">
                                <input type="radio" name="purpose" value="{{ $purpose->value }}"
                                       @checked(old('purpose') === $purpose->value)>
                                <span>
                                    <span>{{ $purpose->label() }}</span>
                                    <span class="chip-meta">{{ $purpose->hint() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Feeds the needs-versus-wants split in reports. Skip it if unsure.
                    </p>
                </div>
            </div>
        </x-ui.card>

        <x-ui.alert variant="muted" icon="credit-card" title="Paying by credit card?">
            Pick the card above. The spend counts today and the card's balance goes up —
            paying that bill later is a separate step, never counted as spending twice.
        </x-ui.alert>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const subcategories = @json($categories->mapWithKeys(fn ($c) => [
        $c->id => $c->children->map(fn ($child) => ['id' => $child->id, 'name' => $child->name])->values()
    ]));
    const oldSubcategory = @json(old('subcategory_id'));

    const wrap = document.getElementById('subcategoryWrap');
    const chips = document.getElementById('subcategoryChips');

    // Subcategories only appear once a parent is picked, so the form never shows
    // choices that do not apply yet.
    function renderSubcategories(parentId) {
        const children = subcategories[parentId] || [];
        chips.innerHTML = '';

        if (!children.length) { wrap.classList.add('hidden'); return; }

        children.forEach((child) => {
            const label = document.createElement('label');
            label.className = 'chip';

            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'subcategory_id';
            input.value = child.id;
            input.checked = String(oldSubcategory) === String(child.id);

            const span = document.createElement('span');
            span.textContent = child.name;

            label.append(input, span);
            chips.append(label);
        });

        wrap.classList.remove('hidden');
    }

    document.getElementById('categoryChips').addEventListener('change', (e) => {
        if (e.target.name === 'category_id') renderSubcategories(e.target.value);
    });

    const initial = document.querySelector('input[name="category_id"]:checked');
    if (initial) renderSubcategories(initial.value);

    // Choosing an account suggests who paid. Fills a blank only — either partner
    // can pay from a joint account, so it must never overrule a real choice.
    document.addEventListener('change', (e) => {
        if (e.target.name !== 'account_id') return;
        const owner = e.target.dataset.owner;
        if (owner && !document.querySelector('input[name="payer_id"]:checked')) {
            const payer = document.querySelector('input[name="payer_id"][value="' + owner + '"]');
            if (payer) payer.checked = true;
        }
    });

    // Merchant memory: choosing a merchant fills in what it usually is.
    // Exposed on window because the picker is an Alpine component that
    // announces its choice by event rather than reaching into this script.
    const merchantHint = document.getElementById('merchantHint');

    window.ftApplyMerchantDefaults = function applyDefaults(id) {
        fetch('{{ url('merchants') }}/' + id + '/defaults', { headers: { 'Accept': 'application/json' } })
            .then((r) => r.json())
            .then((d) => {
                const applied = [];
                const pick = (name, value) => {
                    if (!value) return false;
                    if (document.querySelector('input[name="' + name + '"]:checked')) return false;
                    const el = document.querySelector('input[name="' + name + '"][value="' + value + '"]');
                    if (!el) return false;
                    el.checked = true;
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    return true;
                };

                if (pick('category_id', d.category_id)) applied.push('category');
                if (d.subcategory_id) {
                    const sub = document.querySelector('input[name="subcategory_id"][value="' + d.subcategory_id + '"]');
                    if (sub) sub.checked = true;
                }
                if (pick('account_id', d.account_id)) applied.push('account');
                if (pick('payer_id', d.payer_id)) applied.push('who paid');
                pick('beneficiary_id', d.beneficiary_id);

                if (applied.length) {
                    merchantHint.textContent = 'Filled in ' + applied.join(', ') +
                        ' from last time — change anything that differs.';
                }
            })
            .catch(() => {});
    };
})();
</script>
@endpush
