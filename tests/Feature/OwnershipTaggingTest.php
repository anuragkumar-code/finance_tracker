<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\Person;
use App\Models\Transaction;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PersonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Both partners manage this household's money, so accounts, cards and loans
 * carry an owner tag.
 *
 * It is a label, not a permission — there are no logins, and every screen still
 * shows the whole household by default (spec section 5 warns against turning the
 * app into a competition between spouses).
 */
class OwnershipTaggingTest extends TestCase
{
    use RefreshDatabase;

    private Person $anurag;

    private Person $khushboo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CategorySeeder::class, PersonSeeder::class]);

        $this->anurag = Person::where('name', 'Anurag')->firstOrFail();
        $this->khushboo = Person::where('name', 'Khushboo')->firstOrFail();
    }

    public function test_the_household_people_are_seeded_with_real_names(): void
    {
        $names = Person::orderBy('sort_order')->pluck('name')->all();

        $this->assertSame([
            'Anurag', 'Khushboo', 'Household',
            "Anurag's Parents", "Khushboo's Parents", 'Other',
        ], $names);
    }

    public function test_an_account_can_be_tagged_to_a_person(): void
    {
        $this->post('/accounts', [
            'name' => 'Kotak Salary',
            'type' => AccountType::Bank->value,
            'owner_id' => $this->khushboo->id,
            'opening_balance' => '50000',
            'opening_balance_date' => '2026-09-01',
        ])->assertRedirect();

        $account = Account::where('name', 'Kotak Salary')->firstOrFail();

        $this->assertSame($this->khushboo->id, $account->owner_id);
        $this->assertSame('Khushboo', $account->owner->name);
    }

    public function test_an_account_without_an_owner_is_still_valid(): void
    {
        $this->post('/accounts', [
            'name' => 'Cash',
            'type' => AccountType::Cash->value,
            'opening_balance' => '5000',
            'opening_balance_date' => '2026-09-01',
        ])->assertRedirect();

        $this->assertNull(Account::where('name', 'Cash')->firstOrFail()->owner_id);
    }

    public function test_a_card_is_tagged_through_its_account(): void
    {
        $this->post('/credit-cards', [
            'card_name' => 'HDFC Regalia',
            'owner_id' => $this->anurag->id,
            'credit_limit' => '200000',
            'statement_day' => 20,
            'payment_due_day' => 5,
            'opening_balance' => '0',
            'opening_balance_date' => '2026-09-01',
        ])->assertRedirect();

        $card = CreditCard::where('card_name', 'HDFC Regalia')->firstOrFail();

        $this->assertSame($this->anurag->id, $card->account->owner_id);
    }

    public function test_a_loan_can_be_tagged_to_a_person(): void
    {
        $this->post('/loans', [
            'name' => 'Bike Loan',
            'owner_id' => $this->khushboo->id,
            'emi_amount' => '6500',
            'total_months' => 24,
            'start_date' => '2026-09-10',
            'due_day' => 10,
        ])->assertRedirect();

        $loan = Loan::where('name', 'Bike Loan')->firstOrFail();

        $this->assertSame($this->khushboo->id, $loan->owner_id);
        $this->assertSame('Khushboo', $loan->owner->name);
    }

    public function test_accounts_can_be_filtered_by_owner(): void
    {
        Account::create([
            'name' => "Anurag's Kotak", 'type' => AccountType::Bank,
            'owner_id' => $this->anurag->id,
            'opening_balance' => '1000', 'opening_balance_date' => '2026-09-01',
        ]);

        Account::create([
            'name' => "Khushboo's SBI", 'type' => AccountType::Bank,
            'owner_id' => $this->khushboo->id,
            'opening_balance' => '2000', 'opening_balance_date' => '2026-09-01',
        ]);

        $this->get('/accounts?owner='.$this->anurag->id)
            ->assertOk()
            ->assertSee("Anurag's Kotak")
            ->assertDontSee("Khushboo's SBI");

        // The default view is the whole household, not one person's slice.
        $this->get('/accounts')
            ->assertOk()
            ->assertSee("Anurag's Kotak")
            ->assertSee("Khushboo's SBI");
    }

    public function test_income_records_whose_earnings_it_was(): void
    {
        $account = Account::create([
            'name' => 'Kotak', 'type' => AccountType::Bank,
            'owner_id' => $this->khushboo->id,
            'opening_balance' => '0', 'opening_balance_date' => '2026-09-01',
        ]);

        $this->post('/transactions/income', [
            'transaction_date' => '2026-09-05',
            'account_id' => $account->id,
            'amount' => '120000',
            'payer_id' => $this->khushboo->id,
            'description' => 'September salary',
        ])->assertRedirect();

        $income = Transaction::where('description', 'September salary')->firstOrFail();

        $this->assertSame($this->khushboo->id, $income->payer_id);
    }

    public function test_spending_still_records_payer_and_beneficiary_separately(): void
    {
        $account = Account::create([
            'name' => 'Kotak', 'type' => AccountType::Bank,
            'owner_id' => $this->anurag->id,
            'opening_balance' => '50000', 'opening_balance_date' => '2026-09-01',
            'cached_balance' => '50000',
        ]);

        // Anurag pays, Khushboo's parents benefit — the two dimensions stay apart.
        $this->post('/quick-entry', [
            'transaction_date' => '2026-09-08',
            'account_id' => $account->id,
            'amount' => '10000',
            'payer_id' => $this->anurag->id,
            'beneficiary_id' => Person::where('name', "Khushboo's Parents")->firstOrFail()->id,
        ])->assertRedirect();

        $expense = Transaction::query()->spending()->firstOrFail();

        $this->assertSame('Anurag', $expense->payer->name);
        $this->assertSame("Khushboo's Parents", $expense->beneficiary->name);
    }

    public function test_renaming_a_person_keeps_their_transactions_attached(): void
    {
        $account = Account::create([
            'name' => 'Kotak', 'type' => AccountType::Bank,
            'owner_id' => $this->anurag->id,
            'opening_balance' => '50000', 'opening_balance_date' => '2026-09-01',
            'cached_balance' => '50000',
        ]);

        $this->post('/quick-entry', [
            'transaction_date' => '2026-09-08',
            'account_id' => $account->id,
            'amount' => '500',
            'payer_id' => $this->anurag->id,
        ])->assertRedirect();

        // People are referenced by id, so a rename follows through everywhere.
        $this->put('/settings/people/'.$this->anurag->id, [
            'name' => 'Anurag Kumar',
            'can_be_payer' => '1',
            'can_be_beneficiary' => '1',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('Anurag Kumar', Transaction::query()->spending()->firstOrFail()->payer->name);
        $this->assertSame('Anurag Kumar', $account->refresh()->owner->name);
    }
}
