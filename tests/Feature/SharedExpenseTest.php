<?php

namespace Tests\Feature;

use App\Enums\EventKind;
use App\Enums\SettlementDirection;
use App\Models\Account;
use App\Models\Category;
use App\Models\Event;
use App\Models\Person;
use App\Models\Transaction;
use App\Services\AccountBalanceService;
use App\Services\PersonBalanceService;
use App\Services\Reporting\SpendingReportService;
use App\Services\SharedExpenseService;
use App\Services\TransactionService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Shared costs with people outside the household.
 *
 * The rules pinned down here are the ones that would silently corrupt the
 * numbers if they broke:
 *
 * - settling never moves a bank or card balance, on any date;
 * - spending falls (or rises) by exactly the settled amount, and income never moves;
 * - a friend's repayment is a transfer, not income;
 * - undoing a settlement restores every entry to the paisa, in any order.
 */
class SharedExpenseTest extends TestCase
{
    use RefreshDatabase;

    private Account $bank;

    private Account $card;

    private Person $rahul;

    private Event $trip;

    private Category $holiday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bank = Account::create([
            'name' => 'HDFC', 'type' => 'bank', 'opening_balance' => '50000',
            'opening_balance_date' => '2026-09-01', 'cached_balance' => '50000',
        ]);

        $this->card = Account::create([
            'name' => 'Kotak ZEN', 'type' => 'credit_card', 'opening_balance' => '0',
            'opening_balance_date' => '2026-09-01', 'cached_balance' => '0',
        ]);

        $this->rahul = Person::create([
            'name' => 'Rahul', 'is_external' => true, 'can_be_payer' => false, 'can_be_beneficiary' => false,
        ]);

        $this->trip = Event::create([
            'name' => 'Alleppey', 'kind' => EventKind::Trip,
            'start_date' => '2026-09-12', 'end_date' => '2026-09-13',
        ]);

        $this->holiday = Category::where('name', 'Holiday')->whereNull('parent_id')->firstOrFail();
    }

    private function spend(string $amount, Account $account, string $date = '2026-09-12', ?Event $event = null): Transaction
    {
        return app(TransactionService::class)->recordExpense([
            'transaction_date' => $date,
            'account_id' => $account->id,
            'amount' => $amount,
            'category_id' => $this->holiday->id,
            'event_id' => ($event ?? $this->trip)->id,
        ]);
    }

    private function spending(): string
    {
        return app(SpendingReportService::class)->totalSpending('2026-09-01', '2026-09-30');
    }

    private function income(): string
    {
        return app(SpendingReportService::class)->totalIncome('2026-09-01', '2026-09-30');
    }

    private function settle(string $amount, SettlementDirection $direction = SettlementDirection::TheyOwe)
    {
        return app(SharedExpenseService::class)->settleEvent(
            $this->trip, $this->rahul, $direction, $amount, '2026-09-14', $this->holiday->id,
        );
    }

    private function balanceOf(Account $account, ?string $asOf = null): string
    {
        return app(AccountBalanceService::class)->balance($account->refresh(), $asOf);
    }

    // -----------------------------------------------------------------
    // The core case: you paid, a friend owes part of it
    // -----------------------------------------------------------------

    public function test_the_example_from_the_trip(): void
    {
        $this->spend('4000', $this->card);                   // stay
        $this->spend('2500', $this->bank);                   // houseboat
        $this->spend('1500', $this->bank, '2026-09-13');     // food

        $this->assertSame('8000.00', $this->spending());

        $this->settle('3000');

        $this->assertSame('5000.00', $this->spending(), 'Only the household\'s share should remain as spending.');
        $this->assertSame('3000.00', app(PersonBalanceService::class)->balance($this->rahul->refresh()));
        $this->assertSame('0.00', $this->income(), 'Settling must never create income.');
    }

    public function test_settling_never_moves_a_bank_or_card_balance_on_any_date(): void
    {
        $this->spend('4000', $this->card, '2026-09-12');
        $this->spend('2500', $this->bank, '2026-09-12');
        $this->spend('1500', $this->bank, '2026-09-13');

        $dates = ['2026-09-11', '2026-09-12', '2026-09-13', '2026-09-30'];

        $before = [];
        foreach ([$this->bank, $this->card] as $account) {
            foreach ($dates as $date) {
                $before[$account->name][$date] = $this->balanceOf($account, $date);
            }
        }

        $this->settle('3000');

        foreach ([$this->bank, $this->card] as $account) {
            foreach ($dates as $date) {
                $this->assertSame(
                    $before[$account->name][$date],
                    $this->balanceOf($account, $date),
                    "{$account->name} changed on {$date}; a settlement must leave every statement matching."
                );
            }

            $this->assertSame(
                $this->balanceOf($account),
                (string) $account->refresh()->cached_balance,
                "{$account->name}'s cached balance drifted from its ledger."
            );
        }
    }

    public function test_the_share_is_taken_in_proportion_so_the_trip_breakdown_stays_honest(): void
    {
        $stay = $this->spend('4000', $this->card);
        $boat = $this->spend('2500', $this->bank);
        $food = $this->spend('1500', $this->bank, '2026-09-13');

        $this->settle('3000');

        $this->assertSame('2500.00', (string) $stay->refresh()->amount);
        $this->assertSame('1562.50', (string) $boat->refresh()->amount);
        $this->assertSame('937.50', (string) $food->refresh()->amount);
    }

    public function test_rounding_never_loses_or_invents_a_paisa(): void
    {
        $this->spend('100', $this->bank);
        $this->spend('100', $this->bank);
        $this->spend('100', $this->bank);

        $this->settle('100');

        // 33.33 × 3 = 99.99; the missing paisa has to land somewhere.
        $this->assertSame('200.00', $this->spending());
        $this->assertSame('100.00', app(PersonBalanceService::class)->balance($this->rahul->refresh()));
    }

    public function test_a_friend_cannot_owe_the_whole_amount_or_more(): void
    {
        $this->spend('1000', $this->bank);

        $this->expectException(InvalidArgumentException::class);

        $this->settle('1000');
    }

    public function test_a_failed_settlement_leaves_nothing_behind(): void
    {
        $this->spend('1000', $this->bank);

        try {
            $this->settle('5000');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame('1000.00', $this->spending());
        $this->assertSame(0, \App\Models\SharedSettlement::count());
    }

    // -----------------------------------------------------------------
    // Getting paid back
    // -----------------------------------------------------------------

    public function test_a_repayment_is_a_transfer_and_never_income(): void
    {
        $this->spend('8000', $this->bank);
        $this->settle('3000');

        $bankBefore = $this->balanceOf($this->bank);

        app(PersonBalanceService::class)->recordRepayment($this->rahul->refresh(), $this->bank, '3000', '2026-09-20');

        $this->assertSame('0.00', app(PersonBalanceService::class)->balance($this->rahul->refresh()));
        $this->assertSame(bcadd($bankBefore, '3000', 2), $this->balanceOf($this->bank));
        $this->assertSame('0.00', $this->income(), 'A friend returning money is not income.');
        $this->assertSame('5000.00', $this->spending(), 'Being paid back does not change what was spent.');
    }

    public function test_a_repayment_cannot_exceed_what_is_owed(): void
    {
        $this->spend('8000', $this->bank);
        $this->settle('3000');

        $this->expectException(InvalidArgumentException::class);

        app(PersonBalanceService::class)->recordRepayment($this->rahul->refresh(), $this->bank, '3500', '2026-09-20');
    }

    public function test_writing_off_the_rest_turns_it_into_spending(): void
    {
        $this->spend('8000', $this->bank);
        $this->settle('3000');

        $service = app(PersonBalanceService::class);
        $service->recordRepayment($this->rahul->refresh(), $this->bank, '2500', '2026-09-20');
        $service->writeOff($this->rahul->refresh(), '500', '2026-09-21', $this->holiday->id, $this->trip->id);

        $this->assertSame('0.00', $service->balance($this->rahul->refresh()));
        $this->assertSame('5500.00', $this->spending());
    }

    // -----------------------------------------------------------------
    // The other direction: a friend paid, you owe part of it
    // -----------------------------------------------------------------

    public function test_our_share_of_what_a_friend_paid_is_spending_owed_to_them(): void
    {
        $this->spend('2000', $this->bank);

        $this->settle('3000', SettlementDirection::WeOwe);

        $this->assertSame('5000.00', $this->spending());
        $this->assertSame('-3000.00', app(PersonBalanceService::class)->balance($this->rahul->refresh()));
        $this->assertSame('48000.00', $this->balanceOf($this->bank), 'Nothing left the bank for the friend\'s share.');

        app(PersonBalanceService::class)->recordPayback($this->rahul->refresh(), $this->bank, '3000', '2026-09-20');

        $this->assertSame('0.00', app(PersonBalanceService::class)->balance($this->rahul->refresh()));
        $this->assertSame('45000.00', $this->balanceOf($this->bank));
        $this->assertSame('5000.00', $this->spending(), 'Paying a friend back is not spending again.');
    }

    // -----------------------------------------------------------------
    // Undoing
    // -----------------------------------------------------------------

    public function test_undo_restores_every_entry_to_the_paisa(): void
    {
        $rows = [
            $this->spend('4000', $this->card),
            $this->spend('2500', $this->bank),
            $this->spend('1499.99', $this->bank, '2026-09-13'),
        ];

        $bankBefore = $this->balanceOf($this->bank);
        $cardBefore = $this->balanceOf($this->card);

        $settlement = $this->settle('3000');
        app(SharedExpenseService::class)->undo($settlement);

        $this->assertSame(['4000.00', '2500.00', '1499.99'], array_map(fn ($r) => (string) $r->refresh()->amount, $rows));
        $this->assertSame('7999.99', $this->spending());
        $this->assertSame($bankBefore, $this->balanceOf($this->bank));
        $this->assertSame($cardBefore, $this->balanceOf($this->card));
        $this->assertSame('0.00', app(PersonBalanceService::class)->balance($this->rahul->refresh()));
    }

    public function test_two_settlements_undo_exactly_in_either_order(): void
    {
        $priya = Person::create(['name' => 'Priya', 'is_external' => true]);

        $row = $this->spend('9000', $this->bank);
        $this->spend('1000', $this->bank);

        $service = app(SharedExpenseService::class);
        $first = $this->settle('3000');
        $second = $service->settleEvent($this->trip, $priya, SettlementDirection::TheyOwe, '2000', '2026-09-14');

        $this->assertSame('5000.00', $this->spending());

        // Undo the first one first — the one whose reductions sit underneath.
        $service->undo($first);
        $this->assertSame('8000.00', $this->spending());

        $service->undo($second);
        $this->assertSame('10000.00', $this->spending());
        $this->assertSame('9000.00', (string) $row->refresh()->amount);
    }

    public function test_an_undone_settlement_entry_cannot_be_restored_on_its_own(): void
    {
        $this->spend('8000', $this->bank);
        $settlement = $this->settle('3000');
        $entryId = $settlement->entries()->value('id');

        app(SharedExpenseService::class)->undo($settlement);

        $this->expectException(RuntimeException::class);

        app(TransactionService::class)->restore(Transaction::withTrashed()->findOrFail($entryId));
    }

    // -----------------------------------------------------------------
    // Protecting settled entries
    // -----------------------------------------------------------------

    public function test_a_shared_expense_cannot_have_its_amount_changed(): void
    {
        $row = $this->spend('8000', $this->bank);
        $this->settle('3000');

        $this->expectException(RuntimeException::class);

        app(TransactionService::class)->update($row->refresh(), ['amount' => '6000']);
    }

    public function test_a_shared_expense_can_still_be_recategorised(): void
    {
        $row = $this->spend('8000', $this->bank);
        $this->settle('3000');

        $other = Category::create(['name' => 'Food', 'applies_to' => 'expense']);

        app(TransactionService::class)->update($row->refresh(), [
            'amount' => (string) $row->amount,
            'account_id' => $row->account_id,
            'transaction_date' => $row->transaction_date->toDateString(),
            'category_id' => $other->id,
        ]);

        $this->assertSame($other->id, $row->refresh()->category_id);
    }

    public function test_a_shared_expense_cannot_be_voided_while_settled(): void
    {
        $row = $this->spend('8000', $this->bank);
        $this->settle('3000');

        $this->expectException(RuntimeException::class);

        app(TransactionService::class)->void($row->refresh(), 'mistake');
    }

    public function test_the_transfer_a_settlement_wrote_cannot_be_edited_by_hand(): void
    {
        $this->spend('8000', $this->bank);
        $settlement = $this->settle('3000');

        $this->expectException(RuntimeException::class);

        app(TransferService::class)->update($settlement->entries()->first(), ['amount' => '1']);
    }

    // -----------------------------------------------------------------
    // One bill split in Quick Entry
    // -----------------------------------------------------------------

    public function test_splitting_one_bill_from_quick_entry(): void
    {
        $this->post('/quick-entry', [
            'transaction_date' => '2026-09-12',
            'account_id' => $this->card->id,
            'amount' => '1200',
            'split_person_id' => $this->rahul->id,
            'split_amount' => '600',
        ])->assertRedirect();

        $this->assertSame('600.00', $this->spending());
        $this->assertSame('1200.00', $this->balanceOf($this->card), 'The card statement still shows the full bill.');
        $this->assertSame('600.00', app(PersonBalanceService::class)->balance($this->rahul->refresh()));
    }

    // -----------------------------------------------------------------
    // Friends stay out of the household's own screens
    // -----------------------------------------------------------------

    public function test_a_friend_balance_is_not_offered_as_one_of_your_accounts(): void
    {
        $this->spend('8000', $this->bank);
        $this->settle('3000');

        $friendAccount = Account::whereNotNull('person_id')->sole();
        $link = route('accounts.show', $friendAccount);

        // Recently used is built from spending, and the friend's balance now has
        // entries — which is exactly how it could have crept into the picker.
        $this->get('/quick-entry')->assertOk()
            ->assertDontSee('name="account_id" value="'.$friendAccount->id.'"', false);
        $this->get('/')->assertOk()->assertDontSee($link, false);
        $this->get('/accounts')->assertOk()->assertDontSee($link, false);
        $this->get('/reconcile')->assertOk()->assertDontSee($link, false);
    }

    public function test_friends_are_not_offered_as_who_paid_or_who_for(): void
    {
        $this->assertFalse(Person::payers()->where('name', 'Rahul')->exists());
        $this->assertFalse(Person::beneficiaries()->where('name', 'Rahul')->exists());
    }

    public function test_a_transfer_out_of_a_card_adds_to_what_is_owed(): void
    {
        // The sign rule this feature depends on: before it, a transfer from a
        // card reduced the card's balance as if it were a bank account.
        app(TransferService::class)->create([
            'from_account_id' => $this->card->id,
            'to_account_id' => $this->bank->id,
            'amount' => '1000',
            'transaction_date' => '2026-09-12',
        ]);

        $this->assertSame('1000.00', $this->balanceOf($this->card));
        $this->assertSame('51000.00', $this->balanceOf($this->bank));
    }
}
