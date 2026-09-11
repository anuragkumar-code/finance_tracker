@props([
    'merchants' => [],
    'groups' => [],
    'selected' => null,
    'label' => 'Where did you buy it?',
])

{{--
    Merchant picker.

    A plain <select> of every shop the household has ever used gets unusable
    fast, and a bare text box meant the same place got typed three ways. This is
    a select that knows its master list: merchants sit under their group, the
    list filters as you type, and a name that is genuinely new can still be
    added inline rather than sending someone off to Settings mid-entry.

    Three fields go to the server. merchant_id when an existing merchant was
    picked; merchant_name plus merchant_group_id when a new one was typed. The
    controller takes the id if it has one.
--}}
@php
    $options = collect($merchants)->map(fn ($merchant) => [
        'id' => $merchant->id,
        'name' => $merchant->name,
        'group' => $merchant->group?->name ?? 'Ungrouped',
        'channel' => $merchant->channel?->label(),
    ])->values();

    // Group order comes from the master, so the picker lists families in the
    // order the household arranged them rather than alphabetically. Anything
    // not yet filed collects at the bottom under Ungrouped.
    $order = collect($groups)->pluck('name')->push('Ungrouped')->values();

    $groupOptions = collect($groups)->map(fn ($group) => [
        'id' => $group->id,
        'name' => $group->name,
    ])->values();
@endphp

<div x-data="ftMerchantPicker(@js($options), @js($order), @js($selected))"
     x-on:keydown.escape.stop="close()"
     class="relative">

    <label :for="$id('merchant-trigger')" class="block text-sm font-medium">{{ $label }}</label>

    <input type="hidden" name="merchant_id" :value="chosen ? chosen.id : ''">
    <input type="hidden" name="merchant_name" :value="chosen ? '' : newName">
    <input type="hidden" name="merchant_group_id" :value="chosen ? '' : newGroupId">

    <button type="button" :id="$id('merchant-trigger')"
            x-on:click="toggle()"
            :aria-expanded="open"
            aria-haspopup="listbox"
            class="mt-1.5 flex h-10 w-full items-center gap-2 rounded-md border border-input bg-card
                   px-3 text-left text-sm shadow-xs transition-colors hover:bg-muted
                   focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
        <x-ui.icon name="store" class="size-4 shrink-0 text-muted-foreground" />

        <span class="min-w-0 flex-1 truncate" x-show="chosen || newName">
            <span x-text="chosen ? chosen.name : newName"></span>
            <span class="text-xs text-muted-foreground"
                  x-text="chosen ? ' · ' + chosen.group : ' · new'"></span>
        </span>

        <span class="min-w-0 flex-1 truncate text-subtle" x-show="!chosen && !newName">
            Choose a merchant…
        </span>

        <span x-show="chosen || newName" x-on:click.stop="clear()" role="button" tabindex="0"
              x-on:keydown.enter.stop="clear()"
              class="shrink-0 rounded p-0.5 text-subtle hover:bg-muted hover:text-foreground">
            <x-ui.icon name="x" class="size-3.5" />
            <span class="sr-only">Clear the chosen merchant</span>
        </span>

        <x-ui.icon name="chevron-down" class="size-4 shrink-0 text-muted-foreground" />
    </button>

    <div x-show="open" x-cloak
         x-on:click.outside="close()"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="absolute z-30 mt-1.5 w-full overflow-hidden rounded-lg border border-border
                bg-card shadow-lg">

        <div class="flex items-center gap-2 border-b border-border px-3">
            <x-ui.icon name="search" class="size-4 shrink-0 text-subtle" />
            <input x-ref="search" x-model="query" type="text"
                   placeholder="Search merchants…" autocomplete="off"
                   aria-label="Search merchants"
                   x-on:keydown.enter.prevent="pickHighlighted()"
                   x-on:keydown.down.prevent="move(1)"
                   x-on:keydown.up.prevent="move(-1)"
                   class="h-10 w-full bg-transparent text-sm outline-none placeholder:text-subtle">
        </div>

        <div class="scroll-thin max-h-64 overflow-y-auto p-1" x-ref="list">
            <template x-for="group in grouped" :key="group.name">
                <div>
                    <p class="px-2.5 pb-1 pt-2 text-[0.6875rem] font-semibold uppercase
                              tracking-wider text-subtle" x-text="group.name"></p>
                    <template x-for="item in group.items" :key="item.id">
                        <button type="button" x-on:click="pick(item)"
                                x-on:mouseenter="highlight = item.index"
                                :data-index="item.index"
                                class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-sm"
                                :class="highlight === item.index ? 'bg-accent' : ''">
                            <span class="min-w-0 flex-1 truncate" x-text="item.name"></span>
                            <span class="shrink-0 text-[0.6875rem] text-subtle"
                                  x-show="item.channel" x-text="item.channel"></span>
                        </button>
                    </template>
                </div>
            </template>

            {{-- The escape hatch. Without it a select box would make the corner
                 shop impossible to record without a detour through Settings. --}}
            <div x-show="query.trim() !== '' && !exactMatch"
                 class="border-t border-border p-1.5" :class="results.length ? 'mt-1' : ''">
                <button type="button" x-on:click="addNew()"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm
                               hover:bg-accent">
                    <x-ui.icon name="plus" class="size-3.5 shrink-0 text-muted-foreground" />
                    <span class="min-w-0 truncate">
                        Add “<span class="font-medium" x-text="query.trim()"></span>” as a new merchant
                    </span>
                </button>
            </div>

            <p x-show="results.length === 0 && query.trim() === ''"
               class="px-3 py-6 text-center text-sm text-muted-foreground">
                No merchants yet — type a name to add the first one.
            </p>
        </div>
    </div>

    {{-- Only shown once a new name has been entered: which family it belongs to,
         so it lands somewhere sensible instead of in an ungrouped pile. --}}
    <div x-show="!chosen && newName" x-cloak class="mt-2 rounded-lg border border-border bg-muted/40 p-3">
        <label :for="$id('merchant-group')" class="block text-xs font-medium text-muted-foreground">
            What kind of place is <span class="text-foreground" x-text="newName"></span>?
        </label>
        <select :id="$id('merchant-group')" x-model="newGroupId"
                class="mt-1.5 h-9 w-full rounded-md border border-input bg-card px-3 text-sm
                       focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
            <option value="">Leave ungrouped</option>
            @foreach ($groupOptions as $group)
                <option value="{{ $group['id'] }}">{{ $group['name'] }}</option>
            @endforeach
        </select>
    </div>
</div>

@once
@push('scripts')
<script>
    function ftMerchantPicker(merchants, groupOrder, selected) {
        return {
            open: false,
            query: '',
            highlight: 0,
            chosen: selected || null,
            newName: '',
            newGroupId: '',
            merchants: merchants,
            groupOrder: groupOrder,

            toggle() {
                this.open = !this.open;
                if (this.open) {
                    this.$nextTick(() => this.$refs.search.focus());
                }
            },

            close() {
                this.open = false;
            },

            clear() {
                this.chosen = null;
                this.newName = '';
                this.newGroupId = '';
                this.query = '';
            },

            get results() {
                const q = this.query.trim().toLowerCase();

                const matched = q === ''
                    ? this.merchants
                    : this.merchants.filter(function (m) {
                        return (m.name + ' ' + m.group).toLowerCase().includes(q);
                    });

                return matched.map(function (m, index) {
                    return Object.assign({}, m, { index: index });
                });
            },

            // Typing the exact name of a merchant that already exists should
            // offer the existing one, not a duplicate.
            get exactMatch() {
                const q = this.query.trim().toLowerCase();

                return this.merchants.some(function (m) {
                    return m.name.toLowerCase() === q;
                });
            },

            get grouped() {
                const byGroup = {};

                this.results.forEach(function (item) {
                    (byGroup[item.group] = byGroup[item.group] || []).push(item);
                });

                return this.groupOrder
                    .filter(function (name) { return byGroup[name]; })
                    .map(function (name) { return { name: name, items: byGroup[name] }; });
            },

            move(delta) {
                const count = this.results.length;
                if (count === 0) return;

                this.highlight = (this.highlight + delta + count) % count;

                this.$nextTick(() => {
                    const el = this.$refs.list.querySelector('[data-index="' + this.highlight + '"]');
                    if (el) el.scrollIntoView({ block: 'nearest' });
                });
            },

            pickHighlighted() {
                const item = this.results[this.highlight];

                if (item) {
                    this.pick(item);
                } else if (this.query.trim() !== '') {
                    this.addNew();
                }
            },

            pick(item) {
                this.chosen = item;
                this.newName = '';
                this.newGroupId = '';
                this.query = '';
                this.close();

                // Other parts of the form listen for this to pull in the
                // remembered category, account and payer for this merchant.
                this.$dispatch('merchant-chosen', { id: item.id });
            },

            addNew() {
                this.newName = this.query.trim();
                this.chosen = null;
                this.query = '';
                this.close();
            },
        };
    }
</script>
@endpush
@endonce
