<?php

namespace App\Services;

use App\Enums\LegRole;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Creates, edits and voids single-leg transactions (expenses, income).
 *
 * Two-legged events — transfers and credit-card bill payments — belong to
 * TransferService and its Phase 2 sibling; this service refuses them so a
 * transfer can never be written as a lone leg with a dangling counterpart.
 */
class TransactionService
{
    public function __construct(
        private readonly AccountBalanceService $balances,
    ) {}

    /**
     * Record money consumed (spec Decision A: recognised when the purchase
     * happens, regardless of how it is later paid for).
     *
     * A card purchase and a bank purchase differ only in which account is named
     * — the card's liability grows, the bank's balance falls, and both count as
     * spending on the same date.
     */
    public function recordExpense(array $data): Transaction
    {
        return $this->createSingleLeg(TransactionType::Expense, $data);
    }

    /** Record money received. */
    public function recordIncome(array $data): Transaction
    {
        return $this->createSingleLeg(TransactionType::Income, $data);
    }

    private function createSingleLeg(TransactionType $type, array $data): Transaction
    {
        if ($type->isTwoLegged()) {
            throw new InvalidArgumentException(
                "{$type->value} is a two-legged event and must be created through its own service."
            );
        }

        $account = $this->resolveAccount($data['account_id'] ?? null);
        $this->assertPositiveAmount($data['amount'] ?? null);
        $this->assertSubcategoryBelongsToCategory($data);

        return DB::transaction(function () use ($type, $data, $account) {
            $transaction = Transaction::create([
                'transaction_date' => $data['transaction_date'],
                'type' => $type,
                'leg_role' => LegRole::Single,
                'account_id' => $account->getKey(),
                'amount' => $data['amount'],
                'balance_effect' => $this->balances->effectFor($type, LegRole::Single, $account->normal_balance),
                'category_id' => $data['category_id'] ?? null,
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'payer_id' => $data['payer_id'] ?? null,
                'beneficiary_id' => $data['beneficiary_id'] ?? null,
                'merchant_id' => $data['merchant_id'] ?? null,
                'planned_status' => $data['planned_status'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'reference' => $data['reference'] ?? null,
                'source' => $data['source'] ?? 'manual',
            ]);

            $this->syncTags($transaction, $data);
            $this->syncSplits($transaction, $data);
            $this->balances->recalculate($account);

            return $transaction->refresh();
        });
    }

    /**
     * Edit a posted transaction, keeping every affected balance correct
     * (spec Rule 6).
     *
     * Because balances are always re-derived by summing live rows, an edit is
     * "update the row, recalculate the accounts it touched" — no cascading
     * writes, and no chance of a stale total left behind.
     */
    public function update(Transaction $transaction, array $data): Transaction
    {
        if ($transaction->transfer_group_id !== null) {
            throw new RuntimeException(
                'This transaction is one leg of a linked pair; edit it through the service that owns the pair '
                .'so both legs stay in step.'
            );
        }

        $originalAccount = $transaction->account;
        $targetAccount = array_key_exists('account_id', $data)
            ? $this->resolveAccount($data['account_id'])
            : $originalAccount;

        if (array_key_exists('amount', $data)) {
            $this->assertPositiveAmount($data['amount']);
        }

        $this->assertSubcategoryBelongsToCategory($data, $transaction);

        return DB::transaction(function () use ($transaction, $data, $originalAccount, $targetAccount) {
            $attributes = collect($data)->only([
                'transaction_date', 'amount', 'category_id', 'subcategory_id', 'payer_id',
                'beneficiary_id', 'merchant_id', 'planned_status', 'purpose',
                'description', 'notes', 'reference',
            ])->all();

            $attributes['account_id'] = $targetAccount->getKey();

            // Moving between an asset and a liability account flips the direction
            // — e.g. re-pointing an expense from a bank account to a credit card.
            $attributes['balance_effect'] = $this->balances->effectFor(
                $transaction->type,
                $transaction->leg_role,
                $targetAccount->normal_balance,
            );

            $transaction->update($attributes);
            $this->syncTags($transaction, $data);
            $this->syncSplits($transaction->refresh(), $data);

            $this->balances->recalculateMany([$originalAccount, $targetAccount]);

            return $transaction->refresh();
        });
    }

    /**
     * Void a transaction (spec Rule 7 — controlled deletion, never destructive).
     *
     * The row stays in the database with its reason recorded, so the history
     * remains auditable; soft-delete scoping removes it from every balance and
     * report automatically.
     */
    public function void(Transaction $transaction, string $reason): Transaction
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A reason is required when voiding a transaction.');
        }

        return DB::transaction(function () use ($transaction, $reason) {
            $accounts = [];

            // Void both legs of a linked pair together, or the lone leg.
            foreach ($transaction->linkedLegs() as $leg) {
                $accounts[] = $leg->account_id;
                $leg->forceFill(['void_reason' => $reason])->save();
                $leg->delete();
            }

            $this->balances->recalculateMany($accounts);

            return $transaction->refresh();
        });
    }

    /** Reinstate a voided transaction and its linked legs. */
    public function restore(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction) {
            $accounts = [];

            $legs = $transaction->transfer_group_id === null
                ? collect([$transaction])
                : Transaction::withTrashed()->where('transfer_group_id', $transaction->transfer_group_id)->get();

            foreach ($legs as $leg) {
                $accounts[] = $leg->account_id;
                $leg->restore();
                $leg->forceFill(['void_reason' => null])->save();
            }

            $this->balances->recalculateMany($accounts);

            return $transaction->refresh();
        });
    }

    private function resolveAccount(Account|int|null $account): Account
    {
        if ($account instanceof Account) {
            return $account;
        }

        if ($account === null) {
            throw new InvalidArgumentException('An account is required.');
        }

        return Account::findOrFail($account);
    }

    private function assertPositiveAmount(mixed $amount): void
    {
        if ($amount === null || ! is_numeric($amount) || bccomp((string) $amount, '0', 2) !== 1) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }
    }

    /**
     * A subcategory must be a child of the chosen category, otherwise category
     * roll-ups would silently disagree with subcategory roll-ups.
     */
    private function assertSubcategoryBelongsToCategory(array $data, ?Transaction $existing = null): void
    {
        $subcategoryId = array_key_exists('subcategory_id', $data)
            ? $data['subcategory_id']
            : $existing?->subcategory_id;

        if ($subcategoryId === null) {
            return;
        }

        $categoryId = array_key_exists('category_id', $data)
            ? $data['category_id']
            : $existing?->category_id;

        $subcategory = Category::find($subcategoryId);

        if ($subcategory === null) {
            throw new InvalidArgumentException('The chosen subcategory does not exist.');
        }

        if ($subcategory->parent_id !== null && (int) $subcategory->parent_id === (int) $categoryId) {
            return;
        }

        throw new InvalidArgumentException(
            "Subcategory \"{$subcategory->name}\" does not belong to the chosen category."
        );
    }

    private function syncTags(Transaction $transaction, array $data): void
    {
        if (array_key_exists('tags', $data) && is_array($data['tags'])) {
            $transaction->tags()->sync($data['tags']);
        }
    }

    /**
     * Replace a transaction's category/beneficiary split.
     *
     * Splits refine reporting only — the parent row stays the single ledger leg,
     * so a split can never move the account balance twice. They must add up to
     * the parent amount exactly, otherwise category totals would quietly stop
     * matching the transaction list.
     */
    private function syncSplits(Transaction $transaction, array $data): void
    {
        if (! array_key_exists('splits', $data)) {
            return;
        }

        $splits = $data['splits'] ?? [];

        $transaction->splits()->delete();

        if ($splits === []) {
            return;
        }

        $total = '0';

        foreach ($splits as $split) {
            $this->assertPositiveAmount($split['amount'] ?? null);
            $total = bcadd($total, (string) $split['amount'], 2);
        }

        if (bccomp($total, (string) $transaction->amount, 2) !== 0) {
            throw new InvalidArgumentException(
                "Split amounts total {$total}, which does not match the transaction amount {$transaction->amount}."
            );
        }

        foreach ($splits as $split) {
            $transaction->splits()->create([
                'category_id' => $split['category_id'] ?? null,
                'subcategory_id' => $split['subcategory_id'] ?? null,
                'beneficiary_id' => $split['beneficiary_id'] ?? null,
                'amount' => $split['amount'],
                'notes' => $split['notes'] ?? null,
            ]);
        }
    }
}
