<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\Purpose;
use App\Enums\ScheduleStatus;
use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanPayment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Loans, in the simplified form the household chose: EMI amount, tenure in
 * months, start date and deduction day. No interest rate, no amortisation.
 *
 * Household decision (2026-09-08): a paid EMI counts as SPENDING in full,
 * rather than being excluded as debt repayment. That departs from spec Rule 3,
 * which treats principal as non-consumption — the trade-off was put to the
 * household and chosen deliberately. To keep both views available, every EMI
 * expense is tagged purpose='debt', so reports can still show "of which debt
 * repayment" without changing the headline number.
 */
class LoanService
{
    private const SCALE = 2;

    public function __construct(
        private readonly AccountBalanceService $balances,
        private readonly TransactionService $transactions,
    ) {}

    /**
     * Create a loan and lay out its full instalment schedule.
     *
     * $markPastAsPaid marks instalments already due as paid WITHOUT creating
     * bank transactions: those payments happened before the household started
     * using the app, and inventing ledger entries for them would corrupt
     * account balances that are anchored to an opening balance.
     */
    public function create(array $data, bool $markPastAsPaid = true): Loan
    {
        $emi = (string) $data['emi_amount'];
        $months = (int) $data['total_months'];

        if (! is_numeric($emi) || bccomp($emi, '0', self::SCALE) !== 1) {
            throw new InvalidArgumentException('EMI amount must be greater than zero.');
        }

        if ($months < 1) {
            throw new InvalidArgumentException('A loan needs at least one instalment.');
        }

        $startDate = Carbon::parse($data['start_date']);
        $dueDay = (int) $data['due_day'];

        return DB::transaction(function () use ($data, $emi, $months, $startDate, $dueDay, $markPastAsPaid) {
            $schedule = $this->buildSchedule($startDate, $dueDay, $months);

            $loan = Loan::create([
                'name' => $data['name'],
                'lender' => $data['lender'] ?? null,
                'emi_amount' => $emi,
                'total_months' => $months,
                'start_date' => $startDate->toDateString(),
                'end_date' => end($schedule)->toDateString(),
                'due_day' => $dueDay,
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'status' => LoanStatus::Active,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($schedule as $index => $dueDate) {
                $alreadyDue = $markPastAsPaid && $dueDate->lessThan(today());

                $loan->payments()->create([
                    'period_number' => $index + 1,
                    'due_date' => $dueDate->toDateString(),
                    'amount' => $emi,
                    'status' => $alreadyDue ? ScheduleStatus::Paid : ScheduleStatus::Scheduled,
                    // Paid before the app existed: recorded as history, with no
                    // ledger entry and no payment date claimed.
                    'payment_date' => $alreadyDue ? $dueDate->toDateString() : null,
                    'notes' => $alreadyDue ? 'Paid before tracking began' : null,
                ]);
            }

            return $loan->refresh();
        });
    }

    /**
     * Due dates for the whole tenure.
     *
     * The first instalment falls on the deduction day in the month the loan
     * starts, or the month after if that day has already passed.
     *
     * @return array<int, Carbon>
     */
    public function buildSchedule(CarbonInterface $startDate, int $dueDay, int $months): array
    {
        $first = $this->clampToMonth($startDate->copy(), $dueDay);

        if ($first->lessThan($startDate)) {
            $first = $this->clampToMonth($startDate->copy()->addMonthNoOverflow(), $dueDay);
        }

        $dates = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $first->copy()->addMonthsNoOverflow($i);
            $dates[] = $this->clampToMonth($month, $dueDay);
        }

        return $dates;
    }

    /** A loan due on the 31st still has an instalment in February. */
    private function clampToMonth(Carbon $month, int $dueDay): Carbon
    {
        return $month->copy()->setDay(min($dueDay, $month->daysInMonth))->startOfDay();
    }

    /**
     * Confirm that an instalment was actually paid.
     *
     * Creates one expense on the paying account (household decision: the whole
     * EMI counts as spending), tagged purpose='debt' so it stays separable.
     */
    public function payInstalment(
        LoanPayment $instalment,
        Account $account,
        ?CarbonInterface $paymentDate = null,
        ?string $amount = null,
    ): LoanPayment {
        if ($instalment->status === ScheduleStatus::Paid) {
            throw new InvalidArgumentException('That instalment is already recorded as paid.');
        }

        if ($account->isLiability()) {
            throw new InvalidArgumentException('An EMI is paid from a bank or cash account.');
        }

        $loan = $instalment->loan;
        $paymentDate ??= now();
        $amount ??= (string) $instalment->amount;

        return DB::transaction(function () use ($instalment, $loan, $account, $paymentDate, $amount) {
            $transaction = $this->transactions->recordExpense([
                'transaction_date' => $paymentDate->toDateString(),
                'account_id' => $account->id,
                'amount' => $amount,
                'category_id' => $loan->category_id,
                'purpose' => Purpose::Debt->value,
                'planned_status' => \App\Enums\PlannedStatus::Planned->value,
                'description' => $loan->name.' EMI '.$instalment->period_number.'/'.$loan->total_months,
            ]);

            $transaction->forceFill(['loan_payment_id' => $instalment->id])->save();

            $instalment->update([
                'status' => ScheduleStatus::Paid,
                'payment_date' => $paymentDate->toDateString(),
                'account_id' => $account->id,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
            ]);

            $this->closeIfFinished($loan->refresh());

            return $instalment->refresh();
        });
    }

    /** Undo a confirmed instalment, voiding its ledger entry if it had one. */
    public function unpayInstalment(LoanPayment $instalment, string $reason): LoanPayment
    {
        return DB::transaction(function () use ($instalment, $reason) {
            if ($instalment->transaction !== null) {
                $this->transactions->void($instalment->transaction, $reason);
            }

            $instalment->update([
                'status' => ScheduleStatus::Scheduled,
                'payment_date' => null,
                'transaction_id' => null,
                'account_id' => null,
                'amount' => $instalment->loan->emi_amount,
                'notes' => $reason,
            ]);

            $loan = $instalment->loan;

            if ($loan->status === LoanStatus::Closed) {
                $loan->update(['status' => LoanStatus::Active]);
            }

            return $instalment->refresh();
        });
    }

    /** Mark a loan closed once every instalment is settled. */
    public function closeIfFinished(Loan $loan): Loan
    {
        $outstanding = $loan->payments()
            ->where('status', ScheduleStatus::Scheduled->value)
            ->count();

        if ($outstanding === 0 && $loan->status !== LoanStatus::Closed) {
            $loan->update(['status' => LoanStatus::Closed]);
        }

        return $loan;
    }

    /** Total EMI commitment falling due in a window, across all running loans. */
    public function commitmentsBetween(string $start, string $end): string
    {
        return bcadd(
            (string) LoanPayment::query()
                ->scheduled()
                ->dueBetween($start, $end)
                ->whereHas('loan', fn ($q) => $q->active())
                ->sum('amount'),
            '0',
            self::SCALE,
        );
    }

    /** EMIs actually paid in a period — a subset of spending, tagged purpose=debt. */
    public function paidBetween(string $start, string $end): string
    {
        return bcadd(
            (string) LoanPayment::query()
                ->where('status', ScheduleStatus::Paid->value)
                ->whereBetween('payment_date', [$start, $end])
                ->whereNotNull('transaction_id')
                ->sum('amount'),
            '0',
            self::SCALE,
        );
    }
}
