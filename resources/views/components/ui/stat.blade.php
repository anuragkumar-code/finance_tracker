@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'default',
    'icon' => null,
    'delta' => null,
    'deltaLabel' => 'vs last month',
    'deltaGood' => 'down',
    'series' => null,
    'href' => null,
])

{{--
    A figure, what it is, and which way it has been going.

    A bare number tells you nothing about whether it is normal. The comparison
    against last month and the sparkline behind it are what turn "₹48,200" into
    something you can act on, and they cost one line of vertical space.

    `deltaGood` says which direction is welcome for this particular figure —
    spending falling is good, income falling is not — so colour never has to be
    decided at each call site.
--}}
@php
    $tones = [
        'default' => 'text-foreground',
        'income' => 'text-income',
        'expense' => 'text-expense',
        'debt' => 'text-debt',
        'muted' => 'text-muted-foreground',
    ];

    $sparkTone = in_array($tone, ['income', 'expense', 'debt'], true) ? $tone : 'primary';

    // A delta of exactly zero is worth showing as "no change" rather than as a
    // green 0%, which reads as an improvement.
    $hasDelta = $delta !== null && is_numeric($delta);
    $rising = $hasDelta && $delta > 0;
    $flat = $hasDelta && abs($delta) < 0.5;

    $deltaClass = match (true) {
        ! $hasDelta, $flat => 'text-muted-foreground',
        $rising => $deltaGood === 'up' ? 'text-income' : 'text-expense',
        default => $deltaGood === 'up' ? 'text-expense' : 'text-income',
    };

    $tag = $href ? 'a' : 'div';
    $sparkId = 'spark-'.\Illuminate\Support\Str::random(6);
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge([
        'class' => 'group relative block overflow-hidden rounded-xl border border-border bg-card '
            .'px-4 py-3.5 shadow-xs transition-shadow sm:px-5 sm:py-4'
            .($href ? ' hover:shadow-md' : ''),
    ]) }}>

    <div class="flex items-center justify-between gap-2">
        <p class="text-xs font-medium text-muted-foreground">{{ $label }}</p>
        @if ($icon)
            <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-muted">
                <x-ui.icon :name="$icon" class="size-3.5 text-muted-foreground" />
            </span>
        @endif
    </div>

    <p class="mt-2 text-[1.375rem] font-semibold leading-tight tracking-tight tabular sm:text-2xl
              {{ $tones[$tone] ?? $tones['default'] }}">
        {{ $value }}
    </p>

    <div class="mt-1.5 flex min-h-[1.125rem] items-center gap-1.5">
        @if ($hasDelta)
            <span class="inline-flex items-center gap-0.5 text-xs font-medium {{ $deltaClass }}">
                @if (! $flat)
                    <x-ui.icon :name="$rising ? 'arrow-up' : 'arrow-down'" class="size-3" />
                @endif
                {{ $flat ? 'No change' : abs(round($delta)).'%' }}
            </span>
            <span class="truncate text-xs text-muted-foreground">{{ $deltaLabel }}</span>
        @elseif ($hint)
            <span class="truncate text-xs text-muted-foreground">{{ $hint }}</span>
        @endif
    </div>

    @if ($hint && $hasDelta)
        <p class="mt-0.5 truncate text-xs text-muted-foreground">{{ $hint }}</p>
    @endif

    @if ($series && count($series) > 1)
        {{-- Sat behind the text at low opacity rather than given its own row:
             the trend is context for the number, not a second figure. --}}
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-9 opacity-[0.55]">
            <canvas id="{{ $sparkId }}"></canvas>
        </div>
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    window.ftChart && window.ftChart.sparkline(
                        document.getElementById(@json($sparkId)),
                        @json(array_map('floatval', array_values($series))),
                        @json($sparkTone)
                    );
                });
            </script>
        @endpush
    @endif
</{{ $tag }}>
