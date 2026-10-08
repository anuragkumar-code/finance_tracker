<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Transaction;
use App\Services\LoanService;
use App\Services\Reporting\SpendingReportService;
use App\Services\TransactionCompletenessService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Catching up on half-labelled entries, and comparing one month with another.
 *
 * The rules worth pinning down: the list only asks for what a person can
 * actually supply, filling it in can never move money, and a month still
 * running is never compared against a whole one.
 */
class TidyUpAndCompareTest extends TestCase
{
    use RefreshDatabase;

    private Account $bank;

    private Category $food;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-08');

        $this->bank = Account::create([
            'name' => 'HDFC', 'type' => 'bank', 'opening_balance' => '0',
            'opening_balance_date' => '2026-01-01', 'cached_balance' => '0',
        ]);

        $this->food = Category::create(['name' => 'Food', 'applies_to' => 'expense']);
    }

    private function spend(array $attributes = []): Transaction
    {
        return app(TransactionService::class)->recordExpense(array_merge([
            'transaction_date' => '2026-10-05',
            'account_id' => $this->bank->id,
            'amount' => '500',
        ], $attributes));
    }

    private function completeness(): TransactionCompletenessService
    {
        return app(TransactionCompletenessService::class);
    }

    // -----------------------------------------------------------------
    // What counts as a gap
    // -----------------------------------------------------------------

    public function test_an_entry_missing_a_category_or_a_judgement_needs_you(): void
    {
        $bare = $this->spend();

        $gaps = collect($this->completeness()->gapsFor($bare))->pluck('key');

        $this->assertContains('category_id', $gaps);
        $this->assertContains('planned_status', $gaps);
        $this->assertContains('purpose', $gaps);

        $this->assertTrue($this->completeness()->query(TransactionCompletenessService::NEEDS)
            ->whereKey($bare->id)->exists());
    }

    public function test_a_fully_labelled_entry_is_not_on_the_list(): void
    {
        $complete = $this->spend([
            'category_id' => $this->food->id,
            'planned_status' => 'planned',
            'purpose' => 'need',
            'merchant_id' => \App\Models\Merchant::create(['name' => 'Blinkit'])->id,
            'payer_id' => \App\Models\Person::create(['name' => 'Anurag'])->id,
            'beneficiary_id' => \App\Models\Person::create(['name' => 'Household'])->id,
        ]);

        $this->assertSame([], $this->completeness()->gapsFor($complete->refresh()));
        $this->assertFalse($this->completeness()->query(TransactionCompletenessService::NEEDS)
            ->whereKey($complete->id)->exists());
        $this->assertFalse($this->completeness()->query(TransactionCompletenessService::RICHER)
            ->whereKey($complete->id)->exists());
    }

    public function test_an_emi_is_never_asked_for_a_merchant_or_a_person(): void
    {
        $loan = app(LoanService::class)->create([
            'name' => 'Land Loan', 'emi_amount' => '39005',
            'total_months' => 12, 'start_date' => '2026-09-07', 'due_day' => 7,
        ]);

        $instalment = $loan->payments()->where('status', 'scheduled')->orderBy('due_date')->first();
        app(LoanService::class)->payInstalment($instalment, $this->bank, Carbon::parse('2026-10-03'));

        $emi = Transaction::whereNotNull('loan_payment_id')->latest('id')->first();
        $keys = collect($this->completeness()->gapsFor($emi))->pluck('key');

        // There is no shop behind an EMI, and no person to name beyond the
        // account it leaves from. Asking would leave rows nobody can clear.
        $this->assertNotContains('merchant_id', $keys);
        $this->assertNotContains('payer_id', $keys);
        $this->assertNotContains('beneficiary_id', $keys);

        $this->assertFalse($this->completeness()->query(TransactionCompletenessService::RICHER)
            ->whereKey($emi->id)->exists());
    }

    public function test_an_entry_filed_under_a_subcategory_is_flagged(): void
    {
        $child = Category::create(['name' => 'Groceries', 'parent_id' => $this->food->id, 'applies_to' => 'expense']);

        // Filed under the child as though it were top level, so it reports as
        // its own row instead of rolling up into Food.
        $wrong = $this->spend(['category_id' => $child->id, 'planned_status' => 'planned', 'purpose' => 'need']);

        $this->assertContains('category_id', collect($this->completeness()->gapsFor($wrong))->pluck('key'));
        $this->assertTrue($this->completeness()->query(TransactionCompletenessService::NEEDS)
            ->whereKey($wrong->id)->exists());
    }

    public function test_income_and_transfers_are_not_on_the_list(): void
    {
        app(TransactionService::class)->recordIncome([
            'transaction_date' => '2026-10-01', 'account_id' => $this->bank->id, 'amount' => '86000',
        ]);

        $this->assertSame(0, $this->completeness()->query(TransactionCompletenessService::NEEDS)->count());
    }

    // -----------------------------------------------------------------
    // Filling it in
    // -----------------------------------------------------------------

    public function test_filling_in_labels_never_moves_money(): void
    {
        $entry = $this->spend(['amount' => '1234.56']);
        $balanceBefore = (string) $this->bank->refresh()->cached_balance;

        $this->put('/tidy-up/'.$entry->id, [
            'category_id' => $this->food->id,
            'planned_status' => 'planned',
            'purpose' => 'need',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $entry->refresh();

        $this->assertSame($this->food->id, $entry->category_id);
        $this->assertSame('planned', $entry->planned_status->value);

        // The things this screen must never touch.
        $this->assertSame('1234.56', (string) $entry->amount);
        $this->assertSame($this->bank->id, $entry->account_id);
        $this->assertSame('2026-10-05', $entry->transaction_date->toDateString());
        $this->assertSame($balanceBefore, (string) $this->bank->refresh()->cached_balance);
    }

    public function test_a_subcategory_from_another_parent_is_dropped_rather_than_saved(): void
    {
        $travel = Category::create(['name' => 'Travel', 'applies_to' => 'expense']);
        $flights = Category::create(['name' => 'Flights', 'parent_id' => $travel->id, 'applies_to' => 'expense']);

        $entry = $this->spend();

        // Food with a Travel subcategory would make the two roll-ups disagree.
        $this->put('/tidy-up/'.$entry->id, [
            'category_id' => $this->food->id,
            'subcategory_id' => $flights->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($this->food->id, $entry->refresh()->category_id);
        $this->assertNull($entry->subcategory_id);
    }

    public function test_clearing_the_list_is_reflected_in_the_count(): void
    {
        $entry = $this->spend();

        $this->assertSame(1, $this->completeness()->counts()[TransactionCompletenessService::NEEDS]);

        $this->put('/tidy-up/'.$entry->id, [
            'category_id' => $this->food->id, 'planned_status' => 'unplanned', 'purpose' => 'want',
        ])->assertRedirect();

        $this->assertSame(0, $this->completeness()->counts()[TransactionCompletenessService::NEEDS]);
    }

    public function test_the_screen_renders_both_tiers(): void
    {
        $this->spend();

        $this->get('/tidy-up')->assertOk()->assertSee('Needs you')->assertSee('Fill in');
        $this->get('/tidy-up?tier=richer')->assertOk();
        $this->get('/tidy-up?sort=date')->assertOk();
    }

    // -----------------------------------------------------------------
    // Comparing months
    // -----------------------------------------------------------------

    public function test_a_running_month_is_compared_over_the_same_number_of_days(): void
    {
        // September: spread across the month. October: only the first week.
        $this->spend(['transaction_date' => '2026-09-03', 'amount' => '1000']);
        $this->spend(['transaction_date' => '2026-09-20', 'amount' => '9000']);
        $this->spend(['transaction_date' => '2026-10-03', 'amount' => '1500']);

        $page = $this->get('/reports/compare')->assertOk();

        // Day-aligned by default: ₹1,000 of September, not ₹10,000.
        $page->assertSee('First 8 days of each');
        $this->assertSame('1000.00', app(SpendingReportService::class)->totalSpending('2026-09-01', '2026-09-08'));

        $this->get('/reports/compare?window=full')->assertOk()->assertSee('Whole months');
    }

    public function test_the_comparison_says_so_when_one_month_predates_the_ledger(): void
    {
        $this->spend(['transaction_date' => '2026-10-03']);

        // Nothing was recorded in September at all, so the difference is a gap
        // in records rather than a drop in spending.
        $this->get('/reports/compare')->assertOk()->assertSee('only part-recorded');
    }

    public function test_category_movers_report_what_changed(): void
    {
        $travel = Category::create(['name' => 'Travel', 'applies_to' => 'expense']);

        $this->spend(['transaction_date' => '2026-09-02', 'amount' => '1000', 'category_id' => $this->food->id]);
        $this->spend(['transaction_date' => '2026-10-02', 'amount' => '2500', 'category_id' => $this->food->id]);
        $this->spend(['transaction_date' => '2026-10-02', 'amount' => '700', 'category_id' => $travel->id]);

        $movers = app(SpendingReportService::class)
            ->categoryMovers('2026-10-01', '2026-10-08', '2026-09-01', '2026-09-08');

        $food = $movers->firstWhere('label', 'Food');
        $this->assertSame('1500.00', $food->delta);
        $this->assertSame(150, $food->percent);

        // Nothing to compare against is not a 100% rise.
        $this->assertNull($movers->firstWhere('label', 'Travel')->percent);
    }

    public function test_committed_spending_is_separated_from_choices(): void
    {
        $loan = app(LoanService::class)->create([
            'name' => 'Land Loan', 'emi_amount' => '39005',
            'total_months' => 12, 'start_date' => '2026-09-07', 'due_day' => 7,
        ]);

        $instalment = $loan->payments()->where('status', 'scheduled')->orderBy('due_date')->first();
        app(LoanService::class)->payInstalment($instalment, $this->bank, Carbon::parse('2026-10-03'));

        $this->spend(['transaction_date' => '2026-10-04', 'amount' => '1000']);

        $split = app(SpendingReportService::class)->committedVsDiscretionary('2026-10-01', '2026-10-31');

        $this->assertSame('39005.00', $split['committed']);
        $this->assertSame('1000.00', $split['discretionary']);
        $this->assertSame(98, $split['committed_share']);

        $this->get('/')->assertOk()->assertSee('Committed vs chosen');
    }
}
