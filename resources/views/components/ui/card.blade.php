@props(['padded' => true])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-card shadow-xs']) }}>
    {{ $slot }}
</div>
