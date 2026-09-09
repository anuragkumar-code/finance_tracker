@props(['label' => null, 'name' => null, 'hint' => null, 'prefix' => null, 'error' => null])

@php
    $error = $error ?? ($name ? $errors->first($name) : null);
    $id = $attributes->get('id') ?? $name;
@endphp

<div class="space-y-1.5">
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-foreground">{{ $label }}</label>
    @endif

    <div class="relative">
        @if ($prefix)
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-muted-foreground pointer-events-none">
                {{ $prefix }}
            </span>
        @endif

        <input id="{{ $id }}" @if($name) name="{{ $name }}" @endif
            {{ $attributes->merge([
                'class' => 'h-10 w-full rounded-md border bg-card px-3 text-sm text-foreground '
                    .'transition-colors placeholder:text-muted-foreground '
                    .'focus:outline-none focus:ring-2 focus:ring-ring/25 focus:border-ring '
                    .'disabled:cursor-not-allowed disabled:opacity-60 '
                    .($prefix ? 'pl-7 ' : '')
                    .($error ? 'border-destructive focus:ring-destructive/25 focus:border-destructive' : 'border-input'),
            ]) }}
            @if($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
    </div>

    {{-- Errors sit against the field they belong to, not only in a banner at
         the top of the page (spec section 33). --}}
    @if ($error)
        <p id="{{ $id }}-error" class="text-xs text-destructive">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-xs text-muted-foreground">{{ $hint }}</p>
    @endif
</div>
