<?php

namespace App\Services;

use App\Enums\LegRole;
use App\Enums\StatementStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Models\CreditCardStatement;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Credit-card accounting (spec section 4 — the most important rule in the app).
 *
 * Three separate events, never conflated:
 *
 *   1. A PURCHASE is an expense on the purchase date. It raises what is owed on
 *      the card and touches no bank account. Handled by TransactionService like
 *      any other expense — nothing card-specific is needed.
 *   2. A STATEMENT groups purchases that were already recorded. It creates zero
 *      transaction rows. It is a summary, not a financial event.
 *   3. A PAYMENT moves money from a bank account and reduces what is owed. It
 *      creates two linked legs, neither of them an expense.
 *
 * Because a payment is never type='expense', the purchases it settles cannot be
 * counted a second time. That is structural, not a filter to remember.
 */
class CreditCardService
{
    private const SCALE = 2;

    public function __construct(
        private readonly AccountBalanceService $balances,
    ) {}

    // -----------------------------------------------------------------
    // Statements
    // -----------------------------------------------------------------

    /**
     * Purchases in the open cycle that no statement covers yet.
     *
     * @return Collection<int, Transaction>
     */
    public function unbilledTransactions(CreditCard $card, ?CarbonInterface $upTo = null): Collection
    {
        $billedIds = DB::table('credit_card_statement_items')
            ->join('credit_card_statements', 'credit_card_statements.id', '=', 'credit_card_statement_items.statement_id')
            ->where('credit_card_statements.credit_card_id', $card->id)
            ->pluck('credit_card_statement_items.transaction_id');

        return Transaction::query()
            ->where('account_id', $card->account_id)
            ->whereNotIn('id', $billedIds)
            ->when($upTo, fn ($q) => $q->where('transaction_date', '<=', $upTo))
            // A bill payment reduces the card balance but is not a statement
            // line item; only purchases and refunds belong on a statement.
            ->whereNull('credit_card_payment_id')
            ->orderBy('transaction_date')
            ->get();
    }

    /**
     * What a statement for this period would total, before it is created.
     * Used to pre-fill the form so the household can check it against the real
     * statement rather than trust the app blindly.
     */
    public function previewStatementAmount(CreditCard $card, CarbonInterface $periodStart, CarbonInterface $periodEnd): string
    {
        $rows = Transaction::query()
            ->where('account_id', $card->account_id)
            ->whereBetween('transaction_date', [$periodStart, $periodEnd])
            ->whereNull('credit_card_payment_id')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN balance_effect = 'increase' THEN amount ELSE 0 END), 0) AS charges,
                 COALESCE(SUM(CASE WHEN balance_effect = 'decrease' THEN amount ELSE 0 END), 0) AS credits"
            )->first();

        return bcsub((string) $rows->charges, (string) $rows->credits, self::SCALE);
    }

    /**
     * Create a statement by grouping existing transactions.
     *
     * Inserts rows in credit_card_statements and credit_card_statement_items,
     * and deliberately none in transactions.
     *
     * $statementAmount lets the household enter the bank's actual figure. If it
     * differs from what the app grouped, that difference is real information —
     * a missed or late-posting purchase — and is surfaced rather than hidden.
     */
    public function generateStatement(
        CreditCard $card,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        ?CarbonInterface $statementDate = null,
        ?CarbonInterface $dueDate = null,
        ?string $statementAmount = null,
        ?string $minimumDue = null,
        ?string $notes = null,
    ): CreditCardStatement {
        if ($periodEnd->lessThan($periodStart)) {
            throw new InvalidArgumentException('The statement period ends before it starts.');
        }

        $statementDate ??= $periodEnd;
        $dueDate ??= $card->dueDateFor(Carbon::parse($statementDate));

        $exists = CreditCardStatement::where('credit_card_id', $card->id)
            ->where('period_start', $periodStart->toDateString())
            ->where('period_end', $periodEnd->toDateString())
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('A statement already covers that period for this card.');
        }

        return DB::transaction(function () use (
            $card, $periodStart, $periodEnd, $statementDate, $dueDate, $statementAmount, $minimumDue, $notes
        ) {
            $grouped = $this->previewStatementAmount($card, $periodStart, $periodEnd);

            $statement = CreditCardStatement::create([
                'credit_card_id' => $card->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'statement_date' => $statementDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'statement_amount' => $statementAmount ?? $grouped,
                'carried_balance' => '0.00',
                'minimum_due' => $minimumDue,
                'status' => StatementStatus::Generated,
                'notes' => $notes,
            ]);

            $this->linkTransactions($statement, $card, $periodStart, $periodEnd);

            return $statement->refresh();
        });
    }

    /**
     * Re-link a statement to its transactions after one of them was edited.
     * Idempotent: clears the old links and rebuilds them.
     */
    public function regenerateItems(CreditCardStatement $statement): CreditCardStatement
    {
        return DB::transaction(function () use ($statement) {
            $statement->items()->delete();

            $this->linkTransactions(
                $statement,
                $statement->creditCard,
                $statement->period_start,
                $statement->period_end,
            );

            return $statement->refresh();
        });
    }

    private function linkTransactions(
        CreditCardStatement $statement,
        CreditCard $card,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
    ): void {
        $transactions = Transaction::query()
            ->where('account_id', $card->account_id)
            ->whereBetween('transaction_date', [$periodStart, $periodEnd])
            ->whereNull('credit_card_payment_id')
            ->get();

        foreach ($transactions as $transaction) {
            $statement->items()->create([
                'transaction_id' => $transaction->id,
                // Snapshot, so statement history stays stable even if the
                // underlying transaction is later corrected.
                'amount_snapshot' => $transaction->amount,
            ]);
        }
    }

    /**
     * Difference between the figure on the statement and what the app has
     * grouped into it. Non-zero means something is missing or extra — worth the
     * household's attention, never silently reconciled away.
     */
    public function statementDiscrepancy(CreditCardStatement $statement): string
    {
        $linked = (string) bcadd((string) $statement->items()->sum('amount_snapshot'), '0', self::SCALE);

        return bcsub((string) $statement->statement_amount, $linked, self::SCALE);
    }

    // -----------------------------------------------------------------
    // Payments
    // -----------------------------------------------------------------

    /**
     * Pay a card bill: bank goes down, amount owed goes down, spending
     * unchanged (spec Decision B).
     */
    public function pay(
        CreditCard $card,
        Account $sourceAccount,
        string $amount,
        CarbonInterface $paymentDate,
        ?CreditCardStatement $statement = null,
        ?string $notes = null,
    ): CreditCardPayment {
        if (! is_numeric($amount) || bccomp($amount, '0', self::SCALE) !== 1) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        if ($sourceAccount->id === $card->account_id) {
            throw new InvalidArgumentException('A card cannot pay its own bill.');
        }

        if ($sourceAccount->isLiability()) {
            throw new InvalidArgumentException('Card bills are paid from a bank or cash account.');
        }

        if ($statement !== null && $statement->credit_card_id !== $card->id) {
            throw new InvalidArgumentException('That statement belongs to a different card.');
        }

        return DB::transaction(function () use ($card, $sourceAccount, $amount, $paymentDate, $statement, $notes) {
            $groupId = (string) Str::uuid();

            $payment = CreditCardPayment::create([
                'credit_card_id' => $card->id,
                'statement_id' => $statement?->id,
                'source_account_id' => $sourceAccount->id,
                'payment_date' => $paymentDate->toDateString(),
                'amount' => $amount,
                'transfer_group_id' => $groupId,
                'notes' => $notes,
            ]);

            $cardAccount = $card->account;
            $description = 'Card bill payment — '.$card->card_name;

            // Both legs decrease: the bank loses money, and the debt shrinks.
            // Neither is type='expense'.
            $this->writeLeg($payment, $sourceAccount, LegRole::PaymentFrom, $amount, $paymentDate, $description, $groupId);
            $this->writeLeg($payment, $cardAccount, LegRole::PaymentTo, $amount, $paymentDate, $description, $groupId);

            $this->balances->recalculateMany([$sourceAccount, $cardAccount]);

            if ($statement !== null) {
                $this->refreshStatementStatus($statement->refresh());
            }

            return $payment->refresh();
        });
    }

    private function writeLeg(
        CreditCardPayment $payment,
        Account $account,
        LegRole $role,
        string $amount,
        CarbonInterface $date,
        string $description,
        string $groupId,
    ): Transaction {
        return Transaction::create([
            'transaction_date' => $date->toDateString(),
            'type' => TransactionType::LiabilityPayment,
            'leg_role' => $role,
            'transfer_group_id' => $groupId,
            'account_id' => $account->id,
            'amount' => $amount,
            'balance_effect' => $this->balances->effectFor(
                TransactionType::LiabilityPayment, $role, $account->normal_balance
            ),
            'description' => $description,
            'credit_card_payment_id' => $payment->id,
            'source' => 'manual',
        ]);
    }

    /**
     * Void a payment: both legs and the payment record go together, and the
     * statement returns to its unpaid state.
     */
    public function voidPayment(CreditCardPayment $payment, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A reason is required when voiding a payment.');
        }

        DB::transaction(function () use ($payment, $reason) {
            $accounts = $payment->legs->pluck('account_id')->all();

            foreach ($payment->legs as $leg) {
                $leg->forceFill(['void_reason' => $reason])->save();
                $leg->delete();
            }

            $statement = $payment->statement;

            $payment->delete();

            $this->balances->recalculateMany($accounts);

            if ($statement !== null) {
                $this->refreshStatementStatus($statement->refresh());
            }
        });
    }

    /** Recompute a statement's status from what has actually been paid against it. */
    public function refreshStatementStatus(CreditCardStatement $statement): CreditCardStatement
    {
        $due = $statement->totalDue();
        $paid = $statement->amountPaid();

        $status = match (true) {
            bccomp($paid, $due, self::SCALE) >= 0 && bccomp($due, '0', self::SCALE) >= 0
                => StatementStatus::Paid,
            bccomp($paid, '0', self::SCALE) === 1
                => $statement->due_date->isPast() ? StatementStatus::Overdue : StatementStatus::PartiallyPaid,
            $statement->due_date->isPast()
                => StatementStatus::Overdue,
            default => StatementStatus::Generated,
        };

        $statement->update(['status' => $status]);

        return $statement;
    }

    // -----------------------------------------------------------------
    // Reporting
    // -----------------------------------------------------------------

    /** Purchases charged to this card in a period — never payments. */
    public function spendingInPeriod(CreditCard $card, string $start, string $end): string
    {
        return (string) bcadd(
            (string) Transaction::query()
                ->spending()
                ->where('account_id', $card->account_id)
                ->inPeriod($start, $end)
                ->sum('amount'),
            '0',
            self::SCALE,
        );
    }

    /** Bill payments made in a period — reported separately from spending (spec 19F). */
    public function paymentsInPeriod(CreditCard $card, string $start, string $end): string
    {
        return (string) bcadd(
            (string) CreditCardPayment::where('credit_card_id', $card->id)
                ->whereBetween('payment_date', [$start, $end])
                ->sum('amount'),
            '0',
            self::SCALE,
        );
    }

    /**
     * Statements awaiting payment, for the upcoming-obligations view.
     *
     * @return Collection<int, CreditCardStatement>
     */
    public function upcomingDues(int $withinDays = 30): Collection
    {
        return CreditCardStatement::query()
            ->with('creditCard.account')
            ->whereNotIn('status', [StatementStatus::Paid->value])
            ->whereBetween('due_date', [today(), today()->addDays($withinDays)])
            ->orderBy('due_date')
            ->get();
    }
}
