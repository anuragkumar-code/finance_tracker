<?php

namespace App\Services\Reporting;

use App\Enums\StatementStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\CreditCardStatement;
use App\Models\LoanPayment;
use App\Models\RecurringTransactionOccurrence;
use Illuminate\Support\Collection;

/**
 * What the household has already committed to, and what that leaves them
 * (spec sections 14 and 21).
 *
 * Three sources feed this: loan EMIs, recurring commitments, and unpaid card
 * bills. Each obligation is tagged as CONFIRMED (a fixed amount the household
 * has agreed to) or ESTIMATED (a forecast that may move), because presenting a
 * guess with the same confidence as a known EMI would make the "realistically
 * available" figure untrustworthy.
 */
class UpcomingObligationsService
{
    private const SCALE = 2;

    /**
     * Everything falling due in the next $days, ordered by date.
     *
     * @return Collection<int, object{
     *     source: string, label: string, detail: ?string, amount: string,
     *     due_date: \Carbon\CarbonInterface, is_estimated: bool, url: ?string
     * }>
     */
    public function forNextDays(int $days = 30): Collection
    {
        $from = today();
        $to = today()->addDays($days);

        return collect()
            ->merge($this->loanObligations($from, $to))
            ->merge($this->recurringObligations($from, $to))
            ->merge($this->cardObligations($from, $to))
            ->sortBy(fn (object $o) => $o->due_date->timestamp)
            ->values();
    }

    /** EMIs: a fixed, contractual amount — never an estimate. */
    private function loanObligations($from, $to): Collection
    {
        return LoanPayment::query()
            ->with('loan')
            ->scheduled()
            ->dueBetween($from, $to)
            ->whereHas('loan', fn ($q) => $q->active())
            ->get()
            ->map(fn (LoanPayment $p) => (object) [
                'source' => 'loan',
                'label' => $p->loan->name,
                'detail' => 'EMI '.$p->period_number.' of '.$p->loan->total_months,
                'amount' => (string) $p->amount,
                'due_date' => $p->due_date,
                'is_estimated' => false,
                'url' => route('loans.show', $p->loan),
            ]);
    }

    /** Recurring commitments: the amount is a forecast until confirmed. */
    private function recurringObligations($from, $to): Collection
    {
        return RecurringTransactionOccurrence::query()
            ->with('recurringTransaction')
            ->scheduled()
            ->whereBetween('due_date', [$from, $to])
            ->whereHas('recurringTransaction', fn ($q) => $q->active()
                ->where('type', TransactionType::Expense->value))
            ->get()
            ->map(fn (RecurringTransactionOccurrence $o) => (object) [
                'source' => 'recurring',
                'label' => $o->recurringTransaction->name,
                'detail' => $o->recurringTransaction->frequency->label(),
                'amount' => (string) $o->amount,
                'due_date' => $o->due_date,
                // Bills move month to month, so this is a forecast.
                'is_estimated' => true,
                'url' => route('recurring.index'),
            ]);
    }

    /** Card bills: known once the statement is issued. */
    private function cardObligations($from, $to): Collection
    {
        return CreditCardStatement::query()
            ->with('creditCard')
            ->whereNot('status', StatementStatus::Paid->value)
            ->whereBetween('due_date', [$from, $to])
            ->get()
            ->filter(fn (CreditCardStatement $s) => bccomp($s->balanceRemaining(), '0', self::SCALE) === 1)
            ->map(fn (CreditCardStatement $s) => (object) [
                'source' => 'credit_card',
                'label' => $s->creditCard->card_name,
                'detail' => 'Card bill',
                'amount' => $s->balanceRemaining(),
                'due_date' => $s->due_date,
                'is_estimated' => false,
                'url' => route('credit-cards.statements.show', [$s->creditCard, $s]),
            ]);
    }

    public function totalFor(int $days = 30): string
    {
        return $this->forNextDays($days)
            ->reduce(fn (string $carry, object $o) => bcadd($carry, $o->amount, self::SCALE), '0.00');
    }

    /**
     * Spec section 21 — the number that matters more than the bank balance.
     *
     * @return array{
     *     available: string, committed: string, realistic: string,
     *     estimated_portion: string, is_negative: bool
     * }
     */
    public function financialReality(int $days = 30): array
    {
        $available = bcadd(
            (string) Account::query()->active()->spendableCash()->sum('cached_balance'),
            '0',
            self::SCALE,
        );

        $obligations = $this->forNextDays($days);

        $committed = $obligations->reduce(
            fn (string $carry, object $o) => bcadd($carry, $o->amount, self::SCALE), '0.00'
        );

        $estimated = $obligations->where('is_estimated', true)->reduce(
            fn (string $carry, object $o) => bcadd($carry, $o->amount, self::SCALE), '0.00'
        );

        $realistic = bcsub($available, $committed, self::SCALE);

        return [
            'available' => $available,
            'committed' => $committed,
            'realistic' => $realistic,
            'estimated_portion' => $estimated,
            // Worth surfacing loudly: commitments exceed what is in the bank.
            'is_negative' => bccomp($realistic, '0', self::SCALE) === -1,
        ];
    }

    /** Anything already past its due date and still unconfirmed. */
    public function overdue(): Collection
    {
        $loans = LoanPayment::query()
            ->with('loan')
            ->scheduled()
            ->where('due_date', '<', today())
            ->whereHas('loan', fn ($q) => $q->active())
            ->get()
            ->map(fn (LoanPayment $p) => (object) [
                'source' => 'loan',
                'label' => $p->loan->name,
                'detail' => 'EMI '.$p->period_number,
                'amount' => (string) $p->amount,
                'due_date' => $p->due_date,
                'is_estimated' => false,
                'url' => route('loans.show', $p->loan),
            ]);

        $recurring = RecurringTransactionOccurrence::query()
            ->with('recurringTransaction')
            ->scheduled()
            ->where('due_date', '<', today())
            ->whereHas('recurringTransaction', fn ($q) => $q->active())
            ->get()
            ->map(fn (RecurringTransactionOccurrence $o) => (object) [
                'source' => 'recurring',
                'label' => $o->recurringTransaction->name,
                'detail' => 'Was due',
                'amount' => (string) $o->amount,
                'due_date' => $o->due_date,
                'is_estimated' => true,
                'url' => route('recurring.index'),
            ]);

        // Start from a base collection: merging two Eloquent collections of
        // plain objects would hit Eloquent's key-based merge and fail.
        return collect()
            ->merge($loans)
            ->merge($recurring)
            ->sortBy(fn (object $o) => $o->due_date->timestamp)
            ->values();
    }
}
