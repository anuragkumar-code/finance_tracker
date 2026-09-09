<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Reconciliation;
use App\Services\ReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function __construct(
        private readonly ReconciliationService $reconciliations,
    ) {}

    public function index(): View
    {
        return view('reconciliations.index', [
            'rows' => $this->reconciliations->overview(),
            'recent' => Reconciliation::with(['account', 'adjustment'])
                ->orderByDesc('reconciliation_date')->orderByDesc('id')->limit(25)->get(),
            'openGaps' => Reconciliation::with('account')->where('status', 'discrepancy')
                ->orderByDesc('reconciliation_date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'actual_balance' => ['required', 'numeric'],
            'reconciliation_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ], [
            'actual_balance.required' => 'Enter the balance your bank or card is showing.',
        ]);

        try {
            $reconciliation = $this->reconciliations->reconcile(
                Account::findOrFail($data['account_id']),
                $data['actual_balance'],
                Carbon::parse($data['reconciliation_date']),
                $data['note'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['actual_balance' => $e->getMessage()]);
        }

        if ($reconciliation->matched()) {
            return back()->with('status', $reconciliation->account->name.' matches. Nothing to fix.');
        }

        return back()->with('warning',
            $reconciliation->account->name.' is out by '
            .\App\Support\Money::inr($reconciliation->difference)
            .'. Look for a missing entry before adjusting — the gap usually means '
            .'something was not recorded.');
    }

    /** Close a gap with a visible adjustment, only on explicit confirmation. */
    public function adjust(Request $request, Reconciliation $reconciliation): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Say why the balances differ before adjusting.',
        ]);

        try {
            $this->reconciliations->postAdjustment($reconciliation, $data['reason']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        return back()->with('status',
            'Adjustment posted. It appears in the account history like any other entry, '
            .'so the balance stays explainable.');
    }
}
