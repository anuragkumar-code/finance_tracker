@props(['items' => []])

{{--
    Command palette (Ctrl/Cmd K).

    Every destination in the app is one keystroke and a few letters away, which
    is what stops a sidebar of sixteen links from being the only way around.
    The list is rendered server-side from the same nav array the sidebar uses
    and filtered in the browser — there is no search endpoint, and nothing here
    touches the network.
--}}

<div x-data="ftPalette(@js($items))"
     x-on:open-palette.window="open()"
     x-on:keydown.window.prevent.cmd.k="open()"
     x-on:keydown.window.prevent.ctrl.k="open()"
     x-show="isOpen"
     x-cloak
     class="fixed inset-0 z-50">

    <div x-show="isOpen" x-transition.opacity.duration.150ms
         x-on:click="close()"
         class="absolute inset-0 bg-[oklch(0.21_0.02_258_/_0.35)] backdrop-blur-[2px]"
         aria-hidden="true"></div>

    <div class="absolute inset-x-0 top-[12vh] mx-auto w-full max-w-xl px-4">
        <div x-show="isOpen"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-[0.98] -translate-y-1"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-trap.noscroll="isOpen"
             x-on:keydown.escape.stop="close()"
             x-on:keydown.down.prevent="move(1)"
             x-on:keydown.up.prevent="move(-1)"
             x-on:keydown.enter.prevent="go()"
             role="dialog" aria-modal="true" aria-label="Search and commands"
             class="overflow-hidden rounded-xl border border-border bg-card shadow-lg">

            <div class="flex items-center gap-2.5 border-b border-border px-4">
                <x-ui.icon name="search" class="size-4 shrink-0 text-subtle" />
                <input x-ref="input" x-model="query" x-on:input="active = 0"
                       type="text" placeholder="Search pages and actions…"
                       aria-label="Search pages and actions"
                       autocomplete="off" spellcheck="false"
                       class="h-12 w-full bg-transparent text-sm outline-none placeholder:text-subtle">
                <kbd class="hidden shrink-0 rounded border border-border bg-muted px-1.5 py-px
                            text-[0.625rem] font-medium text-muted-foreground sm:block">Esc</kbd>
            </div>

            <div class="scroll-thin max-h-[22rem] overflow-y-auto p-1.5" x-ref="list">
                <template x-for="(group, gi) in grouped" :key="group.name">
                    <div :class="gi > 0 ? 'mt-1' : ''">
                        <p class="px-2.5 pb-1 pt-1.5 text-[0.6875rem] font-semibold uppercase
                                  tracking-wider text-subtle" x-text="group.name"></p>
                        <template x-for="item in group.items" :key="item.url + item.label">
                            <a :href="item.url"
                               :data-index="item.index"
                               x-on:mouseenter="active = item.index"
                               :aria-selected="active === item.index"
                               class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm"
                               :class="active === item.index
                                   ? 'bg-accent text-accent-foreground'
                                   : 'text-foreground'">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-md
                                             border border-border bg-muted"
                                      x-html="iconFor(item.icon)"></span>
                                <span class="min-w-0 flex-1 truncate" x-text="item.label"></span>
                                <span x-show="active === item.index"
                                      class="shrink-0 text-[0.6875rem] text-muted-foreground">↵</span>
                            </a>
                        </template>
                    </div>
                </template>

                <div x-show="results.length === 0" class="px-3 py-10 text-center">
                    <p class="text-sm text-muted-foreground">No matches for
                        “<span class="text-foreground" x-text="query"></span>”</p>
                </div>
            </div>

            <div class="flex items-center gap-3 border-t border-border bg-muted/60 px-4 py-2
                        text-[0.6875rem] text-muted-foreground">
                <span class="inline-flex items-center gap-1"><kbd class="font-medium">↑↓</kbd> navigate</span>
                <span class="inline-flex items-center gap-1"><kbd class="font-medium">↵</kbd> open</span>
                <span class="ml-auto inline-flex items-center gap-1">
                    <kbd class="font-medium">Ctrl</kbd><kbd class="font-medium">K</kbd> anywhere
                </span>
            </div>
        </div>
    </div>
</div>

{{-- Icons for the palette rows. The Blade icon component cannot be called from
     inside an Alpine template, so the handful of glyphs the palette can show
     are serialised once here and looked up by name at render time. --}}
@php
    $glyphs = collect($items)->pluck('icon')->unique()->values();
@endphp

@push('scripts')
<script>
    window.ftPaletteIcons = {
        @foreach ($glyphs as $glyph)
        @json($glyph): {!! json_encode((string) view('components.ui.icon', ['name' => $glyph, 'class' => 'size-3.5 text-muted-foreground'])->render()) !!},
        @endforeach
    };

    function ftPalette(items) {
        return {
            isOpen: false,
            query: '',
            active: 0,
            items: items.map((item, index) => ({ ...item, index })),

            open() {
                this.isOpen = true;
                this.query = '';
                this.active = 0;
                this.$nextTick(() => this.$refs.input.focus());
            },

            close() {
                this.isOpen = false;
            },

            get results() {
                const q = this.query.trim().toLowerCase();

                if (q === '') {
                    return this.items.map((item, index) => ({ ...item, index }));
                }

                // Every search term has to appear somewhere in the label or its
                // group, so "rep tr" finds "Trends" under Reports.
                const terms = q.split(/\s+/);

                return this.items
                    .filter((item) => {
                        const hay = (item.label + ' ' + item.group).toLowerCase();
                        return terms.every((t) => hay.includes(t));
                    })
                    .map((item, index) => ({ ...item, index }));
            },

            get grouped() {
                const groups = [];

                this.results.forEach((item) => {
                    let group = groups.find((g) => g.name === item.group);
                    if (!group) {
                        group = { name: item.group, items: [] };
                        groups.push(group);
                    }
                    group.items.push(item);
                });

                return groups;
            },

            move(delta) {
                const count = this.results.length;
                if (count === 0) return;

                this.active = (this.active + delta + count) % count;

                this.$nextTick(() => {
                    const el = this.$refs.list.querySelector('[data-index="' + this.active + '"]');
                    if (el) el.scrollIntoView({ block: 'nearest' });
                });
            },

            go() {
                const target = this.results[this.active];
                if (target) window.location.href = target.url;
            },

            iconFor(name) {
                return window.ftPaletteIcons[name] || '';
            },
        };
    }
</script>
@endpush
