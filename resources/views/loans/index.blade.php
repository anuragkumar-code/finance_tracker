@extends('layouts.app')

@section('title', 'Loans')
@section('heading', 'Loans')
@section('subheading', 'EMIs, what is left, and how far along you are')

@section('actions')
    <a href="{{ route('loans.create') }}" class="btn btn-sm btn-primary text-nowrap">+ Add loan</a>
@endsection

@section('content')

@if ($loans->isEmpty())
    <div class="card">
        <div class="card-body empty-state">
            <h2 class="h5">No loans yet</h2>
            <p class="mb-3">
                Add a loan with its EMI, how many months it runs and when it started.
                That is all that is needed — the schedule, what you have paid and what
                is left are worked out from there.
            </p>
            <a href="{{ route('loans.create') }}" class="btn btn-primary">Add your first loan</a>
        </div>
    </div>
@else

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body">
                <div class="stat-label">Still to pay</div>
                <div class="stat-value money money-neg">@inr($totalRemaining)</div>
                <div class="small text-body-secondary mt-1">
                    Cash across remaining EMIs — includes future interest, not principal only.
                </div>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body">
                <div class="stat-label">Every month</div>
                <div class="stat-value money">@inr($monthlyEmi)</div>
                <div class="small text-body-secondary mt-1">Combined EMIs across running loans</div>
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        @foreach ($loans as $loan)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <a href="{{ route('loans.show', $loan) }}"
                                   class="h6 mb-0 d-block text-decoration-none">{{ $loan->name }}</a>
                                <div class="small text-body-secondary">
                                    {{ $loan->lender ?: 'Loan' }} · due on the {{ $loan->due_day }}
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge text-bg-{{ $loan->status->badgeClass() }}">
                                    {{ $loan->status->label() }}
                                </span>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <div class="stat-label">EMI</div>
                                <div class="money fw-semibold">@inr($loan->emi_amount)</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-label">Paid</div>
                                <div class="money">@inrc($loan->paidAmount())</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-label">Left</div>
                                <div class="money">@inrc($loan->remainingAmount())</div>
                            </div>
                        </div>

                        @php($progress = $loan->progressPercent())
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-body-secondary">
                                {{ $loan->paidCount() }} of {{ $loan->total_months }} EMIs
                            </span>
                            <span class="text-body-secondary">
                                ends {{ $loan->end_date->format('M Y') }}
                            </span>
                        </div>
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar bg-success" style="width: {{ $progress }}%"></div>
                        </div>

                        @if ($loan->overduePayments()->exists())
                            <div class="small text-danger mt-2">
                                {{ $loan->overduePayments()->count() }} EMI(s) past due and not yet recorded.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@endsection
