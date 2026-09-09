@props(['variant' => 'info', 'title' => null, 'icon' => null])

@php
    $variants = [
        'info' => ['wrap' => 'bg-info/[0.06] border-info/25', 'icon' => 'text-info', 'default' => 'info'],
        'success' => ['wrap' => 'bg-success/[0.06] border-success/25', 'icon' => 'text-success', 'default' => 'check-circle'],
        'warning' => ['wrap' => 'bg-warning/[0.09] border-warning/30', 'icon' => 'text-[oklch(0.48_0.11_70)]', 'default' => 'alert-triangle'],
        'destructive' => ['wrap' => 'bg-destructive/[0.06] border-destructive/25', 'icon' => 'text-destructive', 'default' => 'alert-triangle'],
        'muted' => ['wrap' => 'bg-muted border-border', 'icon' => 'text-muted-foreground', 'default' => 'info'],
    ];
    $v = $variants[$variant] ?? $variants['info'];
@endphp

<div role="alert" {{ $attributes->merge(['class' => 'flex gap-3 rounded-lg border px-4 py-3 '.$v['wrap']]) }}>
    <x-ui.icon :name="$icon ?? $v['default']" class="size-4 shrink-0 mt-0.5 {{ $v['icon'] }}" />
    <div class="min-w-0 text-sm">
        @if ($title)<p class="font-medium text-foreground">{{ $title }}</p>@endif
        <div class="text-muted-foreground {{ $title ? 'mt-0.5' : '' }}">{{ $slot }}</div>
    </div>
</div>
