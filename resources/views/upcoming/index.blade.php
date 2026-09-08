@extends('layouts.app')

@section('title', 'Upcoming')
@section('heading', 'What is coming')
@section('subheading', 'Your bank balance minus what is already committed')

@section('actions')
    <div class="btn-group btn-group-sm">
        @foreach ([7, 30, 60, 90] as $option)
            <a href="{{ route('upcoming.index', ['days' => $option]) }}"
               class="btn btn-outline-secondary {{ $days === $option ? 'active' : '' }}">{{ $option }}d</a>
        @endforeach
    </div>
@endsection

@section('content')

{{-- Spec section 21: the number that matters more than the bank balance. --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-4 align-items-center">
            <div class="col-md-3">
                <div class="stat-label">In bank + cash</div>
                <div class="h3 mb-0 money">@inr($reality['available'])</div>
            </div>
            <div class="col-md-1 text-center d-none d-md-block">
                <span class="h4 text-body-secondary">−</span>
            </div>
            <div class="col-md-3">
                <div class="stat-label">Committed ({{ $days }} days)</div>
                <div class="h3 mb-0 money money-neg">@inr($reality['committed'])</div>
                @if (bccomp($reality['estimated_portion'], '0', 2) === 1)
                    <div class="small text-body-secondary">
                        includes @inr($reality['estimated_portion']) estimated
                    </div>
                @endif
            </div>
            <div class="col-md-1 text-center d-none d-md-block">
                <span class="h4 text-body-secondary">=</span>
            </div>
            <div class="col-md-4">
                <div class="stat-label">Realistically available</div>
                <div class="h2 mb-0 money {{ $reality['is_negative'] ? 'money-neg' : 'money-pos' }}">
                    @inr($reality['realistic'])
                </div>
                @if ($reality['is_negative'])
                    <div class="small text-danger">
                        Commitments over the next {{ $days }} days exceed what you hold.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if ($overdue->isNotEmpty())
    <div class="alert alert-warning">
        <strong>{{ $overdue->count() }} commitment(s) are past due and not yet recorded.</strong>
        <div class="small mt-1">
            @foreach ($overdue as $item)
                {{ $item->label }} ({{ $item->due_date->format('d M') }})@if(! $loop->last), @endif
            @endforeach
        </div>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Next {{ $days }} days</span>
                <span class="small text-body-secondary">
                    next 7 days: <span class="money">@inr($next7)</span>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Due</th>
                            <th>What</th>
                            <th>Type</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($obligations as $item)
                        <tr>
                            <td class="text-nowrap">
                                {{ $item->due_date->format('d M') }}
                                <div class="small text-body-secondary">
                                    {{ $item->due_date->diffForHumans(['short' => true]) }}
                                </div>
                            </td>
                            <td>
                                <a href="{{ $item->url }}" class="text-decoration-none">{{ $item->label }}</a>
                                @if ($item->detail)
                                    <div class="small text-body-secondary">{{ $item->detail }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge text-bg-light border">
                                    {{ match ($item->source) {
                                        'loan' => 'EMI',
                                        'credit_card' => 'Card bill',
                                        default => 'Recurring',
                                    } }}
                                </span>
                                @if ($item->is_estimated)
                                    {{-- Forecasts are labelled so they are never mistaken
                                         for a fixed, known amount. --}}
                                    <span class="badge text-bg-secondary">estimate</span>
                                @endif
                            </td>
                            <td class="text-end money">@inr($item->amount)</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-state">
                                Nothing committed in the next {{ $days }} days.
                                <div class="small mt-1">
                                    Add <a href="{{ route('loans.index') }}">loans</a> or
                                    <a href="{{ route('recurring.index') }}">recurring commitments</a>
                                    to see them here.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                    @if ($obligations->isNotEmpty())
                        <tfoot class="border-top">
                            <tr>
                                <th colspan="3">Total committed</th>
                                <th class="text-end money">@inr($reality['committed'])</th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">By type</div>
            <div class="card-body p-0">
                @php($grouped = $obligations->groupBy('source'))
                @if ($grouped->isEmpty())
                    <div class="empty-state">Nothing to break down.</div>
                @else
                    <table class="table table-sm mb-0">
                        <tbody>
                        @foreach ($grouped as $source => $items)
                            <tr>
                                <td>
                                    {{ match ($source) {
                                        'loan' => 'Loan EMIs',
                                        'credit_card' => 'Card bills',
                                        default => 'Recurring bills',
                                    } }}
                                    <div class="small text-body-secondary">{{ $items->count() }} item(s)</div>
                                </td>
                                <td class="text-end money">
                                    @inr($items->reduce(fn ($c, $i) => bcadd($c, $i->amount, 2), '0.00'))
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body small text-body-secondary">
                <div class="fw-semibold text-body mb-1">Why this differs from your bank balance</div>
                Your bank shows what you hold today. This shows what is left once money you have
                already committed goes out. Amounts marked <em>estimate</em> are forecasts from your
                recurring commitments and may change — EMIs and issued card bills are fixed.
            </div>
        </div>
    </div>
</div>

@endsection
