{{--
    Flash messages as toasts. They announce and then get out of the way, rather
    than pushing the whole page down the way the old alert banners did.

    Validation errors are deliberately NOT shown here — those belong against
    their own fields (spec section 33). Only session flashes surface as toasts.
--}}
@php
    $toasts = collect([
        ['key' => 'status', 'icon' => 'check-circle', 'tone' => 'text-success'],
        ['key' => 'warning', 'icon' => 'alert-triangle', 'tone' => 'text-[oklch(0.48_0.11_70)]'],
        ['key' => 'error', 'icon' => 'alert-triangle', 'tone' => 'text-destructive'],
    ])->filter(fn ($t) => session()->has($t['key']));
@endphp

@if ($toasts->isNotEmpty())
    <div class="fixed bottom-4 right-4 z-50 flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2"
         aria-live="polite">
        @foreach ($toasts as $toast)
            <div x-data="{ show: false }"
                 x-init="$nextTick(() => show = true); setTimeout(() => show = false, 8000)"
                 x-show="show" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-end="opacity-0 translate-y-1"
                 class="flex items-start gap-3 rounded-lg border border-border bg-card px-4 py-3 shadow-lg">
                <x-ui.icon :name="$toast['icon']" class="mt-0.5 size-4 shrink-0 {{ $toast['tone'] }}" />
                <p class="min-w-0 flex-1 text-sm text-foreground">{{ session($toast['key']) }}</p>
                <button type="button" x-on:click="show = false"
                        class="shrink-0 rounded p-0.5 text-muted-foreground hover:text-foreground">
                    <x-ui.icon name="x" class="size-3.5" />
                    <span class="sr-only">Dismiss</span>
                </button>
            </div>
        @endforeach
    </div>
@endif
