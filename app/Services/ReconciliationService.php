<?php

namespace App\Services;

use App\Enums\BalanceEffect;
use App\Enums\LegRole;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Reconciliation;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Checking the app against reality (spec section 18).
 *
 * The rule that shapes this whole service: the app NEVER silently overwrites a
 * calculated balance to make it match the bank. A gap is recorded, shown, and
 * closed only by a visible adjustment transaction that the household explicitly
 * confirms — so every balance remains explainable by its own ledger (Rule 8).
 *
 * Quietly "fixing" a balance would destroy the one thing that makes this app
 * worth trusting: that its numbers can be traced back to something real.
 */
class ReconciliationService
{
    private const SCALE = 2;

    public function __construct(
        private readonly AccountBalanceService $balances,
    ) {}

    /**
     * Record what the bank says against what the app calculated.
     *
     * Creates no transaction: this is an observation, not a correction.
     */
    public function reconcile(
        Account $account,
        string $actualBalance,
        ?CarbonInterface $asOf = null,
        ?string $note = null,
    ): Reconciliation {
        if (! is_numeric($actualBalance)) {
            throw new InvalidArgumentException('Enter the balance your bank or card is showing.');
        }

        $asOf ??= now();
        $systemBalance = $this->balances->balance($account, $asOf);
        $difference = bcsub($actualBalance, $systemBalance, self::SCALE);
        $matched = bccomp($difference, '0', self::SCALE) === 0;

        return Reconciliation::create([
            'account_id' => $account->id,
            'reconciliation_date' => $asOf->toDateString(),
            'actual_balance' => $actualBalance,
            'system_balance' => $systemBalance,
            'difference' => $difference,
            'status' => $matched ? 'reconciled' : 'discrepancy',
            'note' => $note,
        ]);
    }

    /**
     * Close a gap by posting a visible adjustment — only ever on explicit
     * confirmation, and always with a reason recorded.
     *
     * The adjustment is a real ledger row, so it shows up in the account's
     * history like anything else. That is the point: the balance changed
     * because of something you can see and question later.
     */
    public function postAdjustment(Reconciliation $reconciliation, string $reason): Reconciliation
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Say why the balances differ before adjusting.');
        }

        if ($reconciliation->matched()) {
            throw new InvalidArgumentException('These balances already match — there is nothing to adjust.');
        }

        if ($reconciliation->adjustment_transaction_id !== null) {
            throw new InvalidArgumentException('This difference has already been adjusted.');
        }

        return DB::transaction(function () use ($reconciliation, $reason) {
            $account = $reconciliation->account;
            $difference = (string) $reconciliation->difference;
            $shortfall = bccomp($difference, '0', self::SCALE) === 1;

            // Direction is set explicitly here rather than derived from the
            // transaction type, because only the reconciliation knows which way
            // reality differs. Balances are stored as magnitudes for assets and
            // liabilities alike, so "the bank's figure is higher than ours"
            // always means the stored balance must rise — on a card that reads
            // as owing more, on a bank account as holding more.
            $effect = $shortfall ? BalanceEffect::Increase : BalanceEffect::Decrease;

            $transaction = Transaction::create([
                'transaction_date' => $reconciliation->reconciliation_date->toDateString(),
                'type' => TransactionType::Adjustment,
                'leg_role' => LegRole::Single,
                'account_id' => $account->id,
                'amount' => ltrim($difference, '-'),
                'balance_effect' => $effect,
                'description' => 'Reconciliation adjustment',
                'notes' => $reason,
                'reconciliation_id' => $reconciliation->id,
                'source' => 'manual',
            ]);

            $reconciliation->update([
                'adjustment_transaction_id' => $transaction->id,
                'status' => 'resolved',
                'note' => trim(($reconciliation->note ? $reconciliation->note."\n" : '').$reason),
            ]);

            $this->balances->recalculate($account);

            return $reconciliation->refresh();
        });
    }

    /**
     * The last check for each account, so the household can see at a glance
     * what has not been verified lately.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function overview(): \Illuminate\Support\Collection
    {
        // A friend's balance has no statement to check it against.
        return Account::query()->active()->own()->orderBy('type')->orderBy('name')->get()
            ->map(function (Account $account) {
                $last = Reconciliation::where('account_id', $account->id)
                    ->orderByDesc('reconciliation_date')->orderByDesc('id')->first();

                return (object) [
                    'account' => $account,
                    'last' => $last,
                    'system_balance' => (string) $account->cached_balance,
                    'days_since' => $last?->reconciliation_date->diffInDays(today()),
                    'open_gap' => $last !== null && $last->needsAttention(),
                ];
            });
    }
}
