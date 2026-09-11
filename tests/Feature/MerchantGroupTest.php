<?php

namespace Tests\Feature;

use App\Enums\MerchantChannel;
use App\Models\Account;
use App\Models\Merchant;
use App\Models\MerchantGroup;
use App\Services\Reporting\SpendingReportService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Merchant groups: the master behind the "where did you buy it" picker.
 *
 * The thing most worth pinning down here is that a group and a channel are
 * different axes. Blinkit sits in the E-commerce group because that is how the
 * household thinks about it, and still counts as quick commerce because that is
 * how the money behaves. Collapsing the two would silently empty a report.
 */
class MerchantGroupTest extends TestCase
{
    use RefreshDatabase;

    private Account $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bank = Account::create([
            'name' => 'Test Bank',
            'type' => 'bank',
            'normal_balance' => 'asset',
            'opening_balance' => '50000',
            'opening_balance_date' => '2026-09-01',
            'cached_balance' => '50000',
        ]);
    }

    public function test_the_default_groups_are_seeded_by_the_migration(): void
    {
        $names = MerchantGroup::pluck('name');

        $this->assertContains('Cabs & rides', $names);
        $this->assertContains('E-commerce', $names);
        $this->assertContains('Food delivery', $names);
    }

    public function test_a_merchant_name_is_matched_to_its_group(): void
    {
        $this->assertSame('Cabs & rides', MerchantGroup::guessNameFor('Uber'));
        $this->assertSame('Cabs & rides', MerchantGroup::guessNameFor('Namma Yatri'));
        $this->assertSame('E-commerce', MerchantGroup::guessNameFor('Flipkart'));
        $this->assertSame('Food delivery', MerchantGroup::guessNameFor('Swiggy'));
        $this->assertNull(MerchantGroup::guessNameFor('Some Place No Rule Knows'));
    }

    public function test_a_longer_name_match_beats_a_shorter_one(): void
    {
        // "amazon now" and "amazon" both match; the specific one has to win, or
        // rule order in the table would decide the answer.
        $this->assertSame('E-commerce', MerchantGroup::guessNameFor('Amazon Now'));
        $this->assertSame(MerchantChannel::QuickCommerce, MerchantChannel::guessFrom('Amazon Now'));
    }

    public function test_a_new_merchant_typed_into_quick_entry_is_filed_by_name(): void
    {
        $this->post('/quick-entry', [
            'transaction_date' => '2026-09-09',
            'account_id' => $this->bank->id,
            'amount' => '240',
            'merchant_name' => 'Rapido',
        ])->assertRedirect();

        $merchant = Merchant::where('name', 'Rapido')->firstOrFail();

        $this->assertSame('Cabs & rides', $merchant->group->name);
    }

    public function test_a_group_chosen_in_the_form_beats_the_name_guess(): void
    {
        $group = MerchantGroup::where('name', 'Groceries & kirana')->firstOrFail();

        $this->post('/quick-entry', [
            'transaction_date' => '2026-09-09',
            'account_id' => $this->bank->id,
            'amount' => '900',
            'merchant_name' => 'Uber',
            'merchant_group_id' => $group->id,
        ])->assertRedirect();

        // The household said groceries. Pattern matching does not know better.
        $this->assertSame('Groceries & kirana', Merchant::where('name', 'Uber')->firstOrFail()->group->name);
    }

    /**
     * The regression this whole design exists to prevent.
     *
     * Blinkit belongs to E-commerce, whose default channel is ecommerce. If the
     * group's channel were applied before the name was consulted, a ten-minute
     * delivery would be filed as online shopping and the quick-commerce figure
     * would quietly go to zero.
     */
    public function test_the_group_never_overrides_a_channel_the_name_already_settled(): void
    {
        $this->post('/quick-entry', [
            'transaction_date' => '2026-09-09',
            'account_id' => $this->bank->id,
            'amount' => '306',
            'merchant_name' => 'Blinkit',
        ])->assertRedirect();

        $merchant = Merchant::where('name', 'Blinkit')->firstOrFail();

        $this->assertSame('E-commerce', $merchant->group->name);
        $this->assertSame(MerchantChannel::QuickCommerce, $merchant->channel);
    }

    public function test_a_group_supplies_a_channel_the_name_could_not(): void
    {
        $group = MerchantGroup::where('name', 'Food delivery')->firstOrFail();

        $this->post('/quick-entry', [
            'transaction_date' => '2026-09-09',
            'account_id' => $this->bank->id,
            'amount' => '450',
            'merchant_name' => 'Toing',
            'merchant_group_id' => $group->id,
        ])->assertRedirect();

        // No rule knows "Toing", so the channel comes from the group it was
        // filed under rather than being left as a plain in-person purchase.
        $this->assertSame(MerchantChannel::FoodDelivery, Merchant::where('name', 'Toing')->firstOrFail()->channel);
    }

    public function test_spending_is_reported_by_kind_of_place(): void
    {
        $cabs = MerchantGroup::where('name', 'Cabs & rides')->firstOrFail();
        $food = MerchantGroup::where('name', 'Food delivery')->firstOrFail();

        $uber = Merchant::create(['name' => 'Uber', 'merchant_group_id' => $cabs->id]);
        $ola = Merchant::create(['name' => 'Ola', 'merchant_group_id' => $cabs->id]);
        $zomato = Merchant::create(['name' => 'Zomato', 'merchant_group_id' => $food->id]);

        // Deliberately left unfiled: it must not appear as a phantom group.
        $corner = Merchant::create(['name' => 'Corner shop']);

        $service = app(TransactionService::class);

        foreach ([[$uber, '200'], [$ola, '300'], [$zomato, '450'], [$corner, '75']] as [$merchant, $amount]) {
            $service->recordExpense([
                'transaction_date' => '2026-09-09',
                'account_id' => $this->bank->id,
                'amount' => $amount,
                'merchant_id' => $merchant->id,
            ]);
        }

        $rows = app(SpendingReportService::class)->byMerchantGroup('2026-09-01', '2026-09-30');

        $this->assertCount(2, $rows, 'The ungrouped merchant should not create a group of its own.');

        $cabsRow = $rows->firstWhere('label', 'Cabs & rides');
        $this->assertSame('500.00', $cabsRow->amount);
        $this->assertSame(2, $cabsRow->count);

        // Ordered by size, so the biggest habit leads.
        $this->assertSame('Cabs & rides', $rows->first()->label);
    }

    public function test_the_ledger_can_be_filtered_to_one_kind_of_place(): void
    {
        $cabs = MerchantGroup::where('name', 'Cabs & rides')->firstOrFail();
        $uber = Merchant::create(['name' => 'Uber', 'merchant_group_id' => $cabs->id]);
        $corner = Merchant::create(['name' => 'Corner shop']);

        $service = app(TransactionService::class);

        $service->recordExpense([
            'transaction_date' => '2026-09-09',
            'account_id' => $this->bank->id,
            'amount' => '200',
            'merchant_id' => $uber->id,
            'description' => 'Ride to work',
        ]);

        $service->recordExpense([
            'transaction_date' => '2026-09-09',
            'account_id' => $this->bank->id,
            'amount' => '75',
            'merchant_id' => $corner->id,
            'description' => 'Bread and milk',
        ]);

        // Drilling in from the report has to land on exactly the entries behind
        // the bar that was clicked.
        $this->get('/transactions?merchant_group_id='.$cabs->id)
            ->assertOk()
            ->assertSee('Ride to work')
            ->assertDontSee('Bread and milk');
    }

    public function test_groups_are_retired_rather_than_deleted(): void
    {
        $group = MerchantGroup::where('name', 'Cabs & rides')->firstOrFail();
        $uber = Merchant::create(['name' => 'Uber', 'merchant_group_id' => $group->id]);

        $this->delete('/settings/merchant-groups/'.$group->id)->assertRedirect();

        $this->assertFalse($group->refresh()->is_active);

        // Deleting would have nulled the foreign key and unfiled every merchant
        // in the group; hiding leaves them where they are.
        $this->assertSame($group->id, $uber->refresh()->merchant_group_id);
    }

    public function test_the_grouping_command_reports_before_it_writes(): void
    {
        Merchant::create(['name' => 'Uber']);

        $this->artisan('merchants:group')
            ->expectsOutputToContain('Cabs & rides')
            ->assertSuccessful();

        $this->assertNull(Merchant::where('name', 'Uber')->firstOrFail()->merchant_group_id);

        $this->artisan('merchants:group', ['--apply' => true])->assertSuccessful();

        $this->assertSame(
            'Cabs & rides',
            Merchant::where('name', 'Uber')->firstOrFail()->group->name
        );
    }

    public function test_the_grouping_command_leaves_a_hand_picked_group_alone(): void
    {
        $groceries = MerchantGroup::where('name', 'Groceries & kirana')->firstOrFail();
        Merchant::create(['name' => 'Uber', 'merchant_group_id' => $groceries->id]);

        $this->artisan('merchants:group', ['--apply' => true])->assertSuccessful();

        $this->assertSame(
            'Groceries & kirana',
            Merchant::where('name', 'Uber')->firstOrFail()->group->name
        );
    }
}
