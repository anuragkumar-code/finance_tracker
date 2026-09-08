<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Loan;
use App\Models\Person;
use App\Services\LoanService;
use App\Services\Reporting\NetWorthService;
use App\Services\Reporting\SpendingReportService;
use App\Services\Reporting\UpcomingObligationsService;
use Carbon\Carbon;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PersonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ring-fenced money and things owned outside accounts.
 *
 * The rule under test: an emergency fund must never read as spendable, but must
 * still count as owned. Getting either half wrong misleads in a different
 * direction.
 */
class SetAsideAndAssetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-08');
        $this->seed([CategorySeeder::class, PersonSeeder::class]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $name, string $balance, bool $setAside = false): Account
    {
        return Account::create([
            'name' => $name,
            'type' => AccountType::Bank,
            'opening_balance' => $balance,
            'opening_balance_date' => '2026-09-01',
            'cached_balance' => $balance,
            'is_set_aside' => $setAside,
            'set_aside_reason' => $setAside ? 'Emergency fund' : null,
        ]);
    }

    // -----------------------------------------------------------------
    // Set-aside money
    // -----------------------------------------------------------------

    public function test_set_aside_money_is_not_counted_as_spendable(): void
    {
        $this->account('Everyday', '50000');
        $this->account('Emergency fund', '225000', setAside: true);

        // Only the everyday account is money they can spend.
        $this->assertSame('50000.00', app(SpendingReportService::class)->spendableCash());
    }

    public function test_set_aside_money_is_excluded_from_realistically_available(): void
    {
        $this->account('Everyday', '50000');
        $this->account('Emergency fund', '225000', setAside: true);

        $reality = app(UpcomingObligationsService::class)->financialReality(30);

        $this->assertSame('50000.00', $reality['available']);
    }

    public function test_set_aside_money_still_counts_as_owned(): void
    {
        $this->account('Everyday', '50000');
        $this->account('Emergency fund', '225000', setAside: true);

        $summary = app(NetWorthService::class)->summary();

        // Owned in full — pretending it does not exist would understate the
        // household's real position.
        $this->assertSame('275000.00', $summary['assets']);
        $this->assertSame('275000.00', $summary['net_worth']);

        // But reported apart, so the distinction is visible.
        $this->assertSame('50000.00', $summary['bank_cash']);
        $this->assertSame('225000.00', $summary['set_aside']);
    }

    public function test_an_account_can_be_marked_set_aside_through_the_form(): void
    {
        $this->post('/accounts', [
            'name' => 'BOB Emergency',
            'type' => AccountType::Bank->value,
            'opening_balance' => '225000',
            'opening_balance_date' => '2026-09-01',
            'is_set_aside' => '1',
            'set_aside_reason' => 'Emergency fund',
        ])->assertRedirect();

        $account = Account::where('name', 'BOB Emergency')->firstOrFail();

        $this->assertTrue($account->is_set_aside);
        $this->assertSame('Emergency fund', $account->set_aside_reason);
        $this->assertSame('0.00', app(SpendingReportService::class)->spendableCash());
    }

    public function test_the_dashboard_separates_available_money_from_set_aside_money(): void
    {
        $this->account('Everyday', '50000');
        $this->account('Emergency fund', '225000', setAside: true);

        $this->get('/')
            ->assertOk()
            // Available to spend is the everyday account alone...
            ->assertSee('50,000.00')
            // ...while net worth still counts everything they own.
            ->assertSee('2,75,000.00');

        // The two figures come from different definitions, and that is the point.
        $this->assertSame('50000.00', app(SpendingReportService::class)->spendableCash());
        $this->assertSame('275000.00', app(NetWorthService::class)->summary()['net_worth']);
    }

    // -----------------------------------------------------------------
    // Assets
    // -----------------------------------------------------------------

    public function test_an_asset_can_be_recorded_without_a_value(): void
    {
        $this->post('/assets', [
            'name' => 'Land at Ranchi',
            'type' => 'land',
        ])->assertRedirect();

        $asset = Asset::where('name', 'Land at Ranchi')->firstOrFail();

        $this->assertNull($asset->current_value);
        $this->assertFalse($asset->hasValue());
    }

    public function test_an_unvalued_asset_adds_nothing_to_net_worth_but_is_flagged(): void
    {
        $this->account('Everyday', '50000');

        Asset::create(['name' => 'Land', 'type' => 'land']);

        $summary = app(NetWorthService::class)->summary();

        $this->assertSame('0.00', $summary['asset_value']);
        $this->assertSame('50000.00', $summary['net_worth']);

        // The caveat is what keeps the figure honest rather than just low.
        $this->assertSame(1, $summary['unvalued_assets']);
    }

    public function test_a_valued_asset_counts_towards_net_worth(): void
    {
        $this->account('Everyday', '50000');

        $this->post('/assets', [
            'name' => 'Land at Ranchi',
            'type' => 'land',
            'current_value' => '2500000',
        ])->assertRedirect();

        $summary = app(NetWorthService::class)->summary();

        $this->assertSame('2500000.00', $summary['asset_value']);
        $this->assertSame('2550000.00', $summary['net_worth']);
        $this->assertSame(0, $summary['unvalued_assets']);
    }

    public function test_an_asset_can_be_linked_to_the_loan_that_bought_it(): void
    {
        $loan = app(LoanService::class)->create([
            'name' => 'Land Loan',
            'emi_amount' => '39005',
            'total_months' => 72,
            'start_date' => '2026-03-03',
            'due_day' => 7,
        ]);

        $this->post('/assets', [
            'name' => 'Land at Ranchi',
            'type' => 'land',
            'linked_loan_id' => $loan->id,
        ])->assertRedirect();

        $asset = Asset::where('name', 'Land at Ranchi')->firstOrFail();

        $this->assertSame($loan->id, $asset->linked_loan_id);
        $this->assertSame('Land Loan', $asset->linkedLoan->name);
    }

    public function test_net_worth_counts_loans_as_debt(): void
    {
        $this->account('Everyday', '277703.33');

        app(LoanService::class)->create([
            'name' => 'Land Loan',
            'emi_amount' => '39005',
            'total_months' => 72,
            'start_date' => '2026-03-03',
            'due_day' => 7,
        ]);

        $summary = app(NetWorthService::class)->summary();

        // 72 EMIs, 7 already behind us on 8 Sep -> 65 remaining.
        $this->assertSame('2535325.00', $summary['loan_debt']);
        $this->assertSame('-2257621.67', $summary['net_worth']);
    }

    public function test_the_assets_screen_loads_with_and_without_data(): void
    {
        $this->get('/assets')->assertOk();

        Asset::create(['name' => 'Land', 'type' => 'land']);

        $this->get('/assets')->assertOk()->assertSee('Land');
    }
}
