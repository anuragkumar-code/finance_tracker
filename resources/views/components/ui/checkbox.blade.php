@props(['label' => null, 'name' => null, 'hint' => null, 'checked' => false, 'value' => '1'])

@php($id = $attributes->get('id') ?? $name)

<div class="flex gap-2.5">
    {{-- Paired hidden input so an unchecked box still posts a value. --}}
    @if ($name)<input type="hidden" name="{{ $name }}" value="0">@endif
    <input type="checkbox" id="{{ $id }}" @if($name) name="{{ $name }}" @endif value="{{ $value }}"
        @checked($checked)
        {{ $attributes->merge([
            'class' => 'mt-0.5 size-4 shrink-0 rounded border-input text-primary '
                .'focus:ring-2 focus:ring-ring/25 focus:ring-offset-0',
        ]) }}>
    <div class="min-w-0">
        @if ($label)
            <label for="{{ $id }}" class="block text-sm text-foreground select-none">{{ $label }}</label>
        @endif
        @if ($hint)<p class="text-xs text-muted-foreground mt-0.5">{{ $hint }}</p>@endif
    </div>
</div>
