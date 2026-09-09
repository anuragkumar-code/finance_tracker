@props(['label', 'value', 'hint' => null, 'tone' => 'default', 'icon' => null])

@php
    $tones = [
        'default' => 'text-foreground',
        'income' => 'text-income',
        'expense' => 'text-expense',
        'debt' => 'text-debt',
        'muted' => 'text-muted-foreground',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-card px-5 py-4 shadow-xs']) }}>
    <div class="flex items-center justify-between gap-2">
        <p class="text-xs font-medium text-muted-foreground">{{ $label }}</p>
        @if ($icon)<x-ui.icon :name="$icon" class="size-4 text-muted-foreground" />@endif
    </div>
    <p class="mt-1.5 text-2xl font-semibold tracking-tight tabular {{ $tones[$tone] ?? $tones['default'] }}">
        {{ $value }}
    </p>
    @if ($hint)
        <p class="mt-1 text-xs text-muted-foreground">{{ $hint }}</p>
    @endif
</div>
