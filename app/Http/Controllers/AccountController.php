<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\StoreAccountRequest;
use App\Models\Account;
use App\Models\Person;
use App\Models\Transaction;
use App\Services\AccountBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountBalanceService $balances,
        private readonly \App\Services\Reporting\NetWorthService $netWorth,
    ) {}

    public function index(Request $request): View
    {
        $owner = $request->integer('owner') ?: null;

        $accounts = Account::query()
            ->with('owner')
            ->counted()
            ->when($owner, fn ($q) => $q->ownedBy($owner))
            ->orderBy('type')->orderBy('name')->get();

        // Set-aside accounts are kept out of every total and every other screen,
        // but must stay reachable here — otherwise the household could not
        // transfer into the fund or correct its balance.
        $setAside = Account::query()->with('owner')->active()->setAside()
            ->when($owner, fn ($q) => $q->ownedBy($owner))
            ->orderBy('name')->get();

        return view('accounts.index', [
            'accounts' => $accounts->groupBy(fn (Account $a) => $a->type->label()),
            'setAside' => $setAside,
            'netWorth' => $this->netWorth->summary(),
            'owners' => Person::query()->active()->payers()->ordered()->get(),
            'selectedOwner' => $owner,
        ]);
    }

    public function create(): View
    {
        return view('accounts.create', [
            'types' => AccountType::cases(),
            'owners' => Person::query()->active()->payers()->ordered()->get(),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $account = Account::create($request->validated());

        $this->balances->recalculate($account);

        return redirect()
            ->route('accounts.index')
            ->with('status', "\"{$account->name}\" added with its opening balance.");
    }

    /** An account's own ledger — the drill-down behind its balance (spec Rule 8). */
    public function show(Account $account): View
    {
        return view('accounts.show', [
            'account' => $account,
            'derivedBalance' => $this->balances->balance($account),
            'transactions' => Transaction::query()
                ->with(['category', 'merchant', 'payer'])
                ->forAccount($account)
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->paginate(50),
        ]);
    }

    public function edit(Account $account): View
    {
        return view('accounts.edit', [
            'account' => $account,
            'types' => AccountType::cases(),
            'owners' => Person::query()->active()->payers()->ordered()->get(),
            'hasActivity' => $account->transactions()->exists(),
        ]);
    }

    public function update(StoreAccountRequest $request, Account $account): RedirectResponse
    {
        $data = $request->validated();

        // Switching an account between asset and liability would invert the
        // meaning of every leg already posted against it, so it is blocked once
        // the account has history. Retiring it and opening a new one keeps the
        // existing records honest.
        $newType = AccountType::from($data['type']);

        if ($newType !== $account->type && $account->transactions()->exists()) {
            throw ValidationException::withMessages([
                'type' => 'This account already has transactions, so its type cannot be changed. '
                    .'Mark it inactive and create a new account instead.',
            ]);
        }

        $account->update($data);

        $this->balances->recalculate($account);

        return redirect()
            ->route('accounts.index')
            ->with('status', "\"{$account->name}\" updated and its balance recalculated.");
    }

    /** Re-derive every balance from the ledger — a consistency check (spec Rule 8). */
    public function recalculate(): RedirectResponse
    {
        $drift = $this->balances->recalculateAll();

        if ($drift === []) {
            return redirect()->route('accounts.index')
                ->with('status', 'All balances match the ledger.');
        }

        $summary = collect($drift)
            ->map(fn ($d, $name) => "{$name}: {$d['from']} → {$d['to']}")
            ->implode('; ');

        return redirect()->route('accounts.index')
            ->with('warning', 'Balances were corrected from the ledger — '.$summary);
    }
}
