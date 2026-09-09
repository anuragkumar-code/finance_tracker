@props(['value' => 0, 'variant' => 'default', 'label' => null])

@php
    $pct = max(0, min(100, (float) $value));
    $bars = [
        'default' => 'bg-primary',
        'success' => 'bg-success',
        'warning' => 'bg-warning',
        'destructive' => 'bg-destructive',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'h-1.5 w-full overflow-hidden rounded-full bg-muted']) }}
     role="progressbar" aria-valuenow="{{ round($pct) }}" aria-valuemin="0" aria-valuemax="100"
     @if($label) aria-label="{{ $label }}" @endif>
    <div class="h-full rounded-full transition-[width] duration-300 {{ $bars[$variant] ?? $bars['default'] }}"
         style="width: {{ $pct }}%"></div>
</div>
