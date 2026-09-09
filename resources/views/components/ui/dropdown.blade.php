@props(['align' => 'right', 'width' => 'w-48'])

<div x-data="{ open: false }" x-on:keydown.escape.stop="open = false"
     {{ $attributes->merge(['class' => 'relative inline-block']) }}>
    <div x-on:click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true">
        {{ $trigger }}
    </div>

    <div x-show="open" x-on:click.outside="open = false" x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="absolute z-40 mt-1.5 {{ $width }} origin-top rounded-lg border border-border bg-card p-1 shadow-lg {{ $align === 'right' ? 'right-0' : 'left-0' }}">
        {{ $slot }}
    </div>
</div>
