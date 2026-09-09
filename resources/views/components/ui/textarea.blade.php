@props(['label' => null, 'name' => null, 'hint' => null, 'rows' => 3])

@php
    $error = $name ? $errors->first($name) : null;
    $id = $attributes->get('id') ?? $name;
@endphp

<div class="space-y-1.5">
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-foreground">{{ $label }}</label>
    @endif
    <textarea id="{{ $id }}" @if($name) name="{{ $name }}" @endif rows="{{ $rows }}"
        {{ $attributes->merge([
            'class' => 'w-full rounded-md border bg-card px-3 py-2 text-sm text-foreground '
                .'transition-colors placeholder:text-muted-foreground focus:outline-none '
                .'focus:ring-2 focus:ring-ring/25 focus:border-ring '
                .($error ? 'border-destructive' : 'border-input'),
        ]) }}>{{ $slot }}</textarea>
    @if ($error)
        <p class="text-xs text-destructive">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-xs text-muted-foreground">{{ $hint }}</p>
    @endif
</div>
