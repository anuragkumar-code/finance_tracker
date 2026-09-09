@props(['label' => null, 'name' => null, 'hint' => null, 'error' => null])

@php
    $error = $error ?? ($name ? $errors->first($name) : null);
    $id = $attributes->get('id') ?? $name;
@endphp

<div class="space-y-1.5">
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-foreground">{{ $label }}</label>
    @endif

    <div class="relative">
        <select id="{{ $id }}" @if($name) name="{{ $name }}" @endif
            {{ $attributes->merge([
                'class' => 'h-10 w-full appearance-none rounded-md border bg-card pl-3 pr-9 text-sm '
                    .'text-foreground transition-colors focus:outline-none focus:ring-2 '
                    .'focus:ring-ring/25 focus:border-ring disabled:cursor-not-allowed disabled:opacity-60 '
                    .($error ? 'border-destructive' : 'border-input'),
            ]) }}>
            {{ $slot }}
        </select>
        <x-ui.icon name="chevron-down"
            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
    </div>

    @if ($error)
        <p class="text-xs text-destructive">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-xs text-muted-foreground">{{ $hint }}</p>
    @endif
</div>
