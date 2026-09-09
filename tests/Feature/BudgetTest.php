<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Services\Reporting\BudgetService;
use App\Services\TransactionService;
use App\Services\TransferService;
use Carbon\Carbon;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PersonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Budgets, variance and anomaly detection (spec sections 22 and 33 phase 6).
 *
 * Two rules matter more than the arithmetic here:
 *
 *  - The app must not invent a target from thin data. Spec section 22 warns
 *    against imposing budgets before behaviour is understood, so a suggestion
 *    with too little history must refuse and say why.
 *  - Changing a budget must not rewrite how past months were judged.
 */
class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private BudgetService $budgets;

    private Account $bank;

    private Category $food;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-15');
        $this->seed([CategorySeeder::class, PersonSeeder::class]);

        $this->budgets = app(BudgetService::class);
        $this->food = Category::where('name', 'Food')->firstOrFail();

        $this->bank = Account::create([
            'name' => 'Kotak', 'type' => AccountType::Bank,
            'opening_balance' => '500000', 'opening_balance_date' => '2026-01-01',
            'cached_balance' => '500000',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function spend(string $amount, string $date, ?Category $category = null): void
    {
        app(TransactionService::class)->recordExpense([
            'transaction_date' => $date,
            'account_id' => $this->bank->id,
            'amount' => $amount,
            'category_id' => ($category ?? $this->food)->id,
        ]);
    }

    // -----------------------------------------------------------------
    // Setting budgets
    // -----------------------------------------------------------------

    public function test_a_budget_can_be_set_and_compared_against_actual_spending(): void
    {
        $this->budgets->setBudget($this->food, '20000');
        $this->spend('12000', '2026-09-05');

        $row = $this->budgets->comparison(Carbon::parse('2026-09-01'))
            ->firstWhere('category_id', $this->food->id);

        $this->assertSame('20000.00', $row->budget);
        $this->assertSame('12000.00', $row->spent);
        $this->assertSame('8000.00', $row->remaining);
        $this->assertSame(60.0, $row->used_percent);
    }

    public function test_going_over_budget_is_flagged(): void
    {
        $this->budgets->setBudget($this->food, '10000');
        $this->spend('12500', '2026-09-05');

        $row = $this->budgets->comparison(Carbon::parse('2026-09-01'))
            ->firstWhere('category_id', $this->food->id);

        $this->assertSame('over', $row->status);
        $this->assertSame('-2500.00', $row->remaining);
    }

    public function test_spending_far_ahead_of_the_calendar_is_flagged_early(): void
    {
        // Half the month gone, but nearly the whole budget spent.
        $this->budgets->setBudget($this->food, '10000');
        $this->spend('9000', '2026-09-02');

        $row = $this->budgets->comparison(Carbon::parse('2026-09-01'))
            ->firstWhere('category_id', $this->food->id);

        $this->assertSame('ahead_of_pace', $row->status);
    }

    public function test_a_budget_needs_a_positive_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->budgets->setBudget($this->food, '0');
    }

    public function test_changing_a_budget_does_not_rewrite_how_past_months_were_judged(): void
    {
        // August was judged against 15,000.
        $this->budgets->setBudget($this->food, '15000', Carbon::parse('2026-08-01'));
        $this->spend('14000', '2026-08-10');

        // From September the household raised it.
        $this->budgets->setBudget($this->food, '25000', Carbon::parse('2026-09-01'));
        $this->spend('20000', '2026-09-10');

        $august = $this->budgets->comparison(Carbon::parse('2026-08-01'))
            ->firstWhere('category_id', $this->food->id);
        $september = $this->budgets->comparison(Carbon::parse('2026-09-01'))
            ->firstWhere('category_id', $this->food->id);

        $this->assertSame('15000.00', $august->budget, 'August was re-judged against the new budget.');
        $this->assertSame('25000.00', $september->budget);

        // Two records, so the history is real rather than implied.
        $this->assertSame(2, Budget::where('category_id', $this->food->id)->count());
    }

    public function test_correcting_a_budget_in_the_same_month_does_not_create_history(): void
    {
        $this->budgets->setBudget($this->food, '20000');
        $this->budgets->setBudget($this->food, '22000');

        $this->assertSame(1, Budget::where('category_id', $this->food->id)->count());
        $this->assertSame(
            '22000.00',
            $this->budgets->comparison(Carbon::parse('2026-09-01'))
                ->firstWhere('category_id', $this->food->id)->budget
        );
    }

    public function test_removing_a_budget_leaves_the_spending_visible(): void
    {
        $this->budgets->setBudget($this->food, '20000', Carbon::parse('2026-08-01'));
        $this->spend('5000', '2026-09-05');

        $this->budgets->removeBudget($this->food);

        $row = $this->budgets->comparison(Carbon::parse('2026-09-01'))
            ->firstWhere('category_id', $this->food->id);

        // The category is still listed with what was spent — dropping a budget
        // must not make the spending disappear from view.
        $this->assertNull($row->budget);
        $this->assertSame('5000.00', $row->spent);
        $this->assertSame('unbudgeted', $row->status);
    }

    public function test_categories_with_no_budget_still_appear_with_their_spending(): void
    {
        $shopping = Category::where('name', 'Shopping')->firstOrFail();

        $this->budgets->setBudget($this->food, '20000');
        $this->spend('3000', '2026-09-05');
        $this->spend('7000', '2026-09-06', $shopping);

        $totals = $this->budgets->totals(Carbon::parse('2026-09-01'));

        $this->assertSame('20000.00', $totals['budgeted']);
        $this->assertSame('3000.00', $totals['spent']);
        // Unbudgeted spending is reported, not quietly ignored.
        $this->assertSame('7000.00', $totals['unbudgeted']);
    }

    public function test_transfers_never_count_against_a_budget(): void
    {
        $other = Account::create([
            'name' => 'Savings', 'type' => AccountType::Bank,
            'opening_balance' => '0', 'opening_balance_date' => '2026-01-01',
        ]);

        $this->budgets->setBudget($this->food, '20000');
        $this->spend('3000', '2026-09-05');

        app(TransferService::class)->create([
            'transaction_date' => '2026-09-06',
            'from_account_id' => $this->bank->id,
            'to_account_id' => $other->id,
            'amount' => '50000',
        ]);

        $row = $this->budgets->comparison(Carbon::parse('2026-09-01'))
            ->firstWhere('category_id', $this->food->id);

        $this->assertSame('3000.00', $row->spent);
    }

    // -----------------------------------------------------------------
    // The refusal to guess
    // -----------------------------------------------------------------

    public function test_a_suggestion_is_refused_without_enough_history(): void
    {
        $this->spend('12000', '2026-08-10');

        $suggestion = $this->budgets->suggest($this->food);

        // One month is not a habit. Say so rather than dress a guess as insight.
        $this->assertNull($suggestion['amount']);
        $this->assertSame(1, $suggestion['months']);
        $this->assertStringContainsString('1 month', $suggestion['reason']);
    }

    public function test_a_suggestion_is_offered_once_there_is_enough_history(): void
    {
        $this->spend('10000', '2026-06-10');
        $this->spend('14000', '2026-07-10');
        $this->spend('12000', '2026-08-10');

        $suggestion = $this->budgets->suggest($this->food);

        $this->assertSame(3, $suggestion['months']);
        $this->assertSame('12000.00', $suggestion['amount']);
        $this->assertNull($suggestion['reason']);
    }

    public function test_the_current_partial_month_does_not_drag_a_suggestion_down(): void
    {
        $this->spend('10000', '2026-06-10');
        $this->spend('14000', '2026-07-10');
        $this->spend('12000', '2026-08-10');
        // Only half of September has happened; including it would understate.
        $this->spend('900', '2026-09-02');

        $this->assertSame('12000.00', $this->budgets->suggest($this->food)['amount']);
    }

    // -----------------------------------------------------------------
    // Anomalies
    // -----------------------------------------------------------------

    public function test_a_category_spending_far_above_its_own_average_is_flagged(): void
    {
        $this->spend('5000', '2026-07-10');
        $this->spend('5000', '2026-08-10');
        $this->spend('15000', '2026-09-10');

        $anomalies = $this->budgets->anomalies(Carbon::parse('2026-09-01'));

        $this->assertCount(1, $anomalies);
        $this->assertSame($this->food->id, $anomalies->first()->category_id);
        $this->assertSame(3.0, $anomalies->first()->ratio);
        $this->assertSame('10000.00', $anomalies->first()->extra);
    }

    public function test_steady_spending_is_not_flagged(): void
    {
        $this->spend('5000', '2026-07-10');
        $this->spend('5200', '2026-08-10');
        $this->spend('5100', '2026-09-10');

        $this->assertCount(0, $this->budgets->anomalies(Carbon::parse('2026-09-01')));
    }

    public function test_nothing_is_flagged_without_history_to_compare_against(): void
    {
        // A first month of spending is not an anomaly — there is nothing to
        // call it unusual against.
        $this->spend('50000', '2026-09-10');

        $this->assertCount(0, $this->budgets->anomalies(Carbon::parse('2026-09-01')));
    }

    // -----------------------------------------------------------------
    // Screens
    // -----------------------------------------------------------------

    public function test_the_budgets_screen_loads_and_admits_to_thin_history(): void
    {
        $this->get('/budgets')
            ->assertOk()
            ->assertSee('month(s) of spending recorded');
    }

    public function test_a_budget_can_be_set_through_the_form(): void
    {
        $this->post('/budgets', [
            'category_id' => $this->food->id,
            'amount' => '20000',
            'month' => '2026-09-01',
        ])->assertRedirect();

        $this->assertSame('20000.00', (string) Budget::firstOrFail()->amount);
    }

    public function test_the_suggestion_endpoint_explains_a_refusal(): void
    {
        $this->getJson("/budgets/{$this->food->id}/suggestion")
            ->assertOk()
            ->assertJson(['amount' => null, 'months' => 0]);
    }
}
