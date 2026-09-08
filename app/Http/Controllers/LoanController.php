<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoanRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function __construct(
        private readonly LoanService $loans,
    ) {}

    public function index(): View
    {
        $loans = Loan::with(['paymentAccount', 'owner'])->orderBy('status')->orderBy('name')->get();

        return view('loans.index', [
            'loans' => $loans,
            'totalRemaining' => $loans->where('status', \App\Enums\LoanStatus::Active)->reduce(
                fn (string $carry, Loan $loan) => bcadd($carry, $loan->remainingAmount(), 2),
                '0.00'
            ),
            'monthlyEmi' => $loans->where('status', \App\Enums\LoanStatus::Active)->reduce(
                fn (string $carry, Loan $loan) => bcadd($carry, (string) $loan->emi_amount, 2),
                '0.00'
            ),
        ]);
    }

    public function create(): View
    {
        return view('loans.create', $this->formData());
    }

    public function store(StoreLoanRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $loan = $this->loans->create($data, $request->boolean('mark_past_as_paid', true));
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['emi_amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('loans.show', $loan)
            ->with('status', "\"{$loan->name}\" added with a {$loan->total_months}-month schedule.");
    }

    public function show(Loan $loan): View
    {
        $loan->load(['paymentAccount', 'category', 'owner']);

        return view('loans.show', [
            'loan' => $loan,
            'upcoming' => $loan->payments()->scheduled()->orderBy('due_date')->limit(12)->get(),
            'recent' => $loan->payments()
                ->where('status', \App\Enums\ScheduleStatus::Paid->value)
                ->orderByDesc('due_date')->limit(12)->get(),
            'overdue' => $loan->overduePayments()->orderBy('due_date')->get(),
            'accounts' => Account::query()->active()->assets()->orderBy('name')->get(),
        ]);
    }

    public function edit(Loan $loan): View
    {
        return view('loans.edit', $this->formData() + ['loan' => $loan]);
    }

    /**
     * Only descriptive fields are editable. Changing the EMI or tenure would
     * invalidate a schedule that already has confirmed payments against it, so
     * that is refused rather than silently rebuilt.
     */
    public function update(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'lender' => ['nullable', 'string', 'max:100'],
            'owner_id' => ['nullable', 'exists:people,id'],
            'payment_account_id' => ['nullable', 'exists:accounts,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $loan->update($data);

        return redirect()->route('loans.show', $loan)->with('status', 'Loan updated.');
    }

    /** Confirm that an instalment was actually paid. */
    public function payInstalment(Request $request, Loan $loan, LoanPayment $payment): RedirectResponse
    {
        abort_unless($payment->loan_id === $loan->id, 404);

        $data = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
        ]);

        try {
            $this->loans->payInstalment(
                $payment,
                Account::findOrFail($data['account_id']),
                Carbon::parse($data['payment_date']),
                $data['amount'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['account_id' => $e->getMessage()]);
        }

        return back()->with('status', 'EMI recorded. It has been counted in this month\'s spending.');
    }

    public function unpayInstalment(Request $request, Loan $loan, LoanPayment $payment): RedirectResponse
    {
        abort_unless($payment->loan_id === $loan->id, 404);

        $data = $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
        ], [
            'void_reason.required' => 'Please say why this EMI is being undone.',
        ]);

        $this->loans->unpayInstalment($payment, $data['void_reason']);

        return back()->with('status', 'EMI undone and its entry voided.');
    }

    private function formData(): array
    {
        return [
            'accounts' => Account::query()->active()->assets()->orderBy('name')->get(),
            'categories' => Category::query()->active()->forExpenses()->ordered()->get(),
            'owners' => \App\Models\Person::query()->active()->payers()->ordered()->get(),
        ];
    }
}
