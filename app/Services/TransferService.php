<?php

namespace App\Services;

use App\Enums\LegRole;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Moving money between accounts the household already owns (spec section 10).
 *
 * A transfer is NOT spending (spec Decision C). That is guaranteed structurally
 * rather than by a filter someone has to remember: both legs are written as
 * type='transfer', so no query that sums type='expense' can ever pick them up.
 *
 * The two legs share a transfer_group_id and are always created, edited and
 * voided together, so they can never drift apart into a half-recorded move.
 */
class TransferService
{
    public function __construct(
        private readonly AccountBalanceService $balances,
        private readonly TransactionService $transactions,
    ) {}

    /**
     * @return Collection<int, Transaction> the [from, to] legs
     */
    public function create(array $data): Collection
    {
        $from = $this->resolveAccount($data['from_account_id'] ?? null, 'source');
        $to = $this->resolveAccount($data['to_account_id'] ?? null, 'destination');

        $this->assertDistinctAccounts($from, $to);
        $this->assertPositiveAmount($data['amount'] ?? null);

        return DB::transaction(function () use ($data, $from, $to) {
            $groupId = (string) Str::uuid();

            $fromLeg = $this->writeLeg($data, $from, LegRole::TransferFrom, $groupId);
            $toLeg = $this->writeLeg($data, $to, LegRole::TransferTo, $groupId);

            $this->balances->recalculateMany([$from, $to]);

            return collect([$fromLeg->refresh(), $toLeg->refresh()]);
        });
    }

    /**
     * Edit a transfer from either of its legs; both are updated in step so the
     * amounts can never disagree (spec Rule 6).
     *
     * @return Collection<int, Transaction> the [from, to] legs
     */
    public function update(Transaction $leg, array $data): Collection
    {
        $legs = $this->legsFor($leg);

        if ($legs->contains(fn (Transaction $l) => $l->isSettlementEntry())) {
            throw new RuntimeException(
                'This transfer carries a friend\'s share from settling up. Undo that settlement instead of editing it.'
            );
        }

        $fromLeg = $legs->firstWhere('leg_role', LegRole::TransferFrom);
        $toLeg = $legs->firstWhere('leg_role', LegRole::TransferTo);

        if ($fromLeg === null || $toLeg === null) {
            throw new RuntimeException('This transfer is missing one of its legs and cannot be edited safely.');
        }

        $from = array_key_exists('from_account_id', $data)
            ? $this->resolveAccount($data['from_account_id'], 'source')
            : $fromLeg->account;

        $to = array_key_exists('to_account_id', $data)
            ? $this->resolveAccount($data['to_account_id'], 'destination')
            : $toLeg->account;

        $this->assertDistinctAccounts($from, $to);

        if (array_key_exists('amount', $data)) {
            $this->assertPositiveAmount($data['amount']);
        }

        return DB::transaction(function () use ($data, $legs, $fromLeg, $toLeg, $from, $to) {
            $touched = $legs->pluck('account_id')->all();

            $shared = collect($data)->only([
                'transaction_date', 'amount', 'description', 'notes', 'reference', 'payer_id',
            ])->all();

            $fromLeg->update($shared + [
                'account_id' => $from->getKey(),
                'balance_effect' => $this->balances->effectFor(
                    TransactionType::Transfer, LegRole::TransferFrom, $from->normal_balance
                ),
            ]);

            $toLeg->update($shared + [
                'account_id' => $to->getKey(),
                'balance_effect' => $this->balances->effectFor(
                    TransactionType::Transfer, LegRole::TransferTo, $to->normal_balance
                ),
            ]);

            $this->balances->recalculateMany([...$touched, $from, $to]);

            return collect([$fromLeg->refresh(), $toLeg->refresh()]);
        });
    }

    /** Void both legs together. */
    public function void(Transaction $leg, string $reason): Transaction
    {
        return $this->transactions->void($leg, $reason);
    }

    private function writeLeg(array $data, Account $account, LegRole $role, string $groupId): Transaction
    {
        return Transaction::create([
            'transaction_date' => $data['transaction_date'],
            'type' => TransactionType::Transfer,
            'leg_role' => $role,
            'transfer_group_id' => $groupId,
            'account_id' => $account->getKey(),
            'amount' => $data['amount'],
            'balance_effect' => $this->balances->effectFor(
                TransactionType::Transfer, $role, $account->normal_balance
            ),
            // Transfers carry no category, beneficiary or purpose: they are not
            // consumption, and tagging them as such would invite miscounting.
            'payer_id' => $data['payer_id'] ?? null,
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'reference' => $data['reference'] ?? null,
            'source' => $data['source'] ?? 'manual',
        ]);
    }

    /** @return Collection<int, Transaction> */
    private function legsFor(Transaction $leg): Collection
    {
        if ($leg->transfer_group_id === null) {
            throw new RuntimeException('That transaction is not part of a transfer.');
        }

        return Transaction::where('transfer_group_id', $leg->transfer_group_id)->get()->collect();
    }

    private function resolveAccount(Account|int|null $account, string $which): Account
    {
        if ($account instanceof Account) {
            return $account;
        }

        if ($account === null) {
            throw new InvalidArgumentException("A {$which} account is required.");
        }

        return Account::findOrFail($account);
    }

    private function assertDistinctAccounts(Account $from, Account $to): void
    {
        if ($from->is($to)) {
            throw new InvalidArgumentException('A transfer needs two different accounts.');
        }
    }

    private function assertPositiveAmount(mixed $amount): void
    {
        if ($amount === null || ! is_numeric($amount) || bccomp((string) $amount, '0', 2) !== 1) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }
    }
}
