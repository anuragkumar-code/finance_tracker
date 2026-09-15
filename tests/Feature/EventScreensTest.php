<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Event;
use App\Models\Person;
use App\Models\SharedSettlement;
use App\Services\PersonBalanceService;
use App\Services\Reporting\SpendingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The trip, the settle-up and the repayment, driven through the screens the
 * way the household will actually use them.
 */
class EventScreensTest extends TestCase
{
    use RefreshDatabase;

    private Account $bank;

    private Person $rahul;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bank = Account::create([
            'name' => 'HDFC', 'type' => 'bank', 'opening_balance' => '50000',
            'opening_balance_date' => '2026-01-01', 'cached_balance' => '50000',
        ]);

        $this->rahul = Person::create(['name' => 'Rahul', 'is_external' => true,
            'can_be_payer' => false, 'can_be_beneficiary' => false]);
    }

    public function test_the_whole_trip_from_creation_to_repayment(): void
    {
        $this->post('/events', [
            'name' => 'Alleppey',
            'kind' => 'trip',
            'start_date' => '2026-09-12',
            'end_date' => '2026-09-13',
            'people' => [$this->rahul->id],
        ])->assertRedirect();

        $trip = Event::sole();
        $holiday = Category::where('name', 'Holiday')->whereNull('parent_id')->sole();

        foreach (['4000', '2500', '1500'] as $amount) {
            $this->post('/quick-entry', [
                'transaction_date' => '2026-09-12',
                'account_id' => $this->bank->id,
                'amount' => $amount,
                'category_id' => $holiday->id,
                'event_id' => $trip->id,
            ])->assertRedirect();
        }

        $this->get('/events/'.$trip->id)->assertOk()
            ->assertSee('Settle up')
            ->assertSee('₹8,000.00');

        $this->post('/events/'.$trip->id.'/settlements', [
            'person_id' => $this->rahul->id,
            'direction' => 'they_owe',
            'amount' => '3000',
            'settled_on' => '2026-09-14',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/events/'.$trip->id)->assertOk()
            ->assertSee('Rahul owes you')
            ->assertSee('₹5,000.00');

        $this->get('/friends')->assertOk()->assertSee('Rahul')->assertSee('Got paid back');

        $this->post('/friends/'.$this->rahul->id.'/repayments', [
            'account_id' => $this->bank->id,
            'amount' => '3000',
            'date' => '2026-09-20',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('0.00', app(PersonBalanceService::class)->balance($this->rahul->refresh()));
        $this->assertSame('5000.00', app(SpendingReportService::class)->totalSpending('2026-09-01', '2026-09-30'));
        $this->assertSame('0.00', app(SpendingReportService::class)->totalIncome('2026-09-01', '2026-09-30'));
    }

    public function test_a_settlement_can_be_undone_from_the_trip_page(): void
    {
        $trip = Event::create(['name' => 'Coorg', 'kind' => 'trip', 'start_date' => '2026-09-12']);

        $this->post('/quick-entry', [
            'transaction_date' => '2026-09-12', 'account_id' => $this->bank->id,
            'amount' => '6000', 'event_id' => $trip->id,
        ]);

        $this->post('/events/'.$trip->id.'/settlements', [
            'person_id' => $this->rahul->id, 'direction' => 'they_owe',
            'amount' => '2000', 'settled_on' => '2026-09-14',
        ]);

        $this->delete('/settlements/'.SharedSettlement::sole()->id)->assertRedirect();

        $this->assertSame(0, SharedSettlement::count());
        $this->assertSame('6000.00', app(SpendingReportService::class)->totalSpending('2026-09-01', '2026-09-30'));
    }

    public function test_an_impossible_settlement_is_explained_not_crashed(): void
    {
        $trip = Event::create(['name' => 'Coorg', 'kind' => 'trip', 'start_date' => '2026-09-12']);

        $this->post('/quick-entry', [
            'transaction_date' => '2026-09-12', 'account_id' => $this->bank->id,
            'amount' => '1000', 'event_id' => $trip->id,
        ]);

        $this->from('/events/'.$trip->id)
            ->post('/events/'.$trip->id.'/settlements', [
                'person_id' => $this->rahul->id, 'direction' => 'they_owe',
                'amount' => '5000', 'settled_on' => '2026-09-14',
            ])
            ->assertRedirect('/events/'.$trip->id)
            ->assertSessionHasErrors('amount');
    }

    public function test_voiding_a_settled_entry_is_refused_with_a_reason(): void
    {
        $trip = Event::create(['name' => 'Coorg', 'kind' => 'trip', 'start_date' => '2026-09-12']);

        $this->post('/quick-entry', [
            'transaction_date' => '2026-09-12', 'account_id' => $this->bank->id,
            'amount' => '6000', 'event_id' => $trip->id,
        ]);
        $entry = $trip->expenses()->sole();

        $this->post('/events/'.$trip->id.'/settlements', [
            'person_id' => $this->rahul->id, 'direction' => 'they_owe',
            'amount' => '2000', 'settled_on' => '2026-09-14',
        ]);

        $this->from('/transactions/'.$entry->id)
            ->post('/transactions/'.$entry->id.'/void', ['void_reason' => 'oops'])
            ->assertRedirect('/transactions/'.$entry->id)
            ->assertSessionHas('error');

        $this->assertNotNull($entry->refresh()->exists);
        $this->assertFalse($entry->trashed());
    }

    public function test_quick_entry_picks_the_trip_you_are_on(): void
    {
        Event::create(['name' => 'Right now', 'kind' => 'trip',
            'start_date' => today()->subDay(), 'end_date' => today()->addDay()]);

        $this->get('/quick-entry')->assertOk()->assertSee('Picked because you are on');
    }

    public function test_the_trip_and_friends_screens_render_empty_and_full(): void
    {
        $this->get('/events')->assertOk()->assertSee('No trips or events yet');
        $this->get('/events/create')->assertOk();
        $this->get('/friends')->assertOk();

        $trip = Event::create(['name' => 'Goa', 'kind' => 'trip', 'start_date' => '2026-09-01']);

        $this->get('/events')->assertOk()->assertSee('Goa');
        $this->get('/events/'.$trip->id)->assertOk()->assertSee('Nothing recorded under this yet');
        $this->get('/events/'.$trip->id.'/edit')->assertOk();
    }

    public function test_a_friend_added_on_the_friends_page_stays_out_of_the_household(): void
    {
        $this->post('/friends', ['name' => 'Priya'])->assertRedirect();

        $priya = Person::where('name', 'Priya')->sole();

        $this->assertTrue($priya->is_external);
        $this->get('/quick-entry')->assertOk()
            ->assertDontSee('name="payer_id" value="'.$priya->id.'"', false);
    }
}
