<?php

namespace App\Http\Controllers;

use App\Enums\Frequency;
use App\Enums\PlannedStatus;
use App\Enums\Purpose;
use App\Enums\ScheduleStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Person;
use App\Models\RecurringTransaction;
use App\Models\RecurringTransactionOccurrence;
use App\Services\RecurringTransactionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecurringTransactionController extends Controller
{
    public function __construct(
        private readonly RecurringTransactionService $recurring,
    ) {}

    public function index(): View
    {
        return view('recurring.index', $this->formData() + [
            'commitments' => RecurringTransaction::with(['account', 'category'])
                ->orderByDesc('is_active')
                ->orderBy('next_due_date')
                ->get(),
            'due' => RecurringTransactionOccurrence::with('recurringTransaction.account')
                ->scheduled()
                ->where('due_date', '<=', today()->addDays(14))
                ->whereHas('recurringTransaction', fn ($q) => $q->active())
                ->orderBy('due_date')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $this->recurring->create($data);

        return back()->with('status', 'Commitment added. It will show up in Upcoming until you confirm each one.');
    }

    public function update(Request $request, RecurringTransaction $recurring): RedirectResponse
    {
        $data = $this->validated($request);

        $this->recurring->update($recurring, $data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'Commitment updated. Unconfirmed dates were refreshed.');
    }

    public function destroy(RecurringTransaction $recurring): RedirectResponse
    {
        $recurring->update(['is_active' => false]);
        $recurring->occurrences()->where('status', ScheduleStatus::Scheduled->value)->delete();

        return back()->with('status', "\"{$recurring->name}\" stopped. Past entries are kept.");
    }

    /** Record that a scheduled commitment actually happened. */
    public function confirm(Request $request, RecurringTransactionOccurrence $occurrence): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'paid_on' => ['nullable', 'date'],
        ]);

        try {
            $this->recurring->confirm(
                $occurrence,
                $data['amount'] ?? null,
                isset($data['paid_on']) ? Carbon::parse($data['paid_on']) : null,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Recorded.');
    }

    public function skip(Request $request, RecurringTransactionOccurrence $occurrence): RedirectResponse
    {
        $this->recurring->skip($occurrence, $request->input('reason'));

        return back()->with('status', 'Skipped — nothing was recorded for that date.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:expense,income'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'frequency' => ['required', 'in:daily,weekly,monthly,quarterly,yearly'],
            'next_due_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:next_due_date'],
            'account_id' => ['required', 'exists:accounts,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'payer_id' => ['nullable', 'exists:people,id'],
            'beneficiary_id' => ['nullable', 'exists:people,id'],
            'merchant_id' => ['nullable', 'exists:merchants,id'],
            'purpose' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function formData(): array
    {
        return [
            'accounts' => Account::query()->active()->orderBy('name')->get(),
            'categories' => Category::query()->active()->ordered()->get(),
            'payers' => Person::query()->active()->payers()->ordered()->get(),
            'beneficiaries' => Person::query()->active()->beneficiaries()->ordered()->get(),
            'merchants' => Merchant::query()->active()->orderBy('name')->get(),
            'frequencies' => Frequency::cases(),
            'purposes' => Purpose::cases(),
            'plannedStatuses' => PlannedStatus::cases(),
        ];
    }
}
