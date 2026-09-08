<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Person;
use App\Services\Reporting\SpendingReportService;
use App\Services\TransactionService;
use App\Services\TransferService;
use Carbon\Carbon;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PersonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reports layer (spec section 19).
 *
 * The thing being protected here is agreement: a report figure and the
 * transaction list behind it must always tell the same story, and the metric
 * definitions from spec section 26 must hold in every cut.
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private SpendingReportService $reports;

    private Account $bank;

    private Account $card;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-15');
        $this->seed([CategorySeeder::class, PersonSeeder::class]);

        $this->reports = app(SpendingReportService::class);

        $this->bank = Account::create([
            'name' => 'HDFC Bank', 'type' => AccountType::Bank,
            'opening_balance' => '200000', 'opening_balance_date' => '2026-01-01',
            'cached_balance' => '200000',
        ]);

        $this->card = Account::create([
            'name' => 'HDFC Card', 'type' => AccountType::CreditCard,
            'opening_balance' => '0', 'opening_balance_date' => '2026-01-01',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function spend(string $amount, string $date, array $extra = []): void
    {
        app(TransactionService::class)->recordExpense(array_merge([
            'transaction_date' => $date,
            'account_id' => $this->bank->id,
            'amount' => $amount,
        ], $extra));
    }

    public function test_all_report_screens_load(): void
    {
        foreach ([
            '/reports',
            '/reports?month=2026-08',
            '/reports/trends',
            '/reports/trends?months=12',
            '/reports/net-worth',
            '/reports/credit-cards',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_reports_are_honest_when_there_is_nothing_to_report(): void
    {
        $this->get('/reports')->assertOk()->assertSee('Nothing recorded');
        $this->get('/reports/trends')->assertOk()->assertSee('Not enough history yet');
    }

    public function test_weekly_breakdown_splits_the_month_and_sums_to_the_month_total(): void
    {
        $this->spend('1000', '2026-09-03');   // week 1
        $this->spend('2000', '2026-09-10');   // week 2
        $this->spend('500', '2026-09-28');    // week 4/5

        $weekly = $this->reports->weekly(Carbon::parse('2026-09-01'));

        $this->assertSame('1000.00', $weekly[0]->spending);
        $this->assertSame('2000.00', $weekly[1]->spending);

        // Every rupee lands in exactly one week.
        $summed = $weekly->reduce(fn ($c, $w) => bcadd($c, $w->spending, 2), '0.00');
        $this->assertSame($this->reports->totalSpending('2026-09-01', '2026-09-30'), $summed);
    }

    public function test_transfers_never_appear_in_any_spending_cut(): void
    {
        $other = Account::create([
            'name' => 'ICICI', 'type' => AccountType::Bank,
            'opening_balance' => '0', 'opening_balance_date' => '2026-01-01',
        ]);

        $this->spend('1000', '2026-09-03');

        app(TransferService::class)->create([
            'transaction_date' => '2026-09-05',
            'from_account_id' => $this->bank->id,
            'to_account_id' => $other->id,
            'amount' => '50000',
        ]);

        $start = '2026-09-01';
        $end = '2026-09-30';

        $this->assertSame('1000.00', $this->reports->totalSpending($start, $end));

        foreach (['payer_id', 'beneficiary_id', 'account_id', 'purpose', 'planned_status'] as $column) {
            $total = $this->reports->groupedBy($column, $start, $end)
                ->reduce(fn ($c, $r) => bcadd($c, $r->amount, 2), '0.00');

            $this->assertSame('1000.00', $total, "Transfer leaked into the {$column} breakdown.");
        }

        // But it did leave the account, so cash outflow sees it.
        $this->assertSame('51000.00', $this->reports->cashOutflow($start, $end));
    }

    public function test_every_breakdown_sums_back_to_total_spending(): void
    {
        $food = Category::where('name', 'Food')->firstOrFail();
        $anurag = Person::where('name', 'Anurag')->firstOrFail();

        $this->spend('1200', '2026-09-03', ['category_id' => $food->id, 'payer_id' => $anurag->id]);
        $this->spend('800', '2026-09-06', ['account_id' => $this->card->id]);
        $this->spend('450', '2026-09-09');

        $start = '2026-09-01';
        $end = '2026-09-30';
        $total = $this->reports->totalSpending($start, $end);

        $this->assertSame('2450.00', $total);

        // A cut that does not add up is a cut that is lying somewhere.
        foreach (['payer_id', 'beneficiary_id', 'account_id', 'planned_status', 'purpose'] as $column) {
            $summed = $this->reports->groupedBy($column, $start, $end)
                ->reduce(fn ($c, $r) => bcadd($c, $r->amount, 2), '0.00');

            $this->assertSame($total, $summed, "The {$column} breakdown does not sum to total spending.");
        }

        $byCategory = $this->reports->byCategory($start, $end)
            ->reduce(fn ($c, $r) => bcadd($c, $r->amount, 2), '0.00');

        $this->assertSame($total, $byCategory);
    }

    public function test_card_spending_is_a_subset_of_total_spending(): void
    {
        $this->spend('1000', '2026-09-03');
        $this->spend('2500', '2026-09-04', ['account_id' => $this->card->id]);

        $this->assertSame('3500.00', $this->reports->totalSpending('2026-09-01', '2026-09-30'));
        $this->assertSame('2500.00', $this->reports->creditCardSpending('2026-09-01', '2026-09-30'));
    }

    public function test_the_monthly_series_covers_the_requested_span_in_order(): void
    {
        $this->spend('1000', '2026-09-03');
        $this->spend('700', '2026-08-12');

        $series = $this->reports->monthlySeries(6);

        $this->assertCount(6, $series);
        $this->assertSame('2026-04', $series->first()->month);
        $this->assertSame('2026-09', $series->last()->month);

        $this->assertSame('700.00', $series->firstWhere('month', '2026-08')->spending);
        $this->assertSame('1000.00', $series->firstWhere('month', '2026-09')->spending);
    }

    public function test_the_category_grid_agrees_with_the_monthly_totals(): void
    {
        $food = Category::where('name', 'Food')->firstOrFail();

        $this->spend('1200', '2026-09-03', ['category_id' => $food->id]);
        $this->spend('900', '2026-08-14', ['category_id' => $food->id]);

        $grid = $this->reports->categoryByMonth(6);
        $row = collect($grid['rows'])->firstWhere('label', 'Food');

        $this->assertSame('1200.00', $row['values']['2026-09']);
        $this->assertSame('900.00', $row['values']['2026-08']);
        $this->assertSame('2100.00', $row['total']);
    }

    public function test_a_report_figure_and_its_drill_down_agree(): void
    {
        $food = Category::where('name', 'Food')->firstOrFail();

        $this->spend('1200', '2026-09-03', ['category_id' => $food->id]);
        $this->spend('800', '2026-09-06', ['category_id' => $food->id]);
        $this->spend('450', '2026-09-09');

        $reported = $this->reports->byCategory('2026-09-01', '2026-09-30')
            ->firstWhere('label', 'Food')->amount;

        $this->assertSame('2000.00', $reported);

        // Clicking that number filters the ledger the same way, and must show
        // the same figure back.
        $this->get('/transactions?'.http_build_query([
            'start' => '2026-09-01', 'end' => '2026-09-30',
            'type' => 'expense', 'category_id' => $food->id,
        ]))->assertOk()->assertSee('2,000.00');
    }

    public function test_debt_repayment_is_reported_as_a_subset_of_spending(): void
    {
        $this->spend('1000', '2026-09-03');
        $this->spend('16018', '2026-09-03', ['purpose' => 'debt']);

        $start = '2026-09-01';
        $end = '2026-09-30';

        $this->assertSame('17018.00', $this->reports->totalSpending($start, $end));
        $this->assertSame('16018.00', $this->reports->debtRepayment($start, $end));
    }
}
