@props([
    'variant' => 'default',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => null,
])

{{--
    One button, six variants. Bootstrap's .btn classes are deliberately not used
    anywhere in the new design system — mixing the two produces the exact
    "Bootstrap with a new colour scheme" look this redesign exists to avoid.
--}}
@php
    $base = 'inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md '
        .'font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 '
        .'focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50';

    $variants = [
        'default' => 'bg-primary text-primary-foreground hover:bg-primary/90 shadow-xs',
        'secondary' => 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
        'outline' => 'border border-border bg-card text-foreground hover:bg-muted shadow-xs',
        'ghost' => 'text-foreground hover:bg-muted',
        'destructive' => 'bg-destructive text-destructive-foreground hover:bg-destructive/90 shadow-xs',
        'link' => 'text-foreground underline-offset-4 hover:underline',
    ];

    $sizes = [
        'sm' => 'h-8 px-3 text-[0.8125rem]',
        'md' => 'h-9 px-4 text-sm',
        'lg' => 'h-10 px-5 text-sm',
        'icon' => 'size-9 p-0',
    ];

    $classes = trim($base.' '.($variants[$variant] ?? $variants['default']).' '.($sizes[$size] ?? $sizes['md']));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="size-4 shrink-0" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="size-4 shrink-0" />@endif
        {{ $slot }}
    </button>
@endif
