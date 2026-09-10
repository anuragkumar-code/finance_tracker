{{--
    One spending cut: horizontal bars with the amount and share, every row
    linking into the transaction list filtered the same way.

    Spec section 27 asks that any number can be clicked and traced, so no figure
    here is a dead end. Bars rather than another donut: for ranked comparisons
    length is far easier to read than angle.

    @param string $title
    @param \Illuminate\Support\Collection $rows  objects with ->label, ->amount and ->key/->category_id
    @param string $total       for each row's share
    @param string $filterKey   which transaction filter this cut maps to
    @param array  $baseFilters date range and so on
    @param ?string $empty
--}}
@php
    $rowKey = fn ($row) => property_exists($row, 'category_id') ? $row->category_id : ($row->key ?? null);
    $max = $rows->max('amount') ?: '1';
@endphp

<x-ui.card class="h-full">
    <x-ui.card-header :title="$title" />
    <x-ui.card-content flush>
        @if ($rows->isEmpty())
            <x-ui.empty-state icon="chart-line" title="Nothing here yet"
                :description="$empty ?? 'Nothing recorded for this period.'" />
        @else
            <ul class="divide-y divide-border">
                @foreach ($rows as $row)
                    @php
                        $share = bccomp($total, '0', 2) === 1 ? round($row->amount / $total * 100) : 0;
                        $width = bccomp($max, '0', 2) === 1 ? round($row->amount / $max * 100) : 0;
                    @endphp
                    <li>
                        <a href="{{ route('transactions.index', array_merge($baseFilters, [
                               'type' => 'expense',
                               $filterKey => $rowKey($row),
                           ])) }}"
                           class="block px-5 py-2.5 transition-colors hover:bg-muted/60">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="min-w-0 truncate text-sm">
                                    {{ $row->label }}
                                    @if (! empty($row->parent))
                                        <span class="text-xs text-muted-foreground">· {{ $row->parent }}</span>
                                    @endif
                                </span>
                                <span class="flex shrink-0 items-baseline gap-2">
                                    <span class="text-xs text-muted-foreground tabular">{{ $share }}%</span>
                                    <x-finance.money :amount="$row->amount" class="text-sm" />
                                </span>
                            </div>
                            <div class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-primary/60" style="width: {{ $width }}%"></div>
                            </div>
                            @if (! empty($row->count))
                                <p class="mt-1 text-xs text-muted-foreground">{{ $row->count }} entries</p>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card-content>
</x-ui.card>
