{{--
    One spending cut: a table of amounts with shares, every row linking into the
    transaction list filtered the same way.

    Spec section 27 asks that a user can click a number and see what is behind it,
    so no figure here is a dead end.

    @param string $title
    @param \Illuminate\Support\Collection $rows  objects with ->label, ->amount and ->key/->category_id
    @param string $total       for computing each row's share
    @param string $filterKey   transaction filter this cut maps to
    @param array  $baseFilters date range etc.
    @param ?string $empty      message when there is nothing
--}}
@php
    $rowKey = fn ($row) => property_exists($row, 'category_id') ? $row->category_id : ($row->key ?? null);
@endphp

<div class="card h-100">
    <div class="card-header">{{ $title }}</div>
    <div class="card-body p-0">
        @if ($rows->isEmpty())
            <div class="empty-state">{{ $empty ?? 'Nothing recorded for this period.' }}</div>
        @else
            <table class="table table-sm table-hover mb-0 align-middle">
                <tbody>
                @foreach ($rows as $row)
                    @php($share = bccomp($total, '0', 2) === 1 ? round($row->amount / $total * 100) : 0)
                    <tr>
                        <td>
                            <a class="text-decoration-none"
                               href="{{ route('transactions.index', array_merge($baseFilters, [
                                   'type' => 'expense',
                                   $filterKey => $rowKey($row),
                               ])) }}">
                                {{ $row->label }}
                            </a>
                            @if (! empty($row->parent))
                                <div class="small text-body-secondary">{{ $row->parent }}</div>
                            @endif
                            @if (! empty($row->count))
                                <div class="small text-body-secondary">{{ $row->count }} entries</div>
                            @endif
                        </td>
                        <td style="width:38%">
                            <div class="progress" style="height:5px;">
                                <div class="progress-bar bg-secondary" style="width: {{ $share }}%"></div>
                            </div>
                        </td>
                        <td class="text-end money">@inr($row->amount)</td>
                        <td class="text-end text-body-secondary small" style="width:3.2rem;">{{ $share }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
