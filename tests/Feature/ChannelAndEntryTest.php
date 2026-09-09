<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\MerchantChannel;
use App\Enums\Purpose;
use App\Models\Account;
use App\Models\Merchant;
use App\Models\Tag;
use App\Models\Transaction;
use App\Services\Reporting\SpendingReportService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PersonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quick-commerce reporting, and the transaction detail page that used to crash.
 */
class ChannelAndEntryTest extends TestCase
{
    use RefreshDatabase;

    private Account $bank;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-09');
        $this->seed([CategorySeeder::class, PersonSeeder::class]);

        $this->bank = Account::create([
            'name' => 'Kotak', 'type' => AccountType::Bank,
            'opening_balance' => '50000', 'opening_balance_date' => '2026-09-01',
            'cached_balance' => '50000',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // -----------------------------------------------------------------
    // The crash: pivot table name mismatch
    // -----------------------------------------------------------------

    public function test_a_transaction_detail_page_loads(): void
    {
        // Regression: Transaction::tags() inferred the pivot as "tag_transaction"
        // from alphabetical order while the table is "transaction_tag", so every
        // transaction page returned a 500.
        $expense = app(TransactionService::class)->recordExpense([
            'transaction_date' => '2026-09-09',
            'account_id' => $this->bank->id,
            'amount' => '863',
            'description' => 'Dinner',
        ]);

        $this->get("/transactions/{$expense->id}")
            ->assertOk()
            ->assertSee('Dinner');
    }

    public function test_tags_attach_and_read_back(): void
    {
        $tag = Tag::create(['name' => 'holiday']);

        $expense = app(TransactionService::class)->recordExpense([
            'transaction_date' => '2026-09-09',
            'account_id' => $this->bank->id,
            'amount' => '500',
            'tags' => [$tag->id],
        ]);

        $this->assertSame(['holiday'], $expense->refresh()->tags->pluck('name')->all());
        $this->assertSame(1, $tag->refresh()->transactions()->count());
    }

    // -----------------------------------------------------------------
    // Merchant channels
    // -----------------------------------------------------------------

    public function test_well_known_merchants_classify_themselves(): void
    {
        $cases = [
            'blinkit' => MerchantChannel::QuickCommerce,
            'Zepto' => MerchantChannel::QuickCommerce,
            'Swiggy Instamart' => MerchantChannel::QuickCommerce,
            'Amazon Now' => MerchantChannel::QuickCommerce,
            'Amazon' => MerchantChannel::Ecommerce,
            'Flipkart' => MerchantChannel::Ecommerce,
            'Myntra' => MerchantChannel::Ecommerce,
            'Swiggy' => MerchantChannel::FoodDelivery,
            'Zomato' => MerchantChannel::FoodDelivery,
            'Netflix' => MerchantChannel::Subscription,
            'Thar Restaurant' => MerchantChannel::Offline,
        ];

        foreach ($cases as $name => $expected) {
            $this->assertSame(
                $expected,
                MerchantChannel::guessFrom($name),
                "\"{$name}\" was classified wrongly."
            );
        }
    }

    public function test_quick_commerce_wins_over_the_plain_brand_name(): void
    {
        // "Amazon Now" is quick commerce even though "Amazon" alone is not.
        $this->assertSame(MerchantChannel::QuickCommerce, MerchantChannel::guessFrom('amazon now'));
        $this->assertSame(MerchantChannel::Ecommerce, MerchantChannel::guessFrom('amazon'));
    }

    public function test_typing_a_merchant_into_quick_entry_classifies_it(): void
    {
        $this->post('/quick-entry', [
            'transaction_date' => '2026-09-09',
            'account_id' => $this->bank->id,
            'amount' => '306',
            'merchant_name' => 'Blinkit',
        ])->assertRedirect();

        $merchant = Merchant::where('name', 'Blinkit')->firstOrFail();

        $this->assertSame(MerchantChannel::QuickCommerce, $merchant->channel);
    }

    public function test_a_channel_set_by_hand_is_never_overwritten_by_guessing(): void
    {
        $merchant = Merchant::create(['name' => 'Amazon', 'channel' => MerchantChannel::Subscription]);

        $merchant->guessChannelIfUnset();

        // The household said subscription; pattern matching does not know better.
        $this->assertSame(MerchantChannel::Subscription, $merchant->refresh()->channel);
    }

    // -----------------------------------------------------------------
    // The report that answers the actual question
    // -----------------------------------------------------------------

    public function test_spending_splits_by_how_it_was_bought(): void
    {
        $service = app(TransactionService::class);

        $spend = function (string $amount, ?string $merchantName) use ($service) {
            $merchantId = null;

            if ($merchantName !== null) {
                $merchant = Merchant::firstOrCreate(['name' => $merchantName]);
                $merchant->guessChannelIfUnset();
                $merchantId = $merchant->id;
            }

            $service->recordExpense([
                'transaction_date' => '2026-09-09',
                'account_id' => $this->bank->id,
                'amount' => $amount,
                'merchant_id' => $merchantId,
            ]);
        };

        $spend('300', 'Blinkit');
        $spend('200', 'Zepto');
        $spend('1500', 'Amazon');
        $spend('450', 'Swiggy');
        $spend('800', 'Thar Restaurant');
        $spend('100', null);

        $reports = app(SpendingReportService::class);
        $online = $reports->onlineSpending('2026-09-01', '2026-09-30');

        $this->assertSame('500.00', $online['quick_commerce']);
        $this->assertSame('1500.00', $online['ecommerce']);
        $this->assertSame('450.00', $online['food_delivery']);
        $this->assertSame('2450.00', $online['total_online']);

        // The channel rows must still account for every rupee of spending,
        // including entries with no merchant.
        $summed = $reports->byChannel('2026-09-01', '2026-09-30')
            ->reduce(fn ($c, $r) => bcadd($c, $r->amount, 2), '0.00');

        $this->assertSame($reports->totalSpending('2026-09-01', '2026-09-30'), $summed);
    }

    public function test_the_reports_page_shows_the_channel_breakdown(): void
    {
        $merchant = Merchant::create(['name' => 'Blinkit', 'channel' => MerchantChannel::QuickCommerce]);

        app(TransactionService::class)->recordExpense([
            'transaction_date' => '2026-09-09',
            'account_id' => $this->bank->id,
            'amount' => '306',
            'merchant_id' => $merchant->id,
        ]);

        $this->get('/reports')
            ->assertOk()
            ->assertSee('Quick commerce')
            ->assertSee('306.00');
    }

    // -----------------------------------------------------------------
    // Simplified purpose
    // -----------------------------------------------------------------

    public function test_purpose_is_a_short_understandable_list(): void
    {
        $this->assertSame(
            ['need', 'want', 'family', 'investment', 'debt'],
            array_map(fn (Purpose $p) => $p->value, Purpose::cases())
        );
    }

    public function test_a_transaction_can_be_saved_with_the_new_purpose(): void
    {
        $this->post('/quick-entry', [
            'transaction_date' => '2026-09-09',
            'account_id' => $this->bank->id,
            'amount' => '863',
            'purpose' => 'want',
        ])->assertRedirect();

        $this->assertSame(Purpose::Want, Transaction::query()->spending()->firstOrFail()->purpose);
    }

    // -----------------------------------------------------------------
    // Quick entry layout
    // -----------------------------------------------------------------

    public function test_quick_entry_groups_accounts_and_hides_set_aside_money(): void
    {
        Account::create([
            'name' => 'HDFC Card', 'type' => AccountType::CreditCard,
            'opening_balance' => '0', 'opening_balance_date' => '2026-09-01',
        ]);

        Account::create([
            'name' => 'Emergency fund', 'type' => AccountType::Bank,
            'opening_balance' => '225000', 'opening_balance_date' => '2026-09-01',
            'cached_balance' => '225000', 'is_set_aside' => true,
        ]);

        $this->get('/quick-entry')
            ->assertOk()
            ->assertSee('Bank &amp; cash', false)
            ->assertSee('Credit cards')
            ->assertSee('Kotak')
            ->assertSee('HDFC Card')
            // Ring-fenced money is not offered as a way to pay for things.
            ->assertDontSee('Emergency fund');
    }

    public function test_recently_used_accounts_are_offered_first(): void
    {
        $cash = Account::create([
            'name' => 'Wallet', 'type' => AccountType::Cash,
            'opening_balance' => '2000', 'opening_balance_date' => '2026-09-01',
            'cached_balance' => '2000',
        ]);

        $service = app(TransactionService::class);

        foreach (range(1, 3) as $i) {
            $service->recordExpense([
                'transaction_date' => '2026-09-08',
                'account_id' => $cash->id,
                'amount' => '50',
            ]);
        }

        $this->get('/quick-entry')->assertOk()->assertSee('Recently used');
    }
}
