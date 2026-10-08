<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Transaction;
use App\Services\LoanService;
use App\Services\Reporting\SpendingReportService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app tracks what leaves, not what arrives.
 *
 * A month of real use showed expenses recorded faithfully and income almost
 * never, so every derived bank balance drifted. These are the rules that
 * replaced it: bank accounts carry no balance, cards still do, and money that
 * merely moves (investments) is counted apart from money consumed.
 */
class ExpenseFirstTest extends TestCase
{
    use RefreshDatabase;

    private Account $bank;

    private Account $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bank = Account::create([
            'name' => 'HDFC', 'type' => 'bank', 'opening_balance' => '0',
            'opening_balance_date' => '2026-01-01', 'cached_balance' => '0',
        ]);

        $this->card = Account::create([
            'name' => 'Scapia', 'type' => 'credit_card', 'opening_balance' => '0',
            'opening_balance_date' => '2026-01-01', 'cached_balance' => '0',
        ]);
    }

    private function spend(string $amount, ?Category $category = null, ?Account $account = null): Transaction
    {
        return app(TransactionService::class)->recordExpense([
            'transaction_date' => '2026-10-05',
            'account_id' => ($account ?? $this->bank)->id,
            'amount' => $amount,
            'category_id' => $category?->id,
        ]);
    }

    // -----------------------------------------------------------------
    // Which accounts keep a balance
    // -----------------------------------------------------------------

    public function test_bank_cash_and_cards_are_modes_of_payment_with_no_balance(): void
    {
        $this->assertFalse($this->bank->refresh()->tracksBalance());
        $this->assertFalse($this->card->refresh()->tracksBalance());

        $this->assertTrue(Account::paymentSources()->whereKey($this->bank->id)->exists());
        $this->assertTrue(Account::paymentSources()->whereKey($this->card->id)->exists());
    }

    public function test_money_owed_between_people_still_keeps_a_balance(): void
    {
        // The one balance worth deriving: what a friend owes after a trip is
        // settled. It is not a mode of payment and has no statement of its own.
        $friend = \App\Models\Person::create(['name' => 'Vishal', 'is_external' => true]);
        $account = app(\App\Services\PersonBalanceService::class)->accountFor($friend);

        $this->assertTrue($account->tracksBalance());
        $this->assertTrue(Account::balanceTracked()->whereKey($account->id)->exists());
    }

    public function test_a_new_bank_account_needs_no_opening_balance(): void
    {
        $this->post('/accounts', ['name' => 'Kotak', 'type' => 'bank'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $account = Account::where('name', 'Kotak')->sole();

        $this->assertFalse($account->tracksBalance());
        $this->assertSame('0.00', (string) $account->opening_balance);
    }

    public function test_a_new_card_needs_no_opening_balance_either(): void
    {
        $this->post('/accounts', ['name' => 'IDFC', 'type' => 'credit_card'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $card = Account::where('name', 'IDFC')->sole();

        $this->assertFalse($card->tracksBalance());
    }

    public function test_no_bank_balance_is_shown_on_the_screens_that_used_to_lead_with_one(): void
    {
        $this->spend('1200');

        // A balance for this account would be a guess; what went out through it
        // is not.
        $this->get('/accounts')->assertOk()
            ->assertSee('Payment sources')
            ->assertSee('Out this month')
            ->assertDontSee('Net worth');

        $this->get('/')->assertOk()
            ->assertDontSee('Realistically available')
            ->assertDontSee('Received');

        $this->get('/accounts/'.$this->bank->id)->assertOk()
            ->assertSee('No balance is kept')
            ->assertDontSee('Opening balance');
    }

    public function test_spending_is_split_by_how_it_was_paid_for(): void
    {
        $this->spend('2500', null, $this->card);
        $this->spend('1500');

        $modes = app(SpendingReportService::class)->byPaymentMode('2026-10-01', '2026-10-31');

        $this->assertSame('2500.00', $modes->firstWhere('key', 'credit_card')->amount);
        $this->assertSame('1500.00', $modes->firstWhere('key', 'bank')->amount);

        // The mode is shown; what is owed on the card is not.
        $this->get('/')->assertOk()
            ->assertSee('How you paid')
            ->assertDontSee('Owed on cards');

        // And the split drills through to exactly those entries.
        $this->get('/transactions?account_type=credit_card')->assertOk()
            ->assertSee('2,500.00')
            ->assertDontSee('1,500.00');
    }

    // -----------------------------------------------------------------
    // Money moved is not money spent
    // -----------------------------------------------------------------

    public function test_investments_are_counted_apart_from_spending(): void
    {
        $investments = Category::whereNull('parent_id')->where('name', 'Investments')->sole();
        $food = Category::create(['name' => 'Food', 'applies_to' => 'expense']);

        $this->spend('1000', $food);
        $this->spend('18000', $investments);

        $reports = app(SpendingReportService::class);

        $this->assertSame('1000.00', $reports->totalSpending('2026-10-01', '2026-10-31'));
        $this->assertSame('18000.00', $reports->totalInvested('2026-10-01', '2026-10-31'));

        // The entry is still in the ledger and still on the account — it is
        // only counted differently.
        $this->assertSame(2, Transaction::count());
        $this->assertSame('-19000.00', (string) $this->bank->refresh()->cached_balance);
    }

    public function test_an_investment_does_not_appear_in_any_spending_cut(): void
    {
        $investments = Category::whereNull('parent_id')->where('name', 'Investments')->sole();
        $this->spend('18000', $investments);

        $reports = app(SpendingReportService::class);

        $this->assertTrue($reports->byCategory('2026-10-01', '2026-10-31')->isEmpty());
        $this->assertSame('0.00', $reports->creditCardSpending('2026-10-01', '2026-10-31'));
    }

    public function test_the_recategorise_command_reports_before_it_writes(): void
    {
        $entry = $this->spend('18000');
        $entry->update(['description' => 'paytm money']);

        $this->artisan('transactions:recategorise', ['--to' => 'Investments', '--match' => ['paytm']])
            ->expectsOutputToContain('would move')
            ->assertSuccessful();

        $this->assertNull($entry->refresh()->category_id);

        $this->artisan('transactions:recategorise', [
            '--to' => 'Investments', '--match' => ['paytm'], '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame('Investments', $entry->refresh()->category->name);
        // Only the category moved: the money did not.
        $this->assertSame('18000.00', (string) $entry->amount);
        $this->assertSame($this->bank->id, $entry->account_id);
    }

    public function test_the_recategorise_command_refuses_to_run_unfiltered(): void
    {
        $this->artisan('transactions:recategorise', ['--to' => 'Investments'])->assertFailed();
    }

    // -----------------------------------------------------------------
    // EMIs reach the ledger
    // -----------------------------------------------------------------

    private function loanWithPaidHistory(): Loan
    {
        return app(LoanService::class)->create([
            'name' => 'Land Loan',
            'emi_amount' => '39005',
            'total_months' => 12,
            'start_date' => '2026-07-07',
            'due_day' => 7,
        ]);
    }

    public function test_emis_marked_paid_as_history_can_be_given_their_missing_entries(): void
    {
        $loan = $this->loanWithPaidHistory();

        $unlinked = $loan->payments()->where('status', 'paid')->whereNull('transaction_id')->count();
        $this->assertGreaterThan(0, $unlinked, 'Past instalments are marked paid without an entry.');
        $this->assertSame('0.00', app(SpendingReportService::class)->totalSpending('2026-01-01', '2026-12-31'));

        $this->artisan('loans:backfill-emis', ['--map' => ['Land Loan=HDFC']])
            ->expectsOutputToContain('would write entry')
            ->assertSuccessful();

        // Dry run writes nothing.
        $this->assertSame(0, Transaction::count());

        $this->artisan('loans:backfill-emis', ['--map' => ['Land Loan=HDFC'], '--apply' => true])
            ->assertSuccessful();

        $this->assertSame($unlinked, Transaction::count());
        $this->assertSame(
            bcmul('39005', (string) $unlinked, 2),
            app(SpendingReportService::class)->totalSpending('2026-01-01', '2026-12-31'),
        );

        $this->assertSame(0, LoanPayment::where('status', 'paid')->whereNull('transaction_id')->count());
    }

    public function test_an_emi_typed_in_by_hand_is_linked_rather_than_written_twice(): void
    {
        $loan = $this->loanWithPaidHistory();
        $instalment = $loan->payments()->where('status', 'paid')->orderBy('due_date')->first();

        // The household recorded this one themselves before the instalment was
        // linked. Writing a second entry would double-count the payment.
        $byHand = app(TransactionService::class)->recordExpense([
            'transaction_date' => $instalment->payment_date->toDateString(),
            'account_id' => $this->bank->id,
            'amount' => '39005',
            'description' => 'Land Loan EMI '.$instalment->period_number.'/12',
        ]);

        $before = Transaction::count();

        $this->artisan('loans:backfill-emis', ['--map' => ['Land Loan=HDFC'], '--apply' => true])
            ->assertSuccessful();

        $this->assertSame($instalment->id, $byHand->refresh()->loan_payment_id);
        $this->assertSame($byHand->id, $instalment->refresh()->transaction_id);

        // One entry per instalment, never two for the same one.
        $paid = $loan->payments()->where('status', 'paid')->count();
        $this->assertSame($before + $paid - 1, Transaction::count());
    }

    public function test_marking_an_instalment_paid_still_records_the_spend(): void
    {
        $loan = $this->loanWithPaidHistory();
        $next = $loan->payments()->where('status', 'scheduled')->orderBy('due_date')->first();

        app(LoanService::class)->payInstalment($next, $this->bank, \Illuminate\Support\Carbon::parse('2026-10-03'));

        $this->assertSame('39005.00', app(SpendingReportService::class)->totalSpending('2026-10-01', '2026-10-31'));
        $this->assertNotNull($next->refresh()->transaction_id);
    }

    // -----------------------------------------------------------------
    // Income is out of the picture, but not out of the database
    // -----------------------------------------------------------------

    public function test_recorded_income_is_kept_but_never_shown(): void
    {
        app(TransactionService::class)->recordIncome([
            'transaction_date' => '2026-10-01',
            'account_id' => $this->bank->id,
            'amount' => '86000',
            'description' => 'Salary',
        ]);

        // Still in the ledger — nothing was thrown away.
        $this->assertSame('86000.00', app(SpendingReportService::class)->totalIncome('2026-10-01', '2026-10-31'));

        $this->get('/')->assertOk()->assertDontSee('86,000.00');
        $this->get('/reports')->assertOk()->assertDontSee('86,000.00');
        $this->get('/transactions')->assertOk()->assertDontSee('>Income<', false);
    }
}
