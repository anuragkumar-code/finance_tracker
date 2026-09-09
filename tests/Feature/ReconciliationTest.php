<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Reconciliation;
use App\Models\Transaction;
use App\Services\AccountBalanceService;
use App\Services\ReconciliationService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Checking the app against reality (spec section 18).
 *
 * The invariant these tests protect: the app never edits a calculated balance to
 * make it agree with the bank. A gap is recorded and shown; it is closed only by
 * a visible adjustment the household confirms. Silently "correcting" a balance
 * would destroy the one property that makes the ledger worth trusting.
 */
class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private ReconciliationService $reconciliations;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-09');
        $this->reconciliations = app(ReconciliationService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $name, string $balance, AccountType $type = AccountType::Bank): Account
    {
        return Account::create([
            'name' => $name,
            'type' => $type,
            'opening_balance' => $balance,
            'opening_balance_date' => '2026-09-01',
            'cached_balance' => $balance,
        ]);
    }

    public function test_matching_balances_are_recorded_as_reconciled(): void
    {
        $bank = $this->account('HDFC Bank', '84500');

        $result = $this->reconciliations->reconcile($bank, '84500');

        $this->assertTrue($result->matched());
        $this->assertSame('reconciled', $result->status);
        $this->assertSame('0.00', (string) $result->difference);

        // An observation, not a correction: nothing was posted.
        $this->assertSame(0, Transaction::count());
    }

    public function test_a_gap_is_recorded_without_touching_the_balance(): void
    {
        $bank = $this->account('HDFC Bank', '86200');

        $result = $this->reconciliations->reconcile($bank, '84500');

        $this->assertSame('discrepancy', $result->status);
        $this->assertSame('-1700.00', (string) $result->difference);

        // The crucial part: the app did NOT quietly change its own figure.
        $this->assertSame('86200.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame(0, Transaction::count());
    }

    public function test_adjusting_posts_a_visible_entry_that_closes_the_gap(): void
    {
        $bank = $this->account('HDFC Bank', '86200');

        $result = $this->reconciliations->reconcile($bank, '84500');
        $this->reconciliations->postAdjustment($result, 'Bank charge I had not recorded');

        // Now the app agrees with the bank — because of an entry you can see.
        $this->assertSame('84500.00', (string) $bank->refresh()->cached_balance);

        $adjustment = Transaction::firstOrFail();
        $this->assertSame(TransactionType::Adjustment, $adjustment->type);
        $this->assertSame('1700.00', (string) $adjustment->amount);
        $this->assertSame('Bank charge I had not recorded', $adjustment->notes);

        $this->assertSame('resolved', $result->refresh()->status);
        $this->assertSame($adjustment->id, $result->adjustment_transaction_id);
    }

    public function test_an_adjustment_is_never_counted_as_spending(): void
    {
        $bank = $this->account('HDFC Bank', '86200');

        $result = $this->reconciliations->reconcile($bank, '84500');
        $this->reconciliations->postAdjustment($result, 'Unrecorded bank charge');

        // It moved a balance, but it is not consumption — spec section 26
        // excludes adjustments from spending.
        $this->assertSame('0', (string) Transaction::query()->spending()->sum('amount'));
    }

    public function test_adjusting_upwards_when_the_bank_holds_more(): void
    {
        $bank = $this->account('HDFC Bank', '84500');

        $result = $this->reconciliations->reconcile($bank, '86200');
        $this->reconciliations->postAdjustment($result, 'Interest credited');

        $this->assertSame('86200.00', (string) $bank->refresh()->cached_balance);
    }

    public function test_a_card_owing_more_than_recorded_adjusts_upwards(): void
    {
        $card = $this->account('HDFC Card', '20949', AccountType::CreditCard);

        // The card statement says more is owed than the app knows about.
        $result = $this->reconciliations->reconcile($card, '22000');
        $this->reconciliations->postAdjustment($result, 'Annual fee not recorded');

        $this->assertSame('22000.00', (string) $card->refresh()->cached_balance);
    }

    public function test_matching_balances_cannot_be_adjusted(): void
    {
        $bank = $this->account('HDFC Bank', '84500');
        $result = $this->reconciliations->reconcile($bank, '84500');

        $this->expectException(InvalidArgumentException::class);

        $this->reconciliations->postAdjustment($result, 'No reason');
    }

    public function test_a_gap_cannot_be_adjusted_twice(): void
    {
        $bank = $this->account('HDFC Bank', '86200');
        $result = $this->reconciliations->reconcile($bank, '84500');

        $this->reconciliations->postAdjustment($result, 'First');

        $this->expectException(InvalidArgumentException::class);

        $this->reconciliations->postAdjustment($result->refresh(), 'Second');
    }

    public function test_adjusting_requires_a_reason(): void
    {
        $bank = $this->account('HDFC Bank', '86200');
        $result = $this->reconciliations->reconcile($bank, '84500');

        $this->expectException(InvalidArgumentException::class);

        $this->reconciliations->postAdjustment($result, '   ');
    }

    public function test_the_recorded_difference_does_not_move_when_later_entries_arrive(): void
    {
        $bank = $this->account('HDFC Bank', '86200');
        $result = $this->reconciliations->reconcile($bank, '84500');

        app(TransactionService::class)->recordExpense([
            'transaction_date' => '2026-09-09',
            'account_id' => $bank->id,
            'amount' => '5000',
        ]);

        // What was true on the day you checked stays on record.
        $this->assertSame('-1700.00', (string) $result->refresh()->difference);
        $this->assertSame('86200.00', (string) $result->system_balance);
    }

    public function test_the_balance_stays_re_derivable_after_an_adjustment(): void
    {
        $bank = $this->account('HDFC Bank', '86200');
        $result = $this->reconciliations->reconcile($bank, '84500');
        $this->reconciliations->postAdjustment($result, 'Unrecorded charge');

        // Rule 8: the cached balance must still equal the ledger.
        $this->assertSame([], app(AccountBalanceService::class)->recalculateAll());
    }

    // -----------------------------------------------------------------
    // Screens and export
    // -----------------------------------------------------------------

    public function test_the_reconcile_screen_loads_empty_and_with_history(): void
    {
        $this->get('/reconcile')->assertOk()->assertSee('Nothing checked yet');

        $bank = $this->account('HDFC Bank', '84500');
        $this->reconciliations->reconcile($bank, '84500');

        $this->get('/reconcile')->assertOk()->assertSee('HDFC Bank');
    }

    public function test_reconciling_through_the_form_reports_a_gap(): void
    {
        $bank = $this->account('HDFC Bank', '86200');

        $this->post('/reconcile', [
            'account_id' => $bank->id,
            'actual_balance' => '84500',
            'reconciliation_date' => '2026-09-09',
        ])->assertRedirect()->assertSessionHas('warning');

        $this->assertSame('discrepancy', Reconciliation::firstOrFail()->status);
        $this->assertSame('86200.00', (string) $bank->refresh()->cached_balance);
    }

    public function test_adjusting_through_the_form_requires_a_reason(): void
    {
        $bank = $this->account('HDFC Bank', '86200');
        $result = $this->reconciliations->reconcile($bank, '84500');

        $this->post("/reconcile/{$result->id}/adjust", [])
            ->assertSessionHasErrors('reason');

        $this->assertSame('86200.00', (string) $bank->refresh()->cached_balance);
    }

    public function test_transactions_export_streams_a_csv_including_voided_rows(): void
    {
        $bank = $this->account('HDFC Bank', '50000');
        $service = app(TransactionService::class);

        $kept = $service->recordExpense([
            'transaction_date' => '2026-09-08', 'account_id' => $bank->id,
            'amount' => '1200', 'description' => 'Groceries',
        ]);
        $voided = $service->recordExpense([
            'transaction_date' => '2026-09-08', 'account_id' => $bank->id,
            'amount' => '900', 'description' => 'Entered twice',
        ]);
        $service->void($voided, 'Duplicate');

        $response = $this->get('/export/transactions.csv');
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Groceries', $csv);
        // A voided row is exported and flagged, so the file reconciles against
        // the app it came from rather than quietly disagreeing.
        $this->assertStringContainsString('Entered twice', $csv);
        $this->assertStringContainsString('Duplicate', $csv);
    }

    public function test_accounts_export_includes_balances_and_the_set_aside_flag(): void
    {
        $this->account('HDFC Bank', '50000');

        Account::create([
            'name' => 'Emergency fund', 'type' => AccountType::Bank,
            'opening_balance' => '225000', 'opening_balance_date' => '2026-09-01',
            'cached_balance' => '225000', 'is_set_aside' => true,
        ]);

        $csv = $this->get('/export/accounts.csv')->assertOk()->streamedContent();

        $this->assertStringContainsString('HDFC Bank', $csv);
        // Hidden in the UI, but present in a backup — an export that omitted it
        // would not restore the household's real position.
        $this->assertStringContainsString('Emergency fund', $csv);
        $this->assertStringContainsString('yes', $csv);
    }
}
