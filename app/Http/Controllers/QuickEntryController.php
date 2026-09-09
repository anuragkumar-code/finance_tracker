<?php

namespace App\Http\Controllers;

use App\Enums\PlannedStatus;
use App\Enums\Purpose;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Person;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The fast path for daily entry (spec section 16, Decision H).
 *
 * Everything except amount and account is optional, so a typical evening entry
 * is three taps. Merchant defaults fill the rest in.
 */
class QuickEntryController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactions,
    ) {}

    public function create(): View
    {
        return view('quick-entry', $this->formData());
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['merchant_id'] = $this->resolveMerchant($request);

        $transaction = $this->transactions->recordExpense($data);

        $this->rememberMerchantDefaults($transaction);

        return redirect()
            ->route('quick-entry')
            ->with('status', 'Saved '.\App\Support\Money::inr($transaction->amount)
                .' on '.$transaction->account->name.'.')
            ->with('saved_transaction_id', $transaction->id);
    }

    /**
     * Defaults the app has learned for a merchant, for the quick-entry form to
     * pre-fill over AJAX (spec section 16: "Amazon -> Shopping, HDFC Credit Card").
     */
    public function merchantDefaults(Merchant $merchant): JsonResponse
    {
        return response()->json($merchant->quickEntryDefaults());
    }

    /** Accept either an existing merchant or a free-typed new name. */
    private function resolveMerchant(Request $request): ?int
    {
        if ($request->filled('merchant_id')) {
            return (int) $request->input('merchant_id');
        }

        $name = trim((string) $request->input('merchant_name', ''));

        if ($name === '') {
            return null;
        }

        $merchant = Merchant::firstOrCreate(['name' => $name]);

        // Blinkit typed into the box should classify itself; the household
        // should not have to remember what a channel is mid-entry.
        $merchant->guessChannelIfUnset();

        return $merchant->id;
    }

    /**
     * Learn from what was just entered, so the next visit to this merchant
     * pre-fills itself. Only fills gaps — never overwrites a default the
     * household set deliberately in Settings.
     */
    private function rememberMerchantDefaults(\App\Models\Transaction $transaction): void
    {
        $merchant = $transaction->merchant;

        if ($merchant === null) {
            return;
        }

        $merchant->fill(array_filter([
            'default_category_id' => $merchant->default_category_id ?? $transaction->category_id,
            'default_subcategory_id' => $merchant->default_subcategory_id ?? $transaction->subcategory_id,
            'default_account_id' => $merchant->default_account_id ?? $transaction->account_id,
            'default_payer_id' => $merchant->default_payer_id ?? $transaction->payer_id,
            'default_beneficiary_id' => $merchant->default_beneficiary_id ?? $transaction->beneficiary_id,
        ], fn ($v) => $v !== null))->save();
    }

    private function formData(): array
    {
        return [
            // Seventeen accounts in one flat row is unusable. They are split by
            // kind, and the handful actually used recently is surfaced first —
            // most entries come from the same three or four places.
            'recentAccounts' => $this->recentlyUsedAccounts(),
            'bankAccounts' => Account::query()->active()->counted()->with('owner')
                ->whereIn('type', ['bank', 'cash'])->orderBy('name')->get(),
            'cardAccounts' => Account::query()->active()->counted()->with('owner')
                ->where('type', 'credit_card')->orderBy('name')->get(),
            'categories' => Category::query()->active()->forExpenses()->topLevel()->ordered()->with('children')->get(),
            'payers' => Person::query()->active()->payers()->ordered()->get(),
            'beneficiaries' => Person::query()->active()->beneficiaries()->ordered()->get(),
            'merchants' => Merchant::query()->active()->orderBy('name')->get(),
            'plannedStatuses' => PlannedStatus::cases(),
            'purposes' => Purpose::cases(),
        ];
    }
    /**
     * The accounts this household actually reaches for.
     *
     * Ordered by how often they have been used lately, so the common case is
     * one tap rather than a hunt through seventeen chips.
     *
     * @return \Illuminate\Support\Collection<int, Account>
     */
    private function recentlyUsedAccounts(int $limit = 5): \Illuminate\Support\Collection
    {
        $ids = \App\Models\Transaction::query()
            ->spending()
            ->where('transaction_date', '>=', now()->subDays(60))
            ->selectRaw('account_id, COUNT(*) AS uses')
            ->groupBy('account_id')
            ->orderByDesc('uses')
            ->limit($limit)
            ->pluck('account_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return Account::query()->active()->counted()->with('owner')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Account $a) => $ids->search($a->id))
            ->values();
    }
}