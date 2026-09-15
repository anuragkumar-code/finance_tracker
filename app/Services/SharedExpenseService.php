<?php

namespace App\Services;

use App\Enums\SettlementDirection;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Event;
use App\Models\Person;
use App\Models\SharedSettlement;
use App\Models\SharedSettlementAllocation;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Squaring up costs shared with people outside the household.
 *
 * The rule this service exists to enforce: spending is what the household
 * consumed, not what happened to pass through its accounts.
 *
 * When the household paid for a friend (TheyOwe), the friend's share is taken
 * out of the expense rows and moved to the friend's balance by a transfer from
 * the same account on the same date. The account's balance is identical before
 * and after, on every date, so statements still reconcile; only the split
 * between "spent" and "owed to us" changes.
 *
 * When a friend paid for the household (WeOwe), the household's share is
 * recorded as spending against the friend's balance, which goes negative until
 * it is paid back.
 *
 * Everything here writes ordinary ledger rows. Nothing that totals spending
 * needs to know settlements exist, which is what keeps every report, budget
 * and drill-down agreeing without a special case in any of them.
 */
class SharedExpenseService
{
    private const SCALE = 2;

    public function __construct(
        private readonly AccountBalanceService $balances,
        private readonly TransactionService $transactions,
        private readonly TransferService $transfers,
        private readonly PersonBalanceService $people,
    ) {}

    /**
     * Settle part of a trip or event with one person.
     *
     * For TheyOwe the amount is spread across the event's expense rows in
     * proportion to their size, so the category picture of the trip stays
     * honest: if the stay was half the cost, half the friend's share comes out
     * of the stay.
     */
    public function settleEvent(
        Event $event,
        Person $person,
        SettlementDirection $direction,
        string $amount,
        string $settledOn,
        ?int $categoryId = null,
        ?string $notes = null,
    ): SharedSettlement {
        $this->assertPositive($amount);
        $this->assertExternal($person);

        return DB::transaction(function () use ($event, $person, $direction, $amount, $settledOn, $categoryId, $notes) {
            $settlement = SharedSettlement::create([
                'event_id' => $event->getKey(),
                'person_id' => $person->getKey(),
                'direction' => $direction,
                'amount' => $amount,
                'settled_on' => $settledOn,
                'notes' => $notes,
            ]);

            if ($direction === SettlementDirection::TheyOwe) {
                $rows = $this->eligibleRows($event->expenses()->with('account')->get());

                $this->moveShareToFriend($settlement, $rows, $person, $amount, $event->name);
            } else {
                $this->recordOurShare($settlement, $person, $amount, $settledOn, $categoryId, $event);
            }

            return $settlement->refresh();
        });
    }

    /**
     * Split one bill: the household paid it all, and part of it was a friend's.
     */
    public function splitTransaction(Transaction $transaction, Person $person, string $theirShare, ?string $notes = null): SharedSettlement
    {
        $this->assertPositive($theirShare);
        $this->assertExternal($person);

        return DB::transaction(function () use ($transaction, $person, $theirShare, $notes) {
            $rows = $this->eligibleRows(collect([$transaction->loadMissing('account')]));

            $settlement = SharedSettlement::create([
                'event_id' => $transaction->event_id,
                'person_id' => $person->getKey(),
                'direction' => SettlementDirection::TheyOwe,
                'amount' => $theirShare,
                'settled_on' => $transaction->transaction_date->toDateString(),
                'notes' => $notes,
            ]);

            $this->moveShareToFriend(
                $settlement,
                $rows,
                $person,
                $theirShare,
                $transaction->description ?: $transaction->merchant?->name ?: 'shared bill',
            );

            return $settlement->refresh();
        });
    }

    /**
     * Put everything back exactly as it was before the settlement.
     *
     * Reduced rows get their share added back (not overwritten with a stored
     * original, so undoing two settlements in either order is still exact),
     * and the entries the settlement wrote are voided with a reason so the
     * history shows what happened.
     */
    public function undo(SharedSettlement $settlement): void
    {
        DB::transaction(function () use ($settlement) {
            $touched = [];

            foreach ($settlement->allocations()->with('transaction')->get() as $allocation) {
                $row = $allocation->transaction;

                if ($row === null || $row->trashed()) {
                    throw new RuntimeException(
                        'An entry this settlement reduced has since been voided, so it cannot be put back. '
                        .'Restore that entry first.'
                    );
                }

                $row->forceFill([
                    'amount' => bcadd((string) $row->amount, (string) $allocation->reduced_by, self::SCALE),
                ])->save();

                $touched[] = $row->account_id;
            }

            $reason = "Settlement with {$settlement->person->name} undone";

            foreach ($settlement->entries()->get() as $entry) {
                $touched[] = $entry->account_id;
                $entry->forceFill(['void_reason' => $reason])->save();
                $entry->delete();
            }

            $this->balances->recalculateMany($touched);

            // Allocations cascade; the voided entries keep their "settlement"
            // source, which is what stops them being restored on their own.
            $settlement->delete();
        });
    }

    /**
     * Expense rows a friend's share can be taken from: paid from the
     * household's own accounts, and not already a settlement entry.
     *
     * @param  Collection<int, Transaction>  $rows
     * @return Collection<int, Transaction>
     */
    private function eligibleRows(Collection $rows): Collection
    {
        $eligible = $rows
            ->filter(fn (Transaction $row) => $row->type === TransactionType::Expense
                && ! $row->trashed()
                && ! $row->isSettlementEntry()
                && ! $row->account->isFriendBalance())
            ->values();

        if ($eligible->isEmpty()) {
            throw new InvalidArgumentException(
                'There is nothing you paid for here to take a share from. Record what you spent first.'
            );
        }

        // Splits must add up to their parent, and reducing the parent would
        // break that silently. Rare enough to refuse rather than rescale.
        if ($eligible->contains(fn (Transaction $row) => $row->splits()->exists())) {
            throw new InvalidArgumentException(
                'One of these entries is split across categories. Remove its split before settling up.'
            );
        }

        return $eligible;
    }

    /**
     * Take $amount out of the rows in proportion and move it to the friend.
     *
     * @param  Collection<int, Transaction>  $rows
     */
    private function moveShareToFriend(SharedSettlement $settlement, Collection $rows, Person $person, string $amount, string $label): void
    {
        $pool = $rows->reduce(fn (string $carry, Transaction $row) => bcadd($carry, (string) $row->amount, self::SCALE), '0.00');

        // Equal is refused too: every row would fall to zero, and a zero-amount
        // expense is not a valid entry. If a friend genuinely owes the whole
        // amount, the household never spent anything and the entries should be
        // recorded as a transfer to them instead.
        if (bccomp($amount, $pool, self::SCALE) !== -1) {
            throw new InvalidArgumentException(
                "₹{$amount} is not less than the ₹{$pool} you paid here. A friend's share has to leave some "
                .'cost with you — if they owe all of it, this was a loan rather than shared spending.'
            );
        }

        $reductions = $this->proportionalShares($rows, $pool, $amount);
        $friendAccount = $this->people->accountFor($person);

        $byAccountAndDate = [];

        foreach ($rows as $row) {
            $reduction = $reductions[$row->getKey()];

            if (bccomp($reduction, '0', self::SCALE) !== 1) {
                continue;
            }

            $row->forceFill([
                'amount' => bcsub((string) $row->amount, $reduction, self::SCALE),
            ])->save();

            SharedSettlementAllocation::create([
                'shared_settlement_id' => $settlement->getKey(),
                'transaction_id' => $row->getKey(),
                'reduced_by' => $reduction,
            ]);

            // One transfer per account per day rather than per row: still exact
            // for the balance on every date, without doubling the number of
            // lines a busy trip day puts in the ledger.
            $key = $row->account_id.'|'.$row->transaction_date->toDateString();

            $byAccountAndDate[$key] ??= [
                'account' => $row->account,
                'date' => $row->transaction_date->toDateString(),
                'amount' => '0.00',
            ];

            $byAccountAndDate[$key]['amount'] = bcadd($byAccountAndDate[$key]['amount'], $reduction, self::SCALE);
        }

        foreach ($byAccountAndDate as $group) {
            $this->markAsSettlement($settlement, $this->transfers->create([
                'from_account_id' => $group['account'],
                'to_account_id' => $friendAccount,
                'amount' => $group['amount'],
                'transaction_date' => $group['date'],
                'description' => "{$person->name}'s share · {$label}",
            ]));
        }

        $this->balances->recalculateMany([...$rows->pluck('account_id')->all(), $friendAccount]);
    }

    /** The household's share of what a friend paid, as spending owed to them. */
    private function recordOurShare(SharedSettlement $settlement, Person $person, string $amount, string $date, ?int $categoryId, Event $event): void
    {
        $entry = $this->transactions->recordExpense([
            'account_id' => $this->people->accountFor($person),
            'amount' => $amount,
            'transaction_date' => $date,
            'category_id' => $categoryId,
            'event_id' => $event->getKey(),
            'description' => "Your share of what {$person->name} paid · {$event->name}",
        ]);

        $this->markAsSettlement($settlement, collect([$entry]));
    }

    /** @param  Collection<int, Transaction>  $entries */
    private function markAsSettlement(SharedSettlement $settlement, Collection $entries): void
    {
        foreach ($entries as $entry) {
            $entry->forceFill([
                'shared_settlement_id' => $settlement->getKey(),
                'source' => 'settlement',
            ])->save();
        }
    }

    /**
     * Split $amount across rows in proportion to their size, to the paisa.
     *
     * Each share is rounded down, then the paise lost to rounding go one at a
     * time to the rows that lost the most — so the shares always add up to
     * exactly $amount, and no row is pushed to zero.
     *
     * @param  Collection<int, Transaction>  $rows
     * @return array<int, string> transaction id => reduction
     */
    private function proportionalShares(Collection $rows, string $pool, string $amount): array
    {
        $shares = [];
        $remainders = [];
        $allocated = '0.00';

        foreach ($rows as $row) {
            $exact = bcdiv(bcmul((string) $row->amount, $amount, 10), $pool, 10);
            $floor = bcadd($exact, '0', self::SCALE); // bcmath truncates, and $exact is positive

            $shares[$row->getKey()] = $floor;
            $remainders[$row->getKey()] = bcsub($exact, $floor, 10);
            $allocated = bcadd($allocated, $floor, self::SCALE);
        }

        $leftoverPaise = (int) bcmul(bcsub($amount, $allocated, self::SCALE), '100', 0);

        arsort($remainders, SORT_NUMERIC);

        foreach (array_keys($remainders) as $id) {
            if ($leftoverPaise <= 0) {
                break;
            }

            $row = $rows->firstWhere('id', $id);
            $candidate = bcadd($shares[$id], '0.01', self::SCALE);

            // Never take a row's last paisa.
            if (bccomp($candidate, (string) $row->amount, self::SCALE) === -1) {
                $shares[$id] = $candidate;
                $leftoverPaise--;
            }
        }

        if ($leftoverPaise > 0) {
            throw new RuntimeException('Could not spread the share across these entries without emptying one.');
        }

        return $shares;
    }

    private function assertPositive(string $amount): void
    {
        if (! is_numeric($amount) || bccomp($amount, '0', self::SCALE) !== 1) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }
    }

    private function assertExternal(Person $person): void
    {
        if (! $person->is_external) {
            throw new InvalidArgumentException(
                "{$person->name} is part of the household — shares are only settled with people outside it."
            );
        }
    }
}
