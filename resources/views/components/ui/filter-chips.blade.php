@props(['chips' => [], 'clearUrl' => null])

{{--
    Active filters, shown as removable pills.

    A form full of selects tells you what you *can* filter by; it does not tell
    you what is currently applied — especially once the advanced panel is
    collapsed again. Every applied filter appears here with its own ✕, so
    narrowing a list is always reversible one step at a time rather than only
    by clearing everything.

    Each chip is a link back to this same URL minus that one parameter, so no
    JavaScript is involved and the result is a normal, shareable page.
--}}
@php
    $chips = collect($chips)->filter(fn ($chip) => filled($chip['value'] ?? null))->values();
@endphp

@if ($chips->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1.5']) }}>
        <span class="inline-flex items-center gap-1 text-xs text-muted-foreground">
            <x-ui.icon name="filter" class="size-3" />
            Filtered by
        </span>

        @foreach ($chips as $chip)
            <a href="{{ request()->fullUrlWithQuery(collect($chip['keys'] ?? [$chip['key']])->mapWithKeys(fn ($k) => [$k => null])->all() + ['page' => null]) }}"
               class="group inline-flex items-center gap-1 rounded-full border border-border bg-card
                      py-0.5 pl-2 pr-1 text-xs shadow-xs transition-colors hover:border-input hover:bg-muted">
                <span class="text-muted-foreground">{{ $chip['label'] }}:</span>
                <span class="font-medium">{{ $chip['value'] }}</span>
                <span class="flex size-3.5 items-center justify-center rounded-full text-subtle
                             group-hover:bg-muted-foreground/15 group-hover:text-foreground">
                    <x-ui.icon name="x" class="size-2.5" />
                </span>
                <span class="sr-only">Remove this filter</span>
            </a>
        @endforeach

        @if ($chips->count() > 1 && $clearUrl)
            <a href="{{ $clearUrl }}"
               class="ml-0.5 text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline">
                Clear all
            </a>
        @endif
    </div>
@endif
