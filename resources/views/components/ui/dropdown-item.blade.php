@props(['href' => null, 'icon' => null, 'variant' => 'default'])

@php
    $classes = 'flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-sm transition-colors '
        .($variant === 'destructive'
            ? 'text-destructive hover:bg-destructive/10'
            : 'text-foreground hover:bg-muted');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="size-4 shrink-0 opacity-70" />@endif{{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['class' => $classes, 'type' => 'button']) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="size-4 shrink-0 opacity-70" />@endif{{ $slot }}
    </button>
@endif
