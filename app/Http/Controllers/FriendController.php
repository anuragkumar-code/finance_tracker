<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Event;
use App\Models\Person;
use App\Services\PersonBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Who owes the household money, and whom it owes.
 */
class FriendController extends Controller
{
    public function __construct(
        private readonly PersonBalanceService $balances,
    ) {}

    public function index(): View
    {
        return view('friends.index', [
            'rows' => $this->balances->summary(includeSettled: true),
            'owedToUs' => $this->balances->totalOwedToUs(),
            'weOwe' => $this->balances->totalWeOwe(),
            'accounts' => Account::query()->active()->counted()->spendableCash()->with('owner')->orderBy('name')->get(),
            'categories' => Category::query()->active()->topLevel()->where('applies_to', '!=', 'income')->ordered()->get(),
            'events' => Event::query()->open()->latestFirst()->get(),
            'holiday' => Category::query()->whereNull('parent_id')->where('name', 'Holiday')->first(),
        ]);
    }

    /** Add a friend. They never appear in the household's who-paid / who-for pickers. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:people,name'],
            'relationship' => ['nullable', 'string', 'max:50'],
        ], [
            'name.unique' => 'Someone with that name already exists. Add a surname or initial to tell them apart.',
        ]);

        Person::create($data + [
            'is_external' => true,
            'is_household' => false,
            'can_be_payer' => false,
            'can_be_beneficiary' => false,
            'is_active' => true,
        ]);

        return back()->with('status', "{$data['name']} added.");
    }

    public function repayment(Request $request, Person $person): RedirectResponse
    {
        $data = $this->validatedMovement($request);

        return $this->attempt(fn () => $this->balances->recordRepayment(
            $person, Account::findOrFail($data['account_id']), (string) $data['amount'], $data['date'], $data['note'] ?? null,
        ), "Repayment from {$person->name} recorded. It is a transfer, not income.");
    }

    public function payback(Request $request, Person $person): RedirectResponse
    {
        $data = $this->validatedMovement($request);

        return $this->attempt(fn () => $this->balances->recordPayback(
            $person, Account::findOrFail($data['account_id']), (string) $data['amount'], $data['date'], $data['note'] ?? null,
        ), "Payment to {$person->name} recorded.");
    }

    public function writeOff(Request $request, Person $person): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'event_id' => ['nullable', 'exists:events,id'],
        ]);

        return $this->attempt(fn () => $this->balances->writeOff(
            $person, (string) $data['amount'], $data['date'], $data['category_id'] ?? null, $data['event_id'] ?? null,
        ), "Written off. That amount now counts as your own spending.");
    }

    private function validatedMovement(Request $request): array
    {
        return $request->validate([
            'account_id' => ['required', Rule::exists('accounts', 'id')->whereNull('person_id')->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', $success);
    }
}
