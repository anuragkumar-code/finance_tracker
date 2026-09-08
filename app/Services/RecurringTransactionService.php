<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Enums\TransactionType;
use App\Models\RecurringTransaction;
use App\Models\RecurringTransactionOccurrence;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Regular commitments (spec section 13).
 *
 * The rule that shapes this service: the app generates EXPECTED entries and
 * never assumes they were paid. An occurrence sits as 'scheduled' until someone
 * confirms it, at which point — and only then — a real transaction is written.
 */
class RecurringTransactionService
{
    private const SCALE = 2;

    /** How far ahead occurrences are materialised. */
    private const HORIZON_DAYS = 120;

    public function __construct(
        private readonly TransactionService $transactions,
    ) {}

    public function create(array $data): RecurringTransaction
    {
        $recurring = RecurringTransaction::create($data + ['is_active' => true]);

        $this->generateOccurrences($recurring);

        return $recurring->refresh();
    }

    public function update(RecurringTransaction $recurring, array $data): RecurringTransaction
    {
        return DB::transaction(function () use ($recurring, $data) {
            $recurring->update($data);

            // Unconfirmed occurrences are forecasts, so they follow the template.
            // Confirmed ones are history and are left alone.
            $recurring->occurrences()
                ->where('status', ScheduleStatus::Scheduled->value)
                ->delete();

            $this->generateOccurrences($recurring->refresh());

            return $recurring->refresh();
        });
    }

    /**
     * Materialise expected occurrences up to the horizon.
     *
     * Idempotent — a unique key on (recurring_transaction_id, due_date) means
     * re-running never duplicates a date.
     */
    public function generateOccurrences(RecurringTransaction $recurring, ?CarbonInterface $until = null): int
    {
        if (! $recurring->is_active) {
            return 0;
        }

        $until ??= now()->addDays(self::HORIZON_DAYS);
        $cursor = Carbon::parse($recurring->next_due_date)->startOfDay();
        $created = 0;
        $guard = 0;

        while ($cursor->lessThanOrEqualTo($until)) {
            if ($recurring->end_date !== null && $cursor->greaterThan($recurring->end_date)) {
                break;
            }

            // Safety valve: a misconfigured frequency must not spin forever.
            if (++$guard > 1000) {
                break;
            }

            $exists = $recurring->occurrences()
                ->whereDate('due_date', $cursor->toDateString())
                ->exists();

            if (! $exists) {
                $recurring->occurrences()->create([
                    'due_date' => $cursor->toDateString(),
                    'amount' => $recurring->amount,
                    'status' => ScheduleStatus::Scheduled,
                ]);
                $created++;
            }

            $cursor = Carbon::parse($recurring->frequency->advance($cursor));
        }

        return $created;
    }

    /**
     * Confirm that a scheduled commitment actually happened, writing the real
     * transaction at that point.
     */
    public function confirm(
        RecurringTransactionOccurrence $occurrence,
        ?string $amount = null,
        ?CarbonInterface $paidOn = null,
    ): RecurringTransactionOccurrence {
        if ($occurrence->status === ScheduleStatus::Paid) {
            throw new InvalidArgumentException('That commitment is already recorded.');
        }

        $recurring = $occurrence->recurringTransaction;
        $amount ??= (string) $occurrence->amount;
        $paidOn ??= $occurrence->due_date;

        return DB::transaction(function () use ($occurrence, $recurring, $amount, $paidOn) {
            $payload = [
                'transaction_date' => $paidOn->toDateString(),
                'account_id' => $recurring->account_id,
                'amount' => $amount,
                'category_id' => $recurring->category_id,
                'subcategory_id' => $recurring->subcategory_id,
                'payer_id' => $recurring->payer_id,
                'beneficiary_id' => $recurring->beneficiary_id,
                'merchant_id' => $recurring->merchant_id,
                'purpose' => $recurring->purpose?->value,
                'planned_status' => $recurring->planned_status?->value,
                'description' => $recurring->name,
                'source' => 'recurring',
            ];

            $transaction = $recurring->type === TransactionType::Income
                ? $this->transactions->recordIncome($payload)
                : $this->transactions->recordExpense($payload);

            $transaction->forceFill(['recurring_transaction_id' => $recurring->id])->save();

            $occurrence->update([
                'status' => ScheduleStatus::Paid,
                'amount' => $amount,
                'transaction_id' => $transaction->id,
            ]);

            $this->advanceNextDueDate($recurring);

            return $occurrence->refresh();
        });
    }

    /** Skip a commitment that did not happen this cycle, without posting anything. */
    public function skip(RecurringTransactionOccurrence $occurrence, ?string $reason = null): RecurringTransactionOccurrence
    {
        $occurrence->update([
            'status' => ScheduleStatus::Skipped,
            'notes' => $reason,
        ]);

        $this->advanceNextDueDate($occurrence->recurringTransaction);

        return $occurrence->refresh();
    }

    /** Undo a confirmation, voiding the transaction it created. */
    public function unconfirm(RecurringTransactionOccurrence $occurrence, string $reason): RecurringTransactionOccurrence
    {
        return DB::transaction(function () use ($occurrence, $reason) {
            if ($occurrence->transaction !== null) {
                $this->transactions->void($occurrence->transaction, $reason);
            }

            $occurrence->update([
                'status' => ScheduleStatus::Scheduled,
                'transaction_id' => null,
                'notes' => $reason,
            ]);

            return $occurrence->refresh();
        });
    }

    /** Point next_due_date at the earliest still-unconfirmed occurrence. */
    private function advanceNextDueDate(RecurringTransaction $recurring): void
    {
        $next = $recurring->occurrences()
            ->where('status', ScheduleStatus::Scheduled->value)
            ->orderBy('due_date')
            ->first();

        if ($next !== null) {
            $recurring->update(['next_due_date' => $next->due_date]);

            return;
        }

        // Nothing left in the horizon — extend it so the commitment keeps
        // appearing in upcoming obligations.
        $this->generateOccurrences($recurring);
    }

    /** Regenerate the horizon for every active commitment. */
    public function refreshAll(): int
    {
        $created = 0;

        foreach (RecurringTransaction::query()->active()->cursor() as $recurring) {
            $created += $this->generateOccurrences($recurring);
        }

        return $created;
    }

    /** Total expected from recurring commitments in a window. */
    public function commitmentsBetween(string $start, string $end): string
    {
        return bcadd(
            (string) RecurringTransactionOccurrence::query()
                ->scheduled()
                ->whereBetween('due_date', [$start, $end])
                ->whereHas('recurringTransaction', fn ($q) => $q->active()
                    ->where('type', TransactionType::Expense->value))
                ->sum('amount'),
            '0',
            self::SCALE,
        );
    }
}
