<?php

namespace App\Http\Controllers;

use App\Enums\PlannedStatus;
use App\Enums\Purpose;
use App\Enums\TransactionType;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\StoreIncomeRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Event;
use App\Models\Merchant;
use App\Models\MerchantGroup;
use App\Models\Person;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Services\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactions,
        private readonly TransferService $transfers,
    ) {}

    /** Filterable ledger view (spec section 30). */
    public function index(Request $request): View
    {
        $query = Transaction::query()
            ->with(['account', 'category', 'subcategory', 'merchant', 'payer', 'beneficiary']);

        $this->applyFilters($query, $request);

        $this->applySort($query, $request);

        $transactions = $query
            ->paginate($this->perPage($request))
            ->withQueryString();

        // Only the spending rows are totalled. Adding income, transfers and
        // expenses into one figure would produce a number that means nothing,
        // and this total exists precisely so a drill-down reconciles with the
        // report figure the user clicked to get here.
        $filteredSpending = (string) (clone $query)->spending()->sum('amount');

        return view('transactions.index', [
            'transactions' => $transactions,
            'filteredSpending' => $filteredSpending,
            'accounts' => Account::orderBy('name')->get(),
            'categories' => Category::active()->ordered()->get(),
            'people' => Person::active()->ordered()->get(),
            'merchants' => Merchant::active()->orderBy('name')->get(),
            'merchantGroups' => MerchantGroup::query()->ordered()->get(),
            'events' => Event::query()->latestFirst()->get(),
            'types' => TransactionType::cases(),
            'plannedStatuses' => PlannedStatus::cases(),
            'purposes' => Purpose::cases(),
            'filters' => $request->all(),
        ]);
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load([
            'account.person', 'category', 'subcategory', 'merchant', 'payer', 'beneficiary', 'splits', 'tags',
            'event', 'settlement.person', 'settlement.event', 'shareAllocations.settlement.person',
        ]);

        return view('transactions.show', [
            'transaction' => $transaction,
            'counterpart' => $transaction->counterpartLeg(),
        ]);
    }

    /** Full entry form for income and transfers; expenses use Quick Entry. */
    public function create(Request $request): View
    {
        $type = TransactionType::tryFrom((string) $request->query('type', 'income'));

        if ($type === null || ! in_array($type, TransactionType::phaseOne(), true)) {
            $type = TransactionType::Income;
        }

        return view('transactions.create', $this->formData() + ['type' => $type]);
    }

    public function storeIncome(StoreIncomeRequest $request): RedirectResponse
    {
        $transaction = $this->transactions->recordIncome($request->validated());

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('status', 'Income recorded.');
    }

    public function storeTransfer(StoreTransferRequest $request): RedirectResponse
    {
        $legs = $this->transfers->create($request->validated());

        return redirect()
            ->route('transactions.show', $legs->first())
            ->with('status', 'Transfer recorded. This is a move between your own accounts, so it is not counted as spending.');
    }

    public function edit(Transaction $transaction): View
    {
        $data = $this->formData();
        $counterpart = $transaction->counterpartLeg();

        // The picker offers only the household's own accounts, but an entry can
        // sit on a friend's balance (a write-off, a repayment). Its current
        // accounts must stay selectable, or saving would quietly move it onto
        // whichever account happens to be listed first.
        $current = collect([$transaction->account, $counterpart?->account])->filter();
        $data['accounts'] = $data['accounts']
            ->concat($current->reject(fn ($a) => $data['accounts']->contains('id', $a->id)))
            ->values();

        return view('transactions.edit', $data + [
            'transaction' => $transaction,
            'counterpart' => $counterpart,
        ]);
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        try {
            if ($transaction->type === TransactionType::Transfer) {
                $data = $request->validate([
                    'transaction_date' => ['required', 'date'],
                    'amount' => ['required', 'numeric', 'gt:0'],
                    'from_account_id' => ['required', 'exists:accounts,id'],
                    'to_account_id' => ['required', 'different:from_account_id', 'exists:accounts,id'],
                    'description' => ['nullable', 'string', 'max:255'],
                    'notes' => ['nullable', 'string'],
                ]);

                $this->transfers->update($transaction, $data);
            } else {
                $data = $request->validate([
                    'transaction_date' => ['required', 'date'],
                    'amount' => ['required', 'numeric', 'gt:0'],
                    'account_id' => ['required', 'exists:accounts,id'],
                    'category_id' => ['nullable', 'exists:categories,id'],
                    'subcategory_id' => ['nullable', 'exists:categories,id'],
                    'payer_id' => ['nullable', 'exists:people,id'],
                    'beneficiary_id' => ['nullable', 'exists:people,id'],
                    'merchant_id' => ['nullable', 'exists:merchants,id'],
                    'event_id' => ['nullable', 'exists:events,id'],
                    'planned_status' => ['nullable', 'string'],
                    'purpose' => ['nullable', 'string'],
                    'description' => ['nullable', 'string', 'max:255'],
                    'notes' => ['nullable', 'string'],
                ]);

                $this->transactions->update($transaction, $data);
            }
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('status', 'Updated. Affected balances have been recalculated.');
    }

    /**
     * Void rather than delete (spec Rule 7) — the record stays for audit and
     * simply stops affecting balances.
     */
    public function void(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
        ], [
            'void_reason.required' => 'Please say why this entry is being removed.',
        ]);

        try {
            $this->transactions->void($transaction, $data['void_reason']);
        } catch (\RuntimeException $e) {
            // A settled entry refuses to be voided on its own; say why rather
            // than failing the request.
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Entry voided. It no longer affects any balance, but the record is kept.');
    }

    public function restore(int $id): RedirectResponse
    {
        $transaction = Transaction::withTrashed()->findOrFail($id);

        try {
            $this->transactions->restore($transaction);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('status', 'Entry restored.');
    }

    /**
     * Column sorting for the ledger.
     *
     * Only these columns can be sorted on — the value arrives from the query
     * string, so it is matched against a fixed list rather than passed to
     * orderBy. Whatever the primary sort, id breaks ties: two entries on the
     * same date must not swap places between page one and page two, or rows
     * get skipped as you page through.
     */
    private function applySort($query, Request $request): void
    {
        $sortable = [
            'date' => 'transaction_date',
            'amount' => 'amount',
        ];

        $column = $sortable[$request->query('sort')] ?? 'transaction_date';
        $direction = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($column, $direction);

        if ($column !== 'transaction_date') {
            $query->orderByDesc('transaction_date');
        }

        $query->orderByDesc('id');
    }

    /** Rows per page, from a fixed set so the query string cannot ask for 50,000. */
    private function perPage(Request $request): int
    {
        $requested = (int) $request->query('per_page', 50);

        return in_array($requested, [25, 50, 100, 200], true) ? $requested : 50;
    }

    private function applyFilters($query, Request $request): void
    {
        $query
            ->when($request->filled('start'), fn ($q) => $q->where('transaction_date', '>=', $request->date('start')))
            ->when($request->filled('end'), fn ($q) => $q->where('transaction_date', '<=', $request->date('end')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', $request->input('account_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('category_id', $request->input('category_id'))
                    ->orWhere('subcategory_id', $request->input('category_id'));
            }))
            ->when($request->filled('payer_id'), fn ($q) => $q->where('payer_id', $request->input('payer_id')))
            ->when($request->filled('beneficiary_id'), fn ($q) => $q->where('beneficiary_id', $request->input('beneficiary_id')))
            ->when($request->filled('merchant_id'), fn ($q) => $q->where('merchant_id', $request->input('merchant_id')))
            ->when($request->filled('event_id'), fn ($q) => $q->where('event_id', $request->input('event_id')))
            // Drilling in from the "kinds of place" report. Expressed as a
            // subquery on merchants rather than a join so it composes with
            // every other filter without duplicating rows.
            ->when($request->filled('merchant_group_id'), fn ($q) => $q->whereHas(
                'merchant',
                fn ($m) => $m->where('merchant_group_id', $request->input('merchant_group_id'))
            ))
            ->when($request->filled('planned_status'), fn ($q) => $q->where('planned_status', $request->input('planned_status')))
            ->when($request->filled('purpose'), fn ($q) => $q->where('purpose', $request->input('purpose')))
            ->when($request->filled('min_amount'), fn ($q) => $q->where('amount', '>=', $request->input('min_amount')))
            ->when($request->filled('max_amount'), fn ($q) => $q->where('amount', '<=', $request->input('max_amount')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->input('q').'%';
                $q->where(function ($q) use ($term) {
                    $q->where('description', 'like', $term)
                        ->orWhere('notes', 'like', $term)
                        ->orWhere('reference', 'like', $term)
                        ->orWhereHas('merchant', fn ($m) => $m->where('name', 'like', $term));
                });
            });
    }

    private function formData(): array
    {
        return [
            'accounts' => Account::query()->active()->own()->with('owner')->orderBy('name')->get(),
            'events' => Event::query()->latestFirst()->get(),
            'categories' => Category::query()->active()->topLevel()->ordered()->with('children')->get(),
            'incomeCategories' => Category::query()->active()->forIncome()->ordered()->get(),
            'payers' => Person::query()->active()->payers()->ordered()->get(),
            'beneficiaries' => Person::query()->active()->beneficiaries()->ordered()->get(),
            'merchants' => Merchant::query()->active()->orderBy('name')->get(),
            'plannedStatuses' => PlannedStatus::cases(),
            'purposes' => Purpose::cases(),
        ];
    }
}
