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
 * The rule under test: an emergency fund is invisible in every figure the app
 * reports — available balance, net worth, assets, cash flow, charts — while the
 * account itself stays fully usable so money can be moved into it.
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

    public function test_set_aside_money_is_absent_from_net_worth_and_assets(): void
    {
        $this->account('Everyday', '50000');
        $this->account('Emergency fund', '225000', setAside: true);

        $summary = app(NetWorthService::class)->summary();

        // The household asked for the emergency fund to be invisible in every
        // figure, so net worth describes the money in play rather than
        // everything owned. That trade-off was theirs, made knowingly.
        $this->assertSame('50000.00', $summary['assets']);
        $this->assertSame('50000.00', $summary['net_worth']);
        $this->assertSame('50000.00', $summary['bank_cash']);

        // Still reported on its own, purely so the Accounts page can list it.
        $this->assertSame('225000.00', $summary['set_aside']);
    }

    public function test_moving_money_into_the_fund_reads_as_money_leaving(): void
    {
        $everyday = $this->account('Everyday', '50000');
        $fund = $this->account('Emergency fund', '0', setAside: true);

        app(\App\Services\TransferService::class)->create([
            'transaction_date' => '2026-09-08',
            'from_account_id' => $everyday->id,
            'to_account_id' => $fund->id,
            'amount' => '10000',
        ]);

        $reports = app(SpendingReportService::class);

        // The transfer out is visible; the leg landing in the fund is not, so
        // the money reads as gone from the pool the household actually uses
        // rather than netting to zero.
        $this->assertSame('10000.00', $reports->cashOutflow('2026-09-01', '2026-09-30'));
        $this->assertSame('0.00', $reports->cashInflow('2026-09-01', '2026-09-30'));
        $this->assertSame('-10000.00', $reports->netCashMovement('2026-09-01', '2026-09-30'));

        // And it is still not spending.
        $this->assertSame('0.00', $reports->totalSpending('2026-09-01', '2026-09-30'));

        // The fund did receive it, though — the transfer genuinely happened.
        $this->assertSame('10000.00', (string) $fund->refresh()->cached_balance);
        $this->assertSame('40000.00', (string) $everyday->refresh()->cached_balance);
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

    public function test_the_dashboard_never_shows_set_aside_money(): void
    {
        $this->account('Everyday', '50000');
        $this->account('Emergency fund', '225000', setAside: true);

        $this->get('/')
            ->assertOk()
            ->assertSee('50,000.00')
            // The emergency fund's balance appears nowhere on the dashboard.
            ->assertDontSee('2,25,000.00')
            ->assertDontSee('2,75,000.00');

        $this->assertSame('50000.00', app(SpendingReportService::class)->spendableCash());
        $this->assertSame('50000.00', app(NetWorthService::class)->summary()['net_worth']);
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
