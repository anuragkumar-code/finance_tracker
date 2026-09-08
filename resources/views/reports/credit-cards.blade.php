@extends('layouts.app')

@section('title', 'Card report')
@section('heading', 'Cards')
@section('subheading', $month->format('F Y') . ' — what was charged, and what was paid')

@section('actions')
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm">
            <button class="btn btn-sm btn-outline-secondary">Go</button>
        </form>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')

@if ($rows->isEmpty())
    <div class="card"><div class="card-body empty-state">No cards recorded.</div></div>
@else

{{-- Spec 19F is explicit that purchase volume and bill payments must be shown
     separately, never merged into one "card spending" number. --}}
<div class="card mb-4">
    <div class="card-header">Purchases vs bill payments</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Card</th>
                    <th class="text-end">Charged this month</th>
                    <th class="text-end">Bills paid</th>
                    <th class="text-end">Owed now</th>
                    <th class="text-end">Available</th>
                    <th style="width:12%">Used</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>
                        <a href="{{ route('credit-cards.show', $row->card) }}" class="text-decoration-none fw-medium">
                            {{ $row->card->card_name }}
                        </a>
                        <div class="small text-body-secondary">
                            @if ($row->card->account->owner) {{ $row->card->account->owner->name }} · @endif
                            bills {{ $row->card->statement_day }}, due {{ $row->card->payment_due_day }}
                        </div>
                    </td>
                    <td class="text-end money">
                        <a class="text-decoration-none"
                           href="{{ route('transactions.index', ['start' => $start, 'end' => $end, 'type' => 'expense', 'account_id' => $row->card->account_id]) }}">
                            @inr($row->spending)
                        </a>
                    </td>
                    <td class="text-end money text-body-secondary">@inr($row->payments)</td>
                    <td class="text-end money money-neg">@inr($row->outstanding)</td>
                    <td class="text-end money">@inr($row->available)</td>
                    <td>
                        <div class="progress" style="height:5px;">
                            <div class="progress-bar {{ $row->utilisation >= 70 ? 'bg-danger' : ($row->utilisation >= 40 ? 'bg-warning' : 'bg-success') }}"
                                 style="width: {{ min($row->utilisation, 100) }}%"></div>
                        </div>
                        <div class="small text-body-secondary">{{ $row->utilisation }}%</div>
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot class="border-top">
                <tr>
                    <th>Total</th>
                    <th class="text-end money">
                        @inr($rows->reduce(fn ($c, $r) => bcadd($c, $r->spending, 2), '0.00'))
                    </th>
                    <th class="text-end money">
                        @inr($rows->reduce(fn ($c, $r) => bcadd($c, $r->payments, 2), '0.00'))
                    </th>
                    <th class="text-end money money-neg">
                        @inr($rows->reduce(fn ($c, $r) => bcadd($c, $r->outstanding, 2), '0.00'))
                    </th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Card spending trend</div>
    <div class="card-body">
        <canvas id="cardTrend" height="80"></canvas>
    </div>
</div>

<div class="card">
    <div class="card-body small text-body-secondary">
        <strong class="text-body">Why these two columns are separate.</strong>
        "Charged" is what you bought on the card this month — that is your spending.
        "Bills paid" is money moved from your bank to clear what you already owed; it is not
        spending, and adding the two together would count the same purchases twice.
    </div>
</div>

@endif
@endsection

@push('scripts')
@if ($rows->isNotEmpty())
<script>
new Chart(document.getElementById('cardTrend'), {
    type: 'bar',
    data: {
        labels: @json($series->pluck('label')),
        datasets: [{
            label: 'Charged to cards',
            data: @json($series->pluck('card_spending')->map(fn ($v) => (float) $v)),
            backgroundColor: '#f28e2c',
        }],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, ticks: { callback: v => '₹' + v.toLocaleString('en-IN') } } },
        plugins: { legend: { display: false } },
    },
});
</script>
@endif
@endpush
