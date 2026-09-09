@props(['title' => null, 'description' => null])

<div {{ $attributes->merge(['class' => 'flex items-start justify-between gap-3 px-5 py-4 border-b border-border']) }}>
    <div class="min-w-0">
        @if ($title)
            <h3 class="text-sm font-semibold text-foreground">{{ $title }}</h3>
        @endif
        @if ($description)
            <p class="text-xs text-muted-foreground mt-0.5">{{ $description }}</p>
        @endif
        {{ $slot }}
    </div>
    @isset($action)
        <div class="shrink-0 text-sm">{{ $action }}</div>
    @endisset
</div>
