<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\Frequency;
use App\Enums\LoanStatus;
use App\Enums\Purpose;
use App\Enums\ScheduleStatus;
use App\Models\Account;
use App\Models\Loan;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Services\LoanService;
use App\Services\RecurringTransactionService;
use App\Services\Reporting\UpcomingObligationsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Loans in the simplified EMI-and-tenure form, recurring commitments, and the
 * "realistically available" figure they feed.
 */
class LoanAndCommitmentsTest extends TestCase
{
    use RefreshDatabase;

    private LoanService $loans;

    private RecurringTransactionService $recurring;

    private UpcomingObligationsService $upcoming;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixed "today" so schedule maths is deterministic.
        Carbon::setTestNow('2026-09-08');

        $this->loans = app(LoanService::class);
        $this->recurring = app(RecurringTransactionService::class);
        $this->upcoming = app(UpcomingObligationsService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function bank(string $opening = '200000'): Account
    {
        return Account::create([
            'name' => 'HDFC Bank',
            'type' => AccountType::Bank,
            'opening_balance' => $opening,
            'opening_balance_date' => '2026-01-01',
            'cached_balance' => $opening,
        ]);
    }

    private function landLoan(array $overrides = []): Loan
    {
        return $this->loans->create(array_merge([
            'name' => 'Land Loan',
            'lender' => 'HDFC',
            'emi_amount' => '32500',
            'total_months' => 60,
            'start_date' => '2026-01-12',
            'due_day' => 12,
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // Schedule
    // -----------------------------------------------------------------

    public function test_a_loan_lays_out_its_whole_schedule_and_derives_the_end_date(): void
    {
        $loan = $this->landLoan();

        $this->assertCount(60, $loan->payments);
        $this->assertSame('2026-01-12', $loan->payments->first()->due_date->toDateString());
        $this->assertSame('2030-12-12', $loan->end_date->toDateString());
        $this->assertSame('1950000.00', $loan->totalPayable());
    }

    public function test_instalments_already_due_are_marked_paid_without_inventing_bank_entries(): void
    {
        $bank = $this->bank();
        $loan = $this->landLoan();

        // Jan through Aug are behind us on 8 September; September's is due on the 12th.
        $this->assertSame(8, $loan->paidCount());
        $this->assertSame(52, $loan->remainingCount());
        $this->assertSame('260000.00', $loan->paidAmount());
        $this->assertSame('1690000.00', $loan->remainingAmount());

        // Crucially: no ledger entries were fabricated for those old payments.
        $this->assertSame(0, Transaction::count());
        $this->assertSame('200000.00', (string) $bank->refresh()->cached_balance);
    }

    public function test_past_instalments_can_be_left_unpaid_instead(): void
    {
        $loan = $this->loans->create([
            'name' => 'Bike Loan',
            'emi_amount' => '6500',
            'total_months' => 24,
            'start_date' => '2026-01-10',
            'due_day' => 10,
        ], markPastAsPaid: false);

        $this->assertSame(0, $loan->paidCount());
        $this->assertSame(24, $loan->remainingCount());
    }

    public function test_a_due_day_past_month_end_still_lands_on_a_real_date(): void
    {
        $loan = $this->loans->create([
            'name' => 'Month-end Loan',
            'emi_amount' => '1000',
            'total_months' => 4,
            'start_date' => '2027-01-31',
            'due_day' => 31,
        ], markPastAsPaid: false);

        $dates = $loan->payments->pluck('due_date')->map->toDateString()->all();

        // February has no 31st — the instalment lands on the 28th rather than
        // sliding into March.
        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30'], $dates);
    }

    public function test_a_loan_needs_a_positive_emi_and_at_least_one_month(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->loans->create([
            'name' => 'Bad Loan',
            'emi_amount' => '0',
            'total_months' => 12,
            'start_date' => '2026-01-01',
            'due_day' => 1,
        ]);
    }

    // -----------------------------------------------------------------
    // Paying an EMI
    // -----------------------------------------------------------------

    public function test_confirming_an_emi_reduces_the_bank_and_counts_as_spending(): void
    {
        $bank = $this->bank('200000');
        $loan = $this->landLoan();

        $next = $loan->nextPayment();
        $this->assertSame('2026-09-12', $next->due_date->toDateString());

        $this->loans->payInstalment($next, $bank, Carbon::parse('2026-09-12'));

        $this->assertSame('167500.00', (string) $bank->refresh()->cached_balance);

        // The household chose for EMIs to count as spending in full.
        $spending = (string) Transaction::query()->spending()
            ->inPeriod('2026-09-01', '2026-09-30')->sum('amount');
        $this->assertSame('32500.00', $spending);

        // Tagged so reports can still separate debt repayment out.
        $this->assertSame(Purpose::Debt, Transaction::first()->purpose);

        $loan->refresh();
        $this->assertSame(9, $loan->paidCount());
        $this->assertSame('1657500.00', $loan->remainingAmount());
    }

    public function test_an_emi_cannot_be_paid_from_a_credit_card(): void
    {
        $card = Account::create([
            'name' => 'HDFC Credit Card',
            'type' => AccountType::CreditCard,
            'opening_balance' => '0',
            'opening_balance_date' => '2026-01-01',
        ]);

        $loan = $this->landLoan();

        $this->expectException(InvalidArgumentException::class);

        $this->loans->payInstalment($loan->nextPayment(), $card);
    }

    public function test_the_same_instalment_cannot_be_paid_twice(): void
    {
        $bank = $this->bank();
        $loan = $this->landLoan();
        $next = $loan->nextPayment();

        $this->loans->payInstalment($next, $bank);

        $this->expectException(InvalidArgumentException::class);

        $this->loans->payInstalment($next->refresh(), $bank);
    }

    public function test_undoing_an_emi_restores_the_balance_and_the_schedule(): void
    {
        $bank = $this->bank('200000');
        $loan = $this->landLoan();
        $next = $loan->nextPayment();

        $this->loans->payInstalment($next, $bank, Carbon::parse('2026-09-12'));
        $this->loans->unpayInstalment($next->refresh(), 'Recorded by mistake');

        $this->assertSame('200000.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame(ScheduleStatus::Scheduled, $next->refresh()->status);
        $this->assertSame('0', (string) Transaction::query()->spending()->sum('amount'));
    }

    public function test_a_loan_closes_when_its_last_instalment_is_paid(): void
    {
        $bank = $this->bank();

        $loan = $this->loans->create([
            'name' => 'Short Loan',
            'emi_amount' => '1000',
            'total_months' => 2,
            'start_date' => '2026-09-10',
            'due_day' => 10,
        ], markPastAsPaid: false);

        foreach ($loan->payments as $instalment) {
            $this->loans->payInstalment($instalment, $bank);
        }

        $this->assertSame(LoanStatus::Closed, $loan->refresh()->status);
        $this->assertSame('0.00', $loan->remainingAmount());
    }

    // -----------------------------------------------------------------
    // Recurring commitments
    // -----------------------------------------------------------------

    public function test_a_recurring_commitment_forecasts_dates_without_posting_anything(): void
    {
        $bank = $this->bank();

        $rent = $this->recurring->create([
            'name' => 'Rent',
            'amount' => '25000',
            'frequency' => Frequency::Monthly,
            'next_due_date' => '2026-09-05',
            'account_id' => $bank->id,
        ]);

        $this->assertGreaterThan(3, $rent->occurrences->count());

        // Nothing has actually been paid — spec section 13.
        $this->assertSame(0, Transaction::count());
        $this->assertSame('200000.00', (string) $bank->refresh()->cached_balance);
    }

    public function test_confirming_a_commitment_writes_the_real_transaction(): void
    {
        $bank = $this->bank('200000');

        $rent = $this->recurring->create([
            'name' => 'Rent',
            'amount' => '25000',
            'frequency' => Frequency::Monthly,
            'next_due_date' => '2026-09-05',
            'account_id' => $bank->id,
        ]);

        $occurrence = $rent->occurrences()->first();

        $this->recurring->confirm($occurrence);

        $this->assertSame('175000.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame(ScheduleStatus::Paid, $occurrence->refresh()->status);
        $this->assertSame('25000.00', (string) Transaction::query()->spending()->sum('amount'));
    }

    public function test_a_commitment_can_be_confirmed_at_a_different_amount(): void
    {
        $bank = $this->bank('200000');

        $bill = $this->recurring->create([
            'name' => 'Electricity',
            'amount' => '3200',
            'frequency' => Frequency::Monthly,
            'next_due_date' => '2026-09-09',
            'account_id' => $bank->id,
        ]);

        // The real bill came in higher than the forecast.
        $this->recurring->confirm($bill->occurrences()->first(), '4100');

        $this->assertSame('195900.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame('4100.00', (string) Transaction::query()->spending()->sum('amount'));
    }

    public function test_skipping_a_commitment_posts_nothing(): void
    {
        $bank = $this->bank('200000');

        $sub = $this->recurring->create([
            'name' => 'Streaming',
            'amount' => '499',
            'frequency' => Frequency::Monthly,
            'next_due_date' => '2026-09-09',
            'account_id' => $bank->id,
        ]);

        $this->recurring->skip($sub->occurrences()->first(), 'Cancelled this month');

        $this->assertSame('200000.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame(0, Transaction::count());
    }

    public function test_generating_occurrences_twice_does_not_duplicate_dates(): void
    {
        $bank = $this->bank();

        $rent = $this->recurring->create([
            'name' => 'Rent',
            'amount' => '25000',
            'frequency' => Frequency::Monthly,
            'next_due_date' => '2026-09-05',
            'account_id' => $bank->id,
        ]);

        $before = $rent->occurrences()->count();
        $this->recurring->generateOccurrences($rent);

        $this->assertSame($before, $rent->refresh()->occurrences()->count());
    }

    public function test_a_commitment_stops_generating_past_its_end_date(): void
    {
        $bank = $this->bank();

        $emi = $this->recurring->create([
            'name' => 'Insurance',
            'amount' => '5000',
            'frequency' => Frequency::Monthly,
            'next_due_date' => '2026-09-15',
            'end_date' => '2026-11-30',
            'account_id' => $bank->id,
        ]);

        $this->assertSame(3, $emi->occurrences()->count());
    }

    // -----------------------------------------------------------------
    // Financial reality (spec section 21)
    // -----------------------------------------------------------------

    public function test_realistically_available_subtracts_what_is_already_committed(): void
    {
        $bank = $this->bank('135000');
        $this->landLoan();                       // 32,500 due on the 12th

        $this->recurring->create([
            'name' => 'Electricity',
            'amount' => '3200',
            'frequency' => Frequency::Monthly,
            'next_due_date' => '2026-09-09',
            'account_id' => $bank->id,
        ]);

        $reality = $this->upcoming->financialReality(30);

        $this->assertSame('135000.00', $reality['available']);
        // One EMI plus one electricity bill fall inside the next 30 days.
        $this->assertSame('35700.00', $reality['committed']);
        $this->assertSame('99300.00', $reality['realistic']);
        // The bill is a forecast; the EMI is not.
        $this->assertSame('3200.00', $reality['estimated_portion']);
        $this->assertFalse($reality['is_negative']);
    }

    public function test_it_says_so_when_commitments_exceed_available_cash(): void
    {
        $this->bank('20000');
        $this->landLoan();

        $reality = $this->upcoming->financialReality(30);

        $this->assertTrue($reality['is_negative']);
        $this->assertSame('-12500.00', $reality['realistic']);
    }

    public function test_obligations_list_marks_estimates_apart_from_fixed_amounts(): void
    {
        $bank = $this->bank();
        $this->landLoan();

        $this->recurring->create([
            'name' => 'Electricity',
            'amount' => '3200',
            'frequency' => Frequency::Monthly,
            'next_due_date' => '2026-09-09',
            'account_id' => $bank->id,
        ]);

        $obligations = $this->upcoming->forNextDays(30);

        $this->assertGreaterThanOrEqual(2, $obligations->count());

        $loan = $obligations->firstWhere('source', 'loan');
        $bill = $obligations->firstWhere('source', 'recurring');

        $this->assertFalse($loan->is_estimated);
        $this->assertTrue($bill->is_estimated);

        // Sorted by date: the 9th before the 12th.
        $this->assertTrue($obligations->first()->due_date->lessThanOrEqualTo($obligations->last()->due_date));
    }

    public function test_a_paid_emi_stops_being_an_upcoming_obligation(): void
    {
        $bank = $this->bank();
        $loan = $this->landLoan();

        $this->assertSame('32500.00', $this->loans->commitmentsBetween('2026-09-01', '2026-09-30'));

        $this->loans->payInstalment($loan->nextPayment(), $bank, Carbon::parse('2026-09-12'));

        $this->assertSame('0.00', $this->loans->commitmentsBetween('2026-09-01', '2026-09-30'));
    }

    public function test_overdue_commitments_are_surfaced(): void
    {
        $bank = $this->bank();

        $this->recurring->create([
            'name' => 'Rent',
            'amount' => '25000',
            'frequency' => Frequency::Monthly,
            'next_due_date' => '2026-09-05',   // three days ago
            'account_id' => $bank->id,
        ]);

        $overdue = $this->upcoming->overdue();

        $this->assertCount(1, $overdue);
        $this->assertSame('Rent', $overdue->first()->label);
    }
}
