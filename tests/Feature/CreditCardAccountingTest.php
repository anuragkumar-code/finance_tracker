<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\StatementStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Services\AccountBalanceService;
use App\Services\CreditCardService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Spec section 4 traced end to end — the rule the whole application is built
 * around: a card purchase is an expense, a card bill payment is not.
 *
 * The worked example from the spec is used verbatim: a Rs 2,000 shopping
 * purchase on 8 September, then a Rs 25,000 bill payment from the bank.
 */
class CreditCardAccountingTest extends TestCase
{
    use RefreshDatabase;

    private CreditCardService $cards;

    private TransactionService $transactions;

    private AccountBalanceService $balances;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cards = app(CreditCardService::class);
        $this->transactions = app(TransactionService::class);
        $this->balances = app(AccountBalanceService::class);
    }

    private function account(string $name, AccountType $type, string $opening = '0'): Account
    {
        return Account::create([
            'name' => $name,
            'type' => $type,
            'opening_balance' => $opening,
            'opening_balance_date' => '2026-09-01',
            'cached_balance' => $opening,
        ]);
    }

    private function card(string $opening = '0', string $limit = '200000'): CreditCard
    {
        $account = $this->account('HDFC Credit Card', AccountType::CreditCard, $opening);

        return CreditCard::create([
            'account_id' => $account->id,
            'card_name' => 'HDFC Regalia',
            'credit_limit' => $limit,
            'statement_day' => 20,
            'payment_due_day' => 5,
        ]);
    }

    private function totalSpending(string $start = '2026-09-01', string $end = '2026-09-30'): string
    {
        return (string) Transaction::query()->spending()->inPeriod($start, $end)->sum('amount');
    }

    private function purchase(CreditCard $card, string $amount, string $date = '2026-09-08'): Transaction
    {
        return $this->transactions->recordExpense([
            'transaction_date' => $date,
            'account_id' => $card->account_id,
            'amount' => $amount,
            'description' => 'Shopping',
        ]);
    }

    // -----------------------------------------------------------------
    // Purchases
    // -----------------------------------------------------------------

    public function test_a_purchase_is_recognised_on_the_purchase_date(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '85000');
        $card = $this->card();

        $this->purchase($card, '2000');

        $this->assertSame('2000.00', $this->totalSpending());
        $this->assertSame('2000.00', $card->account->refresh()->cached_balance);
        $this->assertSame('85000.00', $bank->refresh()->cached_balance);
    }

    public function test_available_credit_and_utilisation_reflect_what_is_owed(): void
    {
        $card = $this->card('0', '100000');

        $this->purchase($card, '25000');

        $card->refresh()->load('account');

        $this->assertSame('25000.00', $card->outstanding());
        $this->assertSame('75000.00', $card->availableCredit());
        $this->assertSame(25.0, $card->utilisation());
    }

    // -----------------------------------------------------------------
    // Statements group, they do not create
    // -----------------------------------------------------------------

    public function test_generating_a_statement_creates_no_new_transaction(): void
    {
        $card = $this->card();
        $this->purchase($card, '2000', '2026-09-08');
        $this->purchase($card, '3000', '2026-09-15');

        $countBefore = Transaction::count();
        $spendingBefore = $this->totalSpending();

        $statement = $this->cards->generateStatement(
            $card,
            Carbon::parse('2026-08-21'),
            Carbon::parse('2026-09-20'),
        );

        // The statement is a summary of what was already recorded.
        $this->assertSame($countBefore, Transaction::count());
        $this->assertSame($spendingBefore, $this->totalSpending());

        $this->assertSame('5000.00', (string) $statement->statement_amount);
        $this->assertCount(2, $statement->items);
    }

    public function test_a_statement_only_covers_its_own_period(): void
    {
        $card = $this->card();
        $this->purchase($card, '2000', '2026-09-08');   // inside
        $this->purchase($card, '9999', '2026-09-25');   // after period end

        $statement = $this->cards->generateStatement(
            $card,
            Carbon::parse('2026-08-21'),
            Carbon::parse('2026-09-20'),
        );

        $this->assertSame('2000.00', (string) $statement->statement_amount);
        $this->assertCount(1, $statement->items);
    }

    public function test_the_statement_figure_is_a_snapshot_that_survives_later_edits(): void
    {
        $card = $this->card();
        $purchase = $this->purchase($card, '2000', '2026-09-08');

        $statement = $this->cards->generateStatement(
            $card, Carbon::parse('2026-08-21'), Carbon::parse('2026-09-20')
        );

        // Correcting the purchase later must not silently rewrite history the
        // bank has already issued.
        $this->transactions->update($purchase, ['amount' => '2500']);

        $this->assertSame('2000.00', (string) $statement->refresh()->statement_amount);
        $this->assertSame('2000.00', (string) $statement->items->first()->amount_snapshot);

        // But the discrepancy is visible, not hidden.
        $regenerated = $this->cards->regenerateItems($statement);
        $this->assertSame('-500.00', $this->cards->statementDiscrepancy($regenerated));
    }

    public function test_a_bank_figure_differing_from_our_own_is_surfaced_not_hidden(): void
    {
        $card = $this->card();
        $this->purchase($card, '2000', '2026-09-08');

        // The real statement says 2,350 — we missed a 350 purchase somewhere.
        $statement = $this->cards->generateStatement(
            $card,
            Carbon::parse('2026-08-21'),
            Carbon::parse('2026-09-20'),
            statementAmount: '2350',
        );

        $this->assertSame('350.00', $this->cards->statementDiscrepancy($statement));
    }

    public function test_the_same_period_cannot_be_billed_twice(): void
    {
        $card = $this->card();
        $this->purchase($card, '2000');

        $this->cards->generateStatement($card, Carbon::parse('2026-08-21'), Carbon::parse('2026-09-20'));

        $this->expectException(InvalidArgumentException::class);

        $this->cards->generateStatement($card, Carbon::parse('2026-08-21'), Carbon::parse('2026-09-20'));
    }

    public function test_unbilled_transactions_exclude_anything_already_on_a_statement(): void
    {
        $card = $this->card();
        $this->purchase($card, '2000', '2026-09-08');

        $this->cards->generateStatement($card, Carbon::parse('2026-08-21'), Carbon::parse('2026-09-20'));

        $this->purchase($card, '750', '2026-09-22');

        $unbilled = $this->cards->unbilledTransactions($card);

        $this->assertCount(1, $unbilled);
        $this->assertSame('750.00', (string) $unbilled->first()->amount);
    }

    // -----------------------------------------------------------------
    // Payments — the rule that must never break
    // -----------------------------------------------------------------

    public function test_paying_the_bill_reduces_bank_and_debt_without_adding_spending(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '85000');
        $card = $this->card('23000');            // already owed
        $this->purchase($card, '2000');          // brings it to 25,000

        $spendingBefore = $this->totalSpending();
        $this->assertSame('2000.00', $spendingBefore);

        $this->cards->pay($card, $bank, '25000', Carbon::parse('2026-10-05'));

        // Bank down, debt cleared.
        $this->assertSame('60000.00', $bank->refresh()->cached_balance);
        $this->assertSame('0.00', $card->account->refresh()->cached_balance);

        // The purchases are NOT counted again (spec Decision B): September's
        // spending is unchanged, and paying the bill in October created no
        // October spending at all.
        $this->assertSame($spendingBefore, $this->totalSpending());
        $this->assertSame('0', $this->totalSpending('2026-10-01', '2026-10-31'));
    }

    public function test_a_payment_writes_two_linked_legs_neither_of_which_is_an_expense(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '85000');
        $card = $this->card('10000');

        $payment = $this->cards->pay($card, $bank, '10000', Carbon::parse('2026-10-05'));

        $legs = $payment->legs;

        $this->assertCount(2, $legs);
        $this->assertCount(1, $legs->pluck('transfer_group_id')->unique());

        foreach ($legs as $leg) {
            $this->assertSame(TransactionType::LiabilityPayment, $leg->type);
            $this->assertFalse($leg->type->countsAsSpending());
            // Both sides decrease: bank loses cash, debt shrinks.
            $this->assertSame(\App\Enums\BalanceEffect::Decrease, $leg->balance_effect);
        }
    }

    public function test_a_partial_payment_leaves_the_statement_partly_paid(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '85000');
        $card = $this->card();
        $this->purchase($card, '10000', '2026-09-08');

        $statement = $this->cards->generateStatement(
            $card, Carbon::parse('2026-08-21'), Carbon::parse('2026-09-20')
        );

        $this->cards->pay($card, $bank, '4000', Carbon::parse('2026-09-25'), $statement);

        $statement->refresh();

        $this->assertSame(StatementStatus::PartiallyPaid, $statement->status);
        $this->assertSame('4000.00', $statement->amountPaid());
        $this->assertSame('6000.00', $statement->balanceRemaining());
    }

    public function test_paying_the_rest_settles_the_statement(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '85000');
        $card = $this->card();
        $this->purchase($card, '10000', '2026-09-08');

        $statement = $this->cards->generateStatement(
            $card, Carbon::parse('2026-08-21'), Carbon::parse('2026-09-20')
        );

        $this->cards->pay($card, $bank, '4000', Carbon::parse('2026-09-25'), $statement);
        $this->cards->pay($card, $bank, '6000', Carbon::parse('2026-09-28'), $statement);

        $this->assertSame(StatementStatus::Paid, $statement->refresh()->status);
        $this->assertSame('0.00', $statement->balanceRemaining());
        $this->assertSame('0.00', $card->account->refresh()->cached_balance);
    }

    public function test_voiding_a_payment_restores_the_debt_and_the_statement(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '85000');
        $card = $this->card();
        $this->purchase($card, '10000', '2026-09-08');

        $statement = $this->cards->generateStatement(
            $card, Carbon::parse('2026-08-21'), Carbon::parse('2026-09-20')
        );

        $payment = $this->cards->pay($card, $bank, '10000', Carbon::parse('2026-09-25'), $statement);

        $this->cards->voidPayment($payment, 'Payment failed at the bank');

        $this->assertSame('85000.00', $bank->refresh()->cached_balance);
        $this->assertSame('10000.00', $card->account->refresh()->cached_balance);
        $this->assertSame(StatementStatus::Generated, $statement->refresh()->status);

        // And the spending total was never touched throughout.
        $this->assertSame('10000.00', $this->totalSpending());
    }

    public function test_a_card_cannot_pay_its_own_bill(): void
    {
        $card = $this->card('5000');

        $this->expectException(InvalidArgumentException::class);

        $this->cards->pay($card, $card->account, '5000', Carbon::parse('2026-10-05'));
    }

    public function test_a_bill_cannot_be_paid_from_another_credit_card(): void
    {
        $card = $this->card('5000');
        $otherAccount = $this->account('ICICI Credit Card', AccountType::CreditCard, '0');

        $this->expectException(InvalidArgumentException::class);

        $this->cards->pay($card, $otherAccount, '5000', Carbon::parse('2026-10-05'));
    }

    // -----------------------------------------------------------------
    // Reporting keeps purchases and payments apart (spec 19F)
    // -----------------------------------------------------------------

    public function test_card_spending_and_card_payments_are_reported_separately(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '200000');
        $card = $this->card();

        $this->purchase($card, '12000', '2026-09-08');
        $this->purchase($card, '8000', '2026-09-14');
        $this->cards->pay($card, $bank, '15000', Carbon::parse('2026-09-28'));

        $this->assertSame('20000.00', $this->cards->spendingInPeriod($card, '2026-09-01', '2026-09-30'));
        $this->assertSame('15000.00', $this->cards->paymentsInPeriod($card, '2026-09-01', '2026-09-30'));

        // Still owes the difference.
        $this->assertSame('5000.00', $card->account->refresh()->cached_balance);
    }

    public function test_a_payment_is_not_pulled_into_a_later_statement(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '200000');
        $card = $this->card();

        $this->purchase($card, '5000', '2026-09-08');
        $this->cards->pay($card, $bank, '5000', Carbon::parse('2026-09-10'));

        $statement = $this->cards->generateStatement(
            $card, Carbon::parse('2026-08-21'), Carbon::parse('2026-09-20')
        );

        // Only the purchase belongs on the statement; the payment is not a line item.
        $this->assertCount(1, $statement->items);
        $this->assertSame('5000.00', (string) $statement->statement_amount);
    }

    public function test_a_refund_on_the_card_reduces_what_is_owed(): void
    {
        $card = $this->card();
        $this->purchase($card, '5000', '2026-09-08');

        // A refund is income posted to the card: for a liability account that
        // decreases what is owed.
        $this->transactions->recordIncome([
            'transaction_date' => '2026-09-12',
            'account_id' => $card->account_id,
            'amount' => '1500',
            'description' => 'Returned item',
        ]);

        $this->assertSame('3500.00', $card->account->refresh()->cached_balance);

        // A refund is not negative spending — spending still reflects the purchase.
        $this->assertSame('5000.00', $this->totalSpending());
    }

    public function test_every_balance_still_re_derives_after_a_full_card_cycle(): void
    {
        $bank = $this->account('HDFC Bank', AccountType::Bank, '200000');
        $card = $this->card('45000');

        $this->purchase($card, '12000', '2026-09-08');
        $this->purchase($card, '3000', '2026-09-14');

        $statement = $this->cards->generateStatement(
            $card, Carbon::parse('2026-08-21'), Carbon::parse('2026-09-20')
        );

        $this->cards->pay($card, $bank, '30000', Carbon::parse('2026-10-05'), $statement);

        $this->assertSame([], $this->balances->recalculateAll());
    }
    public function test_the_due_date_lands_in_the_right_month_for_both_cycle_shapes(): void
    {
        // Due day AFTER the statement day: the bill is due that same month.
        // (SBI bills on the 7th, due on the 26th.)
        $sbiAccount = $this->account('SBI Card', AccountType::CreditCard);
        $sbi = CreditCard::create([
            'account_id' => $sbiAccount->id, 'card_name' => 'SBI',
            'credit_limit' => '100000', 'statement_day' => 7, 'payment_due_day' => 26,
        ]);

        $statement = $sbi->statementDateFor(2026, 9);
        $this->assertSame('2026-09-07', $statement->toDateString());
        $this->assertSame('2026-09-26', $sbi->dueDateFor($statement)->toDateString());

        // Due day BEFORE the statement day: the bill rolls into the next month.
        // (Kotak bills on the 21st, due on the 7th.)
        $kotakAccount = $this->account('Kotak Card', AccountType::CreditCard);
        $kotak = CreditCard::create([
            'account_id' => $kotakAccount->id, 'card_name' => 'Kotak',
            'credit_limit' => '100000', 'statement_day' => 21, 'payment_due_day' => 7,
        ]);

        $statement = $kotak->statementDateFor(2026, 9);
        $this->assertSame('2026-09-21', $statement->toDateString());
        $this->assertSame('2026-10-07', $kotak->dueDateFor($statement)->toDateString());
    }

    public function test_a_grace_period_is_never_absurdly_long(): void
    {
        // Guards the bug this replaced: treating every card as "due next month"
        // handed cards like SBI a 49-day grace period and pushed their bill out
        // of the window where the household needed to see it.
        foreach ([[7, 26], [16, 29], [11, 29], [21, 7], [25, 5], [15, 1]] as [$stmtDay, $dueDay]) {
            $account = $this->account("Card {$stmtDay}-{$dueDay}", AccountType::CreditCard);
            $card = CreditCard::create([
                'account_id' => $account->id, 'card_name' => "Card {$stmtDay}-{$dueDay}",
                'credit_limit' => '100000', 'statement_day' => $stmtDay, 'payment_due_day' => $dueDay,
            ]);

            $statement = $card->statementDateFor(2026, 9);
            $grace = $statement->diffInDays($card->dueDateFor($statement));

            $this->assertGreaterThan(0, $grace, "Card {$stmtDay}/{$dueDay} has a non-positive grace period.");
            $this->assertLessThanOrEqual(
                31, $grace,
                "Card {$stmtDay}/{$dueDay} got a {$grace}-day grace period, which no real card gives."
            );
        }
    }
}