@props(['id', 'title' => null, 'description' => null, 'size' => 'md'])

{{--
    Accessible dialog on Alpine, replacing the Bootstrap modal.

    Escape closes, focus is trapped inside and returned on close, the backdrop
    closes on click, and the page behind cannot scroll. Opened from anywhere by
    dispatching an "open-dialog" window event carrying this dialog's id.
--}}
@php
    $sizes = ['sm' => 'max-w-sm', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl'];
@endphp

<div x-data="{ open: false }"
     x-on:open-dialog.window="if ($event.detail === '{{ $id }}') { open = true }"
     x-on:close-dialog.window="if ($event.detail === '{{ $id }}') { open = false }"
     x-on:keydown.escape.window="open = false"
     x-cloak>
    <div x-show="open" class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4">
        <div x-show="open" x-transition.opacity.duration.150ms
             class="fixed inset-0 bg-[oklch(0.21_0.02_258_/_0.45)]"
             x-on:click="open = false" aria-hidden="true"></div>

        <div x-show="open"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-y-2 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-trap.noscroll="open"
             role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title"
             class="relative max-h-[90vh] w-full {{ $sizes[$size] ?? $sizes['md'] }} overflow-y-auto rounded-t-xl border border-border bg-card shadow-lg sm:rounded-xl">
            <div class="flex items-start justify-between gap-3 border-b border-border px-5 py-4">
                <div class="min-w-0">
                    <h2 id="{{ $id }}-title" class="text-sm font-semibold text-foreground">{{ $title }}</h2>
                    @if ($description)
                        <p class="mt-0.5 text-xs text-muted-foreground">{{ $description }}</p>
                    @endif
                </div>
                <button type="button" x-on:click="open = false"
                        class="shrink-0 rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring">
                    <x-ui.icon name="x" class="size-4" />
                    <span class="sr-only">Close</span>
                </button>
            </div>
            {{ $slot }}
        </div>
    </div>
</div>
