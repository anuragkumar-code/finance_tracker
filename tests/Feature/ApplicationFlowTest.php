<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Transaction;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PersonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end checks that the screens a household actually uses work, and that
 * what they submit lands in the ledger correctly.
 */
class ApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_every_main_screen_loads(): void
    {
        $this->seed([CategorySeeder::class, PersonSeeder::class]);
        $account = $this->account('HDFC Bank', AccountType::Bank, '85000');

        foreach ([
            '/',
            '/quick-entry',
            '/transactions',
            '/transactions/new?type=income',
            '/transactions/new?type=transfer',
            '/accounts',
            '/accounts/create',
            "/accounts/{$account->id}",
            "/accounts/{$account->id}/edit",
            '/settings/categories',
            '/settings/people',
            '/settings/merchants',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_dashboard_works_on_a_completely_empty_install(): void
    {
        // The very first thing a new user sees must not blow up on zero data.
        $this->get('/')->assertOk()->assertSee('Start with your accounts');
    }

    public function test_quick_entry_records_a_spend_and_updates_the_balance(): void
    {
        $this->seed([CategorySeeder::class, PersonSeeder::class]);
        $account = $this->account('HDFC Bank', AccountType::Bank, '10000');
        $category = Category::where('name', 'Food')->first();

        $response = $this->post('/quick-entry', [
            'transaction_date' => '2026-09-08',
            'account_id' => $account->id,
            'amount' => '1250.50',
            'category_id' => $category->id,
            'merchant_name' => 'Big Bazaar',
            'planned_status' => 'planned',
        ]);

        $response->assertRedirect(route('quick-entry'));

        $this->assertDatabaseHas('transactions', [
            'amount' => '1250.50',
            'type' => 'expense',
            'account_id' => $account->id,
        ]);

        $this->assertSame('8749.50', (string) $account->refresh()->cached_balance);

        // A merchant typed in free-text is remembered for next time.
        $this->assertDatabaseHas('merchants', ['name' => 'Big Bazaar']);
    }

    public function test_quick_entry_learns_merchant_defaults_and_offers_them_back(): void
    {
        $this->seed([CategorySeeder::class, PersonSeeder::class]);
        $account = $this->account('HDFC Credit Card', AccountType::CreditCard);
        $category = Category::where('name', 'Shopping')->first();

        $this->post('/quick-entry', [
            'transaction_date' => '2026-09-08',
            'account_id' => $account->id,
            'amount' => '2000',
            'category_id' => $category->id,
            'merchant_name' => 'Amazon',
        ]);

        $merchant = Merchant::where('name', 'Amazon')->firstOrFail();

        $this->getJson("/merchants/{$merchant->id}/defaults")
            ->assertOk()
            ->assertJson([
                'category_id' => $category->id,
                'account_id' => $account->id,
            ]);
    }

    public function test_a_transfer_submitted_through_the_form_is_not_counted_as_spending(): void
    {
        $from = $this->account('HDFC Bank', AccountType::Bank, '50000');
        $to = $this->account('ICICI Bank', AccountType::Bank, '10000');

        $this->post('/transactions/transfer', [
            'transaction_date' => '2026-09-10',
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => '15000',
        ])->assertRedirect();

        $this->assertSame('35000.00', (string) $from->refresh()->cached_balance);
        $this->assertSame('25000.00', (string) $to->refresh()->cached_balance);

        $this->assertSame(0, Transaction::query()->spending()->count());
    }

    public function test_a_transfer_to_the_same_account_is_rejected_by_validation(): void
    {
        $account = $this->account('HDFC Bank', AccountType::Bank, '50000');

        $this->post('/transactions/transfer', [
            'transaction_date' => '2026-09-10',
            'from_account_id' => $account->id,
            'to_account_id' => $account->id,
            'amount' => '1000',
        ])->assertSessionHasErrors('to_account_id');

        $this->assertSame(0, Transaction::count());
    }

    public function test_voiding_through_the_ui_requires_a_reason(): void
    {
        $account = $this->account('HDFC Bank', AccountType::Bank, '10000');

        $expense = app(\App\Services\TransactionService::class)->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $account->id,
            'amount' => '500',
        ]);

        $this->post("/transactions/{$expense->id}/void", [])
            ->assertSessionHasErrors('void_reason');

        $this->assertNotSoftDeleted('transactions', ['id' => $expense->id]);
    }

    public function test_an_accounts_type_cannot_be_changed_once_it_has_history(): void
    {
        $account = $this->account('HDFC Bank', AccountType::Bank, '10000');

        app(\App\Services\TransactionService::class)->recordExpense([
            'transaction_date' => '2026-09-08',
            'account_id' => $account->id,
            'amount' => '500',
        ]);

        // Flipping this to a credit card would invert the meaning of every
        // entry already posted against it.
        $this->put("/accounts/{$account->id}", [
            'name' => 'HDFC Bank',
            'type' => AccountType::CreditCard->value,
            'opening_balance' => '10000',
            'opening_balance_date' => '2026-09-01',
        ])->assertSessionHasErrors('type');

        $this->assertSame(AccountType::Bank, $account->refresh()->type);
    }

    public function test_creating_an_account_seeds_its_opening_balance(): void
    {
        $this->post('/accounts', [
            'name' => 'ICICI Bank',
            'type' => AccountType::Bank->value,
            'opening_balance' => '110000',
            'opening_balance_date' => '2026-09-01',
            'currency' => 'INR',
        ])->assertRedirect(route('accounts.index'));

        $account = Account::where('name', 'ICICI Bank')->firstOrFail();

        $this->assertSame('110000.00', (string) $account->cached_balance);
        $this->assertSame(\App\Enums\NormalBalance::Asset, $account->normal_balance);
    }

    public function test_a_credit_card_account_is_stored_as_a_liability(): void
    {
        $this->post('/accounts', [
            'name' => 'HDFC Credit Card',
            'type' => AccountType::CreditCard->value,
            'opening_balance' => '45000',
            'opening_balance_date' => '2026-09-01',
        ])->assertRedirect();

        $card = Account::where('name', 'HDFC Credit Card')->firstOrFail();

        $this->assertSame(\App\Enums\NormalBalance::Liability, $card->normal_balance);
        $this->assertSame('45000.00', (string) $card->cached_balance);
    }

    public function test_transaction_filters_narrow_the_list(): void
    {
        $this->seed([CategorySeeder::class, PersonSeeder::class]);
        $bank = $this->account('HDFC Bank', AccountType::Bank, '50000');
        $cash = $this->account('Cash', AccountType::Cash, '5000');
        $service = app(\App\Services\TransactionService::class);

        $service->recordExpense([
            'transaction_date' => '2026-09-08', 'account_id' => $bank->id,
            'amount' => '1000', 'description' => 'Bank groceries',
        ]);
        $service->recordExpense([
            'transaction_date' => '2026-09-09', 'account_id' => $cash->id,
            'amount' => '200', 'description' => 'Cash snacks',
        ]);

        $this->get('/transactions?account_id='.$bank->id)
            ->assertOk()
            ->assertSee('Bank groceries')
            ->assertDontSee('Cash snacks');
    }
}
