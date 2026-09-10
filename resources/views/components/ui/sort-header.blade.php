@props([
    'column',
    'label',
    'align' => 'left',
    'default' => 'desc',
])

{{--
    A sortable column heading.

    Sorting is a link, not JavaScript: the sorted view has its own URL, so it
    survives a refresh, can be bookmarked, and works with the back button. The
    arrow appears only on the active column — an indicator on every heading is
    noise, since only one can be sorted at a time.
--}}
@php
    $current = request()->query('sort');
    $direction = request()->query('dir', 'desc') === 'asc' ? 'asc' : 'desc';
    $active = $current === $column;

    // Clicking the active column flips it; clicking a fresh one starts at the
    // direction that column is usually wanted in — newest dates, largest sums.
    $next = $active ? ($direction === 'asc' ? 'desc' : 'asc') : $default;

    $url = request()->fullUrlWithQuery(['sort' => $column, 'dir' => $next, 'page' => null]);
@endphp

<th scope="col" @class(['num' => $align === 'right'])>
    <a href="{{ $url }}" class="th-sort {{ $active ? 'text-foreground' : '' }}"
       aria-label="Sort by {{ $label }}, {{ $next === 'asc' ? 'ascending' : 'descending' }}">
        <span>{{ $label }}</span>
        @if ($active)
            <x-ui.icon :name="$direction === 'asc' ? 'arrow-up' : 'arrow-down'" class="size-3" />
        @else
            <x-ui.icon name="chevrons-up-down" class="size-3 opacity-0 transition-opacity group-hover/table:opacity-40" />
        @endif
    </a>
</th>
