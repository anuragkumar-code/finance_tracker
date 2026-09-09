@props(['variant' => 'default'])

{{--
    Badges stay low-saturation on purpose: in a finance UI they sit next to real
    numbers, and a wall of bright pills makes the figures harder to read.
--}}
@php
    $variants = [
        'default' => 'bg-primary/10 text-primary border-primary/15',
        'secondary' => 'bg-muted text-muted-foreground border-border',
        'success' => 'bg-success/10 text-success border-success/20',
        'warning' => 'bg-warning/15 text-[oklch(0.48_0.11_70)] border-warning/25',
        'destructive' => 'bg-destructive/10 text-destructive border-destructive/20',
        'info' => 'bg-info/10 text-info border-info/20',
        'outline' => 'bg-transparent text-muted-foreground border-border',
    ];
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 '
        .'text-[0.6875rem] font-medium leading-4 whitespace-nowrap '
        .($variants[$variant] ?? $variants['default']),
]) }}>{{ $slot }}</span>
