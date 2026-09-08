<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\ScheduleStatus;
use App\Models\Account;
use App\Models\Loan;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Services\LoanService;
use App\Services\RecurringTransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-08');
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

    public function test_all_loan_and_upcoming_screens_load(): void
    {
        $bank = $this->bank();

        $loan = app(LoanService::class)->create([
            'name' => 'Land Loan',
            'emi_amount' => '32500',
            'total_months' => 60,
            'start_date' => '2026-01-12',
            'due_day' => 12,
            'payment_account_id' => $bank->id,
        ]);

        foreach ([
            '/loans',
            '/loans/create',
            "/loans/{$loan->id}",
            "/loans/{$loan->id}/edit",
            '/recurring',
            '/upcoming',
            '/upcoming?days=7',
            '/upcoming?days=90',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_these_screens_are_fine_with_no_data(): void
    {
        $this->get('/loans')->assertOk()->assertSee('No loans yet');
        $this->get('/recurring')->assertOk();
        $this->get('/upcoming')->assertOk()->assertSee('Realistically available');
    }

    public function test_creating_a_loan_through_the_form_builds_the_schedule(): void
    {
        $bank = $this->bank();

        $this->post('/loans', [
            'name' => 'Bike Loan',
            'lender' => 'HDFC',
            'emi_amount' => '6500',
            'total_months' => 24,
            'start_date' => '2026-03-10',
            'due_day' => 10,
            'payment_account_id' => $bank->id,
            'mark_past_as_paid' => '1',
        ])->assertRedirect();

        $loan = Loan::where('name', 'Bike Loan')->firstOrFail();

        $this->assertSame(24, $loan->payments()->count());
        $this->assertSame('2028-02-10', $loan->end_date->toDateString());

        // Mar–Aug are behind us; Sep's is due on the 10th, already past on the 8th? No —
        // the 10th is still ahead, so six instalments are marked paid.
        $this->assertSame(6, $loan->paidCount());

        // No fabricated bank history for those.
        $this->assertSame(0, Transaction::count());
        $this->assertSame('200000.00', (string) $bank->refresh()->cached_balance);
    }

    public function test_recording_an_emi_through_the_form_moves_the_balance(): void
    {
        $bank = $this->bank('200000');

        $loan = app(LoanService::class)->create([
            'name' => 'Land Loan',
            'emi_amount' => '32500',
            'total_months' => 60,
            'start_date' => '2026-01-12',
            'due_day' => 12,
        ]);

        $next = $loan->nextPayment();

        $this->post("/loans/{$loan->id}/instalments/{$next->id}/pay", [
            'account_id' => $bank->id,
            'payment_date' => '2026-09-12',
        ])->assertRedirect();

        $this->assertSame('167500.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame(ScheduleStatus::Paid, $next->refresh()->status);
    }

    public function test_a_loans_emi_and_tenure_cannot_be_edited_away(): void
    {
        $loan = app(LoanService::class)->create([
            'name' => 'Land Loan',
            'emi_amount' => '32500',
            'total_months' => 60,
            'start_date' => '2026-01-12',
            'due_day' => 12,
        ]);

        // The update route accepts only descriptive fields; EMI changes are ignored.
        $this->put("/loans/{$loan->id}", [
            'name' => 'Land Loan (renamed)',
            'emi_amount' => '999',
            'total_months' => 6,
        ])->assertRedirect();

        $loan->refresh();

        $this->assertSame('Land Loan (renamed)', $loan->name);
        $this->assertSame('32500.00', (string) $loan->emi_amount);
        $this->assertSame(60, $loan->total_months);
    }

    public function test_adding_a_recurring_commitment_forecasts_without_posting(): void
    {
        $bank = $this->bank();

        $this->post('/recurring', [
            'name' => 'Rent',
            'type' => 'expense',
            'amount' => '25000',
            'frequency' => 'monthly',
            'next_due_date' => '2026-09-15',
            'account_id' => $bank->id,
        ])->assertRedirect();

        $rent = RecurringTransaction::where('name', 'Rent')->firstOrFail();

        $this->assertGreaterThan(0, $rent->occurrences()->count());
        $this->assertSame(0, Transaction::count());
        $this->assertSame('200000.00', (string) $bank->refresh()->cached_balance);
    }

    public function test_confirming_a_commitment_through_the_form_records_it(): void
    {
        $bank = $this->bank('200000');

        $rent = app(RecurringTransactionService::class)->create([
            'name' => 'Rent',
            'type' => 'expense',
            'amount' => '25000',
            'frequency' => 'monthly',
            'next_due_date' => '2026-09-15',
            'account_id' => $bank->id,
        ]);

        $occurrence = $rent->occurrences()->first();

        $this->post("/recurring/occurrences/{$occurrence->id}/confirm", [
            'amount' => '26000',
        ])->assertRedirect();

        $this->assertSame('174000.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame('26000.00', (string) Transaction::query()->spending()->sum('amount'));
    }

    public function test_the_dashboard_shows_realistically_available(): void
    {
        $bank = $this->bank('135000');

        app(LoanService::class)->create([
            'name' => 'Land Loan',
            'emi_amount' => '32500',
            'total_months' => 60,
            'start_date' => '2026-01-12',
            'due_day' => 12,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Realistically available')
            ->assertSee('Land Loan');
    }
}
