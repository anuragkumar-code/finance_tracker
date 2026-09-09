@props(['icon' => 'inbox', 'title' => null, 'description' => null])

{{-- Never a blank panel: an empty list should say what will appear and how to
     make it appear (spec section 31). --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center text-center px-6 py-12']) }}>
    <div class="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
        <x-ui.icon :name="$icon" class="size-5" />
    </div>
    @if ($title)
        <h3 class="mt-3 text-sm font-semibold text-foreground">{{ $title }}</h3>
    @endif
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-muted-foreground">{{ $description }}</p>
    @endif
    @if (trim($slot))
        <div class="mt-4 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
