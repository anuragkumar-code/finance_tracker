<?php

namespace App\Http\Controllers;

use App\Enums\PlannedStatus;
use App\Enums\Purpose;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Person;
use App\Models\Transaction;
use App\Services\TransactionCompletenessService;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The entries still missing something, and a quick way to fill it in.
 *
 * Recording a spend is deliberately a few taps, so detail gets skipped in the
 * moment and never revisited — the ledger ends up carrying entries nobody can
 * report on. This is the place to catch up, biggest first, without opening
 * each entry in turn.
 */
class TidyUpController extends Controller
{
    public function __construct(
        private readonly TransactionCompletenessService $completeness,
        private readonly TransactionService $transactions,
    ) {}

    public function index(Request $request): View
    {
        $tier = $request->query('tier') === TransactionCompletenessService::RICHER
            ? TransactionCompletenessService::RICHER
            : TransactionCompletenessService::NEEDS;

        // Biggest first by default. Six entries carry 61% of the value here, so
        // ordering by date would bury the ones that actually matter.
        $sort = $request->query('sort') === 'date' ? 'transaction_date' : 'amount';

        $entries = $this->completeness->query($tier)
            ->with(['account', 'category.children', 'subcategory', 'merchant', 'payer', 'beneficiary'])
            ->orderByDesc($sort)
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('tidy-up.index', [
            'tier' => $tier,
            'sort' => $sort,
            'entries' => $entries,
            'gaps' => $entries->mapWithKeys(fn (Transaction $t) => [$t->id => $this->completeness->gapsFor($t)]),
            'counts' => $this->completeness->counts(),
            'completeness' => $this->completeness->monthlyCompleteness(4),

            'categories' => Category::query()->active()->topLevel()->ordered()->with('children')->get(),
            'merchants' => Merchant::query()->active()->with('group')->orderBy('name')->get(),
            'payers' => Person::query()->active()->payers()->ordered()->get(),
            'beneficiaries' => Person::query()->active()->beneficiaries()->ordered()->get(),
            'plannedStatuses' => PlannedStatus::cases(),
            'purposes' => Purpose::cases(),
        ]);
    }

    /**
     * Fill in the labels on one entry.
     *
     * Only the tagging fields are accepted. Amount, account and date are not
     * part of this screen at all, so a tidy-up pass can never move money or
     * disturb an entry a settlement depends on.
     */
    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'exists:categories,id'],
            'merchant_id' => ['nullable', 'exists:merchants,id'],
            'payer_id' => ['nullable', 'exists:people,id'],
            'beneficiary_id' => ['nullable', 'exists:people,id'],
            'planned_status' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        // A subcategory belonging to a different parent would make the category
        // roll-up disagree with itself; clearing it is kinder than refusing.
        if (($data['subcategory_id'] ?? null) !== null) {
            $child = Category::find($data['subcategory_id']);

            if ($child === null || (int) $child->parent_id !== (int) ($data['category_id'] ?? 0)) {
                $data['subcategory_id'] = null;
            }
        }

        try {
            $this->transactions->update($transaction, $data);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages(['category_id' => $e->getMessage()]);
        }

        return back()->with('status', 'Updated. '.$this->remainingMessage());
    }

    private function remainingMessage(): string
    {
        $left = $this->completeness->counts()[TransactionCompletenessService::NEEDS];

        return $left === 0
            ? 'Nothing else needs you.'
            : $left.' '.\Illuminate\Support\Str::plural('entry', $left).' still need you.';
    }
}
