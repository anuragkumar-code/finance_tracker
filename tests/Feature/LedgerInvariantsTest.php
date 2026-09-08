<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BalanceEffect;
use App\Enums\LegRole;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\AccountBalanceService;
use App\Services\TransactionService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The financial invariants from spec section 37.
 *
 * These tests exist to catch the failure this whole application is designed to
 * avoid: numbers that look plausible but are wrong. If any of these break, the
 * ledger is lying and no amount of dashboard polish can fix it.
 */
class LedgerInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $transactions;

    private TransferService $transfers;

    private AccountBalanceService $balances;

    protected function setUp(): void
    {
        parent::setUp();

        $this->balances = app(AccountBalanceService::class);
        $this->transactions = app(TransactionService::class);
        $this->transfers = app(TransferService::class);
    }

    private function account(string $name, AccountType $type, string $openingBalance = '0'): Account
    {
        return Account::create([
            'name' => $name,
            'type' => $type,
            'opening_balance' => $openingBalance,
            'opening_balance_date' => '2026-09-01',
        ]);
    }

    /** Total spending for a period, per spec section 26. */
    private function totalSpending(string $start = '2026-09-01', string $end = '2026-09-30'): string
    {
        return (string) Transaction::query()->spending()->inPeriod($start, $end)->sum('amount');
    }

    // ---------------------------------------------------------------------
    // Credit-card purchase (spec section 4, section 37)
    // ---------------------------------------------------------------------

    public function test_credit_card_purchase_raises_liability_and_leaves_bank_untouched(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '85000');
        $card = $this->account('HDFC Credit Card', AccountType::CreditCard, '0');

        $this->transactions->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $card->id,
            'amount' => '2000',
            'description' => 'Shopping',
        ]);

        // The expense is recognised on the purchase date (Decision A).
        $this->assertSame('2000.00', $this->totalSpending());

        // The card's balance means "amount owed", so it goes UP.
        $this->assertSame('2000.00', $this->balances->balance($card->refresh()));

        // Nothing was paid from the bank yet.
        $this->assertSame('85000.00', $this->balances->balance($bank->refresh()));
    }

    public function test_expense_on_a_bank_account_reduces_that_balance(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '85000');

        $this->transactions->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $bank->id,
            'amount' => '1500',
        ]);

        $this->assertSame('83500.00', $this->balances->balance($bank->refresh()));
        $this->assertSame('83500.00', (string) $bank->refresh()->cached_balance);
    }

    // ---------------------------------------------------------------------
    // Transfers are not spending (spec Decision C, Rule 2)
    // ---------------------------------------------------------------------

    public function test_bank_to_bank_transfer_moves_money_without_counting_as_spending(): void
    {
        $hdfc = $this->account('HDFC Bank', AccountType::Bank, '85000');
        $icici = $this->account('ICICI Bank', AccountType::Bank, '110000');

        $this->transfers->create([
            'transaction_date' => '2026-09-10',
            'from_account_id' => $hdfc->id,
            'to_account_id' => $icici->id,
            'amount' => '20000',
        ]);

        $this->assertSame('65000.00', $this->balances->balance($hdfc->refresh()));
        $this->assertSame('130000.00', $this->balances->balance($icici->refresh()));

        // The household is no poorer — and the spending report must agree.
        $this->assertSame('0', $this->totalSpending());
    }

    public function test_transfer_writes_two_linked_legs_that_share_a_group(): void
    {
        $hdfc = $this->account('HDFC Bank', AccountType::Bank, '50000');
        $cash = $this->account('Cash', AccountType::Cash, '5000');

        $legs = $this->transfers->create([
            'transaction_date' => '2026-09-10',
            'from_account_id' => $hdfc->id,
            'to_account_id' => $cash->id,
            'amount' => '3000',
        ]);

        $this->assertCount(2, $legs);
        $this->assertSame($legs[0]->transfer_group_id, $legs[1]->transfer_group_id);
        $this->assertNotNull($legs[0]->transfer_group_id);

        $this->assertSame(LegRole::TransferFrom, $legs[0]->leg_role);
        $this->assertSame(BalanceEffect::Decrease, $legs[0]->balance_effect);
        $this->assertSame(LegRole::TransferTo, $legs[1]->leg_role);
        $this->assertSame(BalanceEffect::Increase, $legs[1]->balance_effect);

        // Neither leg is an expense — that is what makes Rule 2 structural.
        $this->assertSame(TransactionType::Transfer, $legs[0]->type);
        $this->assertSame(TransactionType::Transfer, $legs[1]->type);
    }

    public function test_a_transfer_needs_two_different_accounts(): void
    {
        $hdfc = $this->account('HDFC Bank', AccountType::Bank, '50000');

        $this->expectException(InvalidArgumentException::class);

        $this->transfers->create([
            'transaction_date' => '2026-09-10',
            'from_account_id' => $hdfc->id,
            'to_account_id' => $hdfc->id,
            'amount' => '1000',
        ]);
    }

    // ---------------------------------------------------------------------
    // Opening balances (spec section 8, Rule 4)
    // ---------------------------------------------------------------------

    public function test_opening_balance_is_preserved_and_never_counts_as_spending(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '85000');
        $card = $this->account('HDFC Credit Card', AccountType::CreditCard, '45000');

        // Starting position is intact before any activity.
        $this->assertSame('85000.00', $this->balances->balance($bank));
        $this->assertSame('45000.00', $this->balances->balance($card));

        // Existing dues did not invent a current-period expense.
        $this->assertSame('0', $this->totalSpending());
    }

    public function test_transactions_before_the_opening_date_do_not_move_the_balance(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '85000');

        // Backfilled history from before the household adopted the app. The
        // opening balance already embodies it, so counting it again would
        // double-count — but it stays visible as historical activity.
        $this->transactions->recordExpense([
            'transaction_date' => '2026-08-15',
            'account_id' => $bank->id,
            'amount' => '5000',
        ]);

        $this->assertSame('85000.00', $this->balances->balance($bank->refresh()));
        $this->assertSame('5000.00', $this->totalSpending('2026-08-01', '2026-08-31'));
    }

    // ---------------------------------------------------------------------
    // Editing (spec Rule 6)
    // ---------------------------------------------------------------------

    public function test_editing_an_amount_recalculates_the_balance(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '10000');

        $expense = $this->transactions->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $bank->id,
            'amount' => '1000',
        ]);

        $this->assertSame('9000.00', (string) $bank->refresh()->cached_balance);

        $this->transactions->update($expense, ['amount' => '2500']);

        $this->assertSame('7500.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame('2500.00', $this->totalSpending());
    }

    public function test_moving_an_expense_between_accounts_corrects_both_balances(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '10000');
        $cash = $this->account('Cash', AccountType::Cash, '5000');

        $expense = $this->transactions->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $bank->id,
            'amount' => '1000',
        ]);

        $this->transactions->update($expense, ['account_id' => $cash->id]);

        $this->assertSame('10000.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame('4000.00', (string) $cash->refresh()->cached_balance);
    }

    public function test_repointing_an_expense_from_bank_to_card_flips_the_balance_direction(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '10000');
        $card = $this->account('HDFC Credit Card', AccountType::CreditCard, '0');

        $expense = $this->transactions->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $bank->id,
            'amount' => '2000',
        ]);

        $this->assertSame(BalanceEffect::Decrease, $expense->balance_effect);

        // "I actually paid for this on the card" — the direction must invert,
        // because for a liability account an expense increases what is owed.
        $updated = $this->transactions->update($expense, ['account_id' => $card->id]);

        $this->assertSame(BalanceEffect::Increase, $updated->balance_effect);
        $this->assertSame('10000.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame('2000.00', (string) $card->refresh()->cached_balance);

        // Still exactly one expense — moving it did not duplicate it.
        $this->assertSame('2000.00', $this->totalSpending());
    }

    public function test_a_transfer_leg_cannot_be_edited_on_its_own(): void
    {
        $hdfc = $this->account('HDFC Bank', AccountType::Bank, '50000');
        $cash = $this->account('Cash', AccountType::Cash, '5000');

        $legs = $this->transfers->create([
            'transaction_date' => '2026-09-10',
            'from_account_id' => $hdfc->id,
            'to_account_id' => $cash->id,
            'amount' => '3000',
        ]);

        $this->expectException(\RuntimeException::class);

        $this->transactions->update($legs[0], ['amount' => '9999']);
    }

    public function test_editing_a_transfer_updates_both_legs_together(): void
    {
        $hdfc = $this->account('HDFC Bank', AccountType::Bank, '50000');
        $cash = $this->account('Cash', AccountType::Cash, '5000');

        $legs = $this->transfers->create([
            'transaction_date' => '2026-09-10',
            'from_account_id' => $hdfc->id,
            'to_account_id' => $cash->id,
            'amount' => '3000',
        ]);

        $this->transfers->update($legs[0], ['amount' => '4500']);

        $this->assertSame('45500.00', (string) $hdfc->refresh()->cached_balance);
        $this->assertSame('9500.00', (string) $cash->refresh()->cached_balance);

        // Both legs moved — the transfer never half-updates.
        foreach (Transaction::all() as $leg) {
            $this->assertSame('4500.00', (string) $leg->amount);
        }
    }

    // ---------------------------------------------------------------------
    // Voiding and reversal (spec Rule 7)
    // ---------------------------------------------------------------------

    public function test_voiding_an_expense_removes_its_balance_effect_but_keeps_the_record(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '10000');

        $expense = $this->transactions->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $bank->id,
            'amount' => '1000',
        ]);

        $this->transactions->void($expense, 'Entered twice by mistake');

        $this->assertSame('10000.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame('0', $this->totalSpending());

        // Controlled deletion: the row survives, with its reason, for audit.
        $this->assertDatabaseHas('transactions', [
            'id' => $expense->id,
            'void_reason' => 'Entered twice by mistake',
        ]);
        $this->assertSoftDeleted('transactions', ['id' => $expense->id]);
    }

    public function test_voiding_requires_a_reason(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '10000');

        $expense = $this->transactions->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $bank->id,
            'amount' => '1000',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->transactions->void($expense, '   ');
    }

    public function test_voiding_one_transfer_leg_voids_both_and_leaves_no_orphan_effect(): void
    {
        $hdfc = $this->account('HDFC Bank', AccountType::Bank, '50000');
        $cash = $this->account('Cash', AccountType::Cash, '5000');

        $legs = $this->transfers->create([
            'transaction_date' => '2026-09-10',
            'from_account_id' => $hdfc->id,
            'to_account_id' => $cash->id,
            'amount' => '3000',
        ]);

        $this->transfers->void($legs[0], 'Transfer never actually happened');

        // Neither side keeps a dangling half of the move.
        $this->assertSame('50000.00', (string) $hdfc->refresh()->cached_balance);
        $this->assertSame('5000.00', (string) $cash->refresh()->cached_balance);

        $this->assertSoftDeleted('transactions', ['id' => $legs[0]->id]);
        $this->assertSoftDeleted('transactions', ['id' => $legs[1]->id]);
    }

    public function test_restoring_a_voided_transfer_reinstates_both_legs(): void
    {
        $hdfc = $this->account('HDFC Bank', AccountType::Bank, '50000');
        $cash = $this->account('Cash', AccountType::Cash, '5000');

        $legs = $this->transfers->create([
            'transaction_date' => '2026-09-10',
            'from_account_id' => $hdfc->id,
            'to_account_id' => $cash->id,
            'amount' => '3000',
        ]);

        $this->transfers->void($legs[0], 'Mistake');
        $this->transactions->restore($legs[0]);

        $this->assertSame('47000.00', (string) $hdfc->refresh()->cached_balance);
        $this->assertSame('8000.00', (string) $cash->refresh()->cached_balance);
    }

    // ---------------------------------------------------------------------
    // Amount integrity
    // ---------------------------------------------------------------------

    public function test_a_zero_or_negative_amount_is_rejected(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '10000');

        $this->expectException(InvalidArgumentException::class);

        $this->transactions->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $bank->id,
            'amount' => '-500',
        ]);
    }

    public function test_fractional_amounts_stay_exact_across_many_entries(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '1000.00');

        // The classic float trap: 0.1 + 0.2 must land on 0.30, not 0.30000000004.
        foreach (['0.10', '0.20', '0.30', '99.99', '0.01'] as $amount) {
            $this->transactions->recordExpense([
                'transaction_date' => '2026-09-08',
                'account_id' => $bank->id,
                'amount' => $amount,
            ]);
        }

        $this->assertSame('899.40', (string) $bank->refresh()->cached_balance);
        $this->assertSame('100.60', $this->totalSpending());
    }

    // ---------------------------------------------------------------------
    // Splits (spec section 37)
    // ---------------------------------------------------------------------

    public function test_a_split_expense_refines_reporting_without_moving_the_balance_twice(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '10000');

        $expense = $this->transactions->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $bank->id,
            'amount' => '3000',
            'splits' => [
                ['amount' => '1800', 'notes' => 'Groceries'],
                ['amount' => '1200', 'notes' => 'Household'],
            ],
        ]);

        $this->assertCount(2, $expense->splits);

        // The account moved once, by the parent amount — not by 3000 + 3000.
        $this->assertSame('7000.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame('3000.00', $this->totalSpending());
    }

    public function test_splits_must_add_up_to_the_transaction_amount(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '10000');

        $this->expectException(InvalidArgumentException::class);

        $this->transactions->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $bank->id,
            'amount' => '3000',
            'splits' => [
                ['amount' => '1800'],
                ['amount' => '900'],
            ],
        ]);
    }

    // ---------------------------------------------------------------------
    // Traceability (spec Rule 8)
    // ---------------------------------------------------------------------

    public function test_every_cached_balance_is_re_derivable_from_the_ledger(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '85000');
        $card = $this->account('HDFC Credit Card', AccountType::CreditCard, '45000');
        $cash = $this->account('Cash', AccountType::Cash, '15000');

        $this->transactions->recordExpense([
            'transaction_date' => '2026-09-08', 'account_id' => $card->id, 'amount' => '2000',
        ]);
        $this->transactions->recordIncome([
            'transaction_date' => '2026-09-05', 'account_id' => $bank->id, 'amount' => '240000',
        ]);
        $this->transfers->create([
            'transaction_date' => '2026-09-06',
            'from_account_id' => $bank->id, 'to_account_id' => $cash->id, 'amount' => '10000',
        ]);

        // A full re-derivation from source rows must agree with every cache.
        $drift = $this->balances->recalculateAll();

        $this->assertSame([], $drift, 'Cached balances drifted from the ledger: '.json_encode($drift));
    }

    public function test_income_increases_the_balance_and_is_not_counted_as_spending(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '10000');

        $this->transactions->recordIncome([
            'transaction_date' => '2026-09-05',
            'account_id' => $bank->id,
            'amount' => '240000',
        ]);

        $this->assertSame('250000.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame('0', $this->totalSpending());
    }
}
