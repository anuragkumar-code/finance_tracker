<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Enums\StatementStatus;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Services\CreditCardService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The credit-card screens end to end — what the household clicks, and what it
 * does to the ledger.
 */
class CreditCardScreensTest extends TestCase
{
    use RefreshDatabase;

    private function bank(string $opening = '85000'): Account
    {
        return Account::create([
            'name' => 'HDFC Bank',
            'type' => AccountType::Bank,
            'opening_balance' => $opening,
            'opening_balance_date' => '2026-09-01',
            'cached_balance' => $opening,
        ]);
    }

    private function card(string $owed = '0'): CreditCard
    {
        $account = Account::create([
            'name' => 'HDFC Regalia',
            'type' => AccountType::CreditCard,
            'opening_balance' => $owed,
            'opening_balance_date' => '2026-09-01',
            'cached_balance' => $owed,
        ]);

        return CreditCard::create([
            'account_id' => $account->id,
            'card_name' => 'HDFC Regalia',
            'credit_limit' => '200000',
            'statement_day' => 20,
            'payment_due_day' => 5,
        ]);
    }

    public function test_all_credit_card_screens_load(): void
    {
        $this->bank();
        $card = $this->card('12000');

        $statement = app(CreditCardService::class)->generateStatement(
            $card, Carbon::parse('2026-08-21'), Carbon::parse('2026-09-20')
        );

        foreach ([
            '/credit-cards',
            '/credit-cards/create',
            "/credit-cards/{$card->id}",
            "/credit-cards/{$card->id}/edit",
            "/credit-cards/{$card->id}/statements/new",
            "/credit-cards/{$card->id}/statements/{$statement->id}",
            "/credit-cards/{$card->id}/payments/new",
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_credit_cards_index_is_fine_with_no_cards(): void
    {
        $this->get('/credit-cards')->assertOk()->assertSee('No cards yet');
    }

    public function test_adding_a_card_creates_its_account_as_a_liability(): void
    {
        $this->post('/credit-cards', [
            'card_name' => 'ICICI Amazon Pay',
            'institution' => 'ICICI',
            'credit_limit' => '150000',
            'statement_day' => 18,
            'payment_due_day' => 8,
            'opening_balance' => '45000',
            'opening_balance_date' => '2026-09-01',
        ])->assertRedirect();

        $card = CreditCard::where('card_name', 'ICICI Amazon Pay')->firstOrFail();

        $this->assertSame(NormalBalance::Liability, $card->account->normal_balance);
        $this->assertSame('45000.00', (string) $card->account->cached_balance);
        $this->assertSame('45000.00', $card->outstanding());
        $this->assertSame('105000.00', $card->availableCredit());

        // The starting debt is not this month's spending.
        $this->assertSame(0, Transaction::query()->spending()->count());
    }

    public function test_recording_a_statement_adds_no_spending(): void
    {
        $card = $this->card();

        app(TransactionService::class)->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $card->account_id,
            'amount' => '2000',
        ]);

        $before = Transaction::count();

        $this->post("/credit-cards/{$card->id}/statements", [
            'period_start' => '2026-08-21',
            'period_end' => '2026-09-20',
            'statement_date' => '2026-09-20',
            'due_date' => '2026-10-05',
            'statement_amount' => '2000',
        ])->assertRedirect();

        $this->assertSame($before, Transaction::count());
        $this->assertSame('2000.00', (string) $card->statements()->first()->statement_amount);
    }

    public function test_a_statement_that_disagrees_with_our_records_warns_the_user(): void
    {
        $card = $this->card();

        app(TransactionService::class)->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $card->account_id,
            'amount' => '2000',
        ]);

        // Bank says 2,350 but we only recorded 2,000.
        $this->post("/credit-cards/{$card->id}/statements", [
            'period_start' => '2026-08-21',
            'period_end' => '2026-09-20',
            'statement_date' => '2026-09-20',
            'due_date' => '2026-10-05',
            'statement_amount' => '2350',
        ])->assertSessionHas('warning');
    }

    public function test_paying_a_bill_through_the_form_clears_debt_without_new_spending(): void
    {
        $bank = $this->bank('85000');
        $card = $this->card();

        app(TransactionService::class)->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $card->account_id,
            'amount' => '25000',
        ]);

        $this->post("/credit-cards/{$card->id}/payments", [
            'payment_date' => '2026-10-05',
            'amount' => '25000',
            'source_account_id' => $bank->id,
        ])->assertRedirect();

        $this->assertSame('60000.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame('0.00', (string) $card->account->refresh()->cached_balance);

        // Exactly one expense exists — the original purchase.
        $this->assertSame(1, Transaction::query()->spending()->count());
        $this->assertSame('25000.00', (string) Transaction::query()->spending()->sum('amount'));
    }

    public function test_a_bill_cannot_be_paid_from_a_credit_card(): void
    {
        $card = $this->card('5000');
        $otherCard = $this->card('0');

        $this->post("/credit-cards/{$card->id}/payments", [
            'payment_date' => '2026-10-05',
            'amount' => '5000',
            'source_account_id' => $otherCard->account_id,
        ])->assertSessionHasErrors('source_account_id');

        $this->assertSame('5000.00', (string) $card->account->refresh()->cached_balance);
    }

    public function test_voiding_a_payment_through_the_form_restores_the_debt(): void
    {
        $bank = $this->bank('85000');
        $card = $this->card('10000');

        $payment = app(CreditCardService::class)->pay(
            $card, $bank, '10000', Carbon::parse('2026-10-05')
        );

        $this->post("/credit-cards/{$card->id}/payments/{$payment->id}/void", [
            'void_reason' => 'Payment bounced',
        ])->assertRedirect();

        $this->assertSame('85000.00', (string) $bank->refresh()->cached_balance);
        $this->assertSame('10000.00', (string) $card->account->refresh()->cached_balance);
    }

    public function test_the_dashboard_shows_what_is_owed_and_due(): void
    {
        $this->bank();
        $card = $this->card('12000');

        app(CreditCardService::class)->generateStatement(
            $card,
            Carbon::parse('2026-08-21'),
            Carbon::parse('2026-09-20'),
            dueDate: Carbon::today()->addDays(5),
            statementAmount: '12000',
        );

        // What is owed across every card now sits in the Balances summary, and
        // the card with a bill falling due keeps its own panel. The wording
        // moved; the guarantee this test exists for did not.
        $this->get('/')
            ->assertOk()
            ->assertSee('Owed on cards')
            ->assertSee('Card bills due')
            ->assertSee('HDFC Regalia');
    }

    public function test_a_paid_statement_stops_appearing_as_due(): void
    {
        $bank = $this->bank('85000');
        $card = $this->card();

        app(TransactionService::class)->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $card->account_id,
            'amount' => '5000',
        ]);

        $cards = app(CreditCardService::class);

        $statement = $cards->generateStatement(
            $card,
            Carbon::parse('2026-08-21'),
            Carbon::parse('2026-09-20'),
            dueDate: Carbon::today()->addDays(5),
        );

        $this->assertCount(1, $cards->upcomingDues(30));

        $cards->pay($card, $bank, '5000', Carbon::today(), $statement);

        $this->assertSame(StatementStatus::Paid, $statement->refresh()->status);
        $this->assertCount(0, $cards->upcomingDues(30));
    }
}
