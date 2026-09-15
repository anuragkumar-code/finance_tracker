<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Person;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * What friends owe the household, and what it owes them.
 *
 * Each friend's balance is an ordinary asset account: positive when they owe
 * the household, negative when the household owes them. Keeping it in the
 * ledger rather than as a separate tally means the figure is always derived
 * from the entries behind it — a repayment, a settlement, a write-off — and
 * can be traced line by line on the account's own page.
 *
 * Every movement here is either a transfer (money actually changing hands) or,
 * for a write-off, an expense. None of them is income: a friend handing back
 * money they borrowed does not make the household richer than it was before
 * it lent it.
 */
class PersonBalanceService
{
    private const SCALE = 2;

    /**
     * Balance accounts open on a date earlier than any entry could carry.
     *
     * Balances ignore rows dated before an account's opening date, because an
     * opening balance already embodies them. A friend's account is created the
     * first time it is needed — often while settling a trip that happened days
     * earlier — so opening it "today" would silently drop that trip's entries.
     */
    private const OPENED_ON = '2000-01-01';

    public function __construct(
        private readonly TransferService $transfers,
        private readonly TransactionService $transactions,
    ) {}

    /** The friend's balance account, created on first use. */
    public function accountFor(Person $person): Account
    {
        if (! $person->is_external) {
            throw new InvalidArgumentException(
                "{$person->name} is part of the household. Balances are only kept for people outside it."
            );
        }

        return Account::firstOrCreate(
            ['person_id' => $person->getKey()],
            [
                'name' => $person->name,
                'type' => AccountType::OtherAsset,
                'opening_balance' => '0',
                'opening_balance_date' => self::OPENED_ON,
                'cached_balance' => '0',
                'is_active' => true,
                'notes' => 'Kept by the app: what this person owes you, or you owe them when negative.',
            ],
        );
    }

    /** Positive: they owe the household. Negative: the household owes them. */
    public function balance(Person $person): string
    {
        $account = $person->balanceAccount;

        return $account === null ? '0.00' : bcadd((string) $account->cached_balance, '0', self::SCALE);
    }

    /**
     * Every friend with a balance, largest first.
     *
     * @return Collection<int, object{person: Person, balance: string, account: ?Account}>
     */
    public function summary(bool $includeSettled = false): Collection
    {
        return Person::query()
            ->external()
            ->with('balanceAccount')
            ->ordered()
            ->get()
            ->map(fn (Person $person) => (object) [
                'person' => $person,
                'account' => $person->balanceAccount,
                'balance' => $this->balance($person),
            ])
            ->filter(fn ($row) => $includeSettled || bccomp($row->balance, '0', self::SCALE) !== 0)
            ->sortByDesc(fn ($row) => abs((float) $row->balance))
            ->values();
    }

    /** Total owed to the household across all friends (ignores what it owes). */
    public function totalOwedToUs(): string
    {
        return $this->summary()->reduce(
            fn (string $carry, $row) => bccomp($row->balance, '0', self::SCALE) === 1
                ? bcadd($carry, $row->balance, self::SCALE)
                : $carry,
            '0.00',
        );
    }

    /** Total the household owes across all friends, as a positive figure. */
    public function totalWeOwe(): string
    {
        return $this->summary()->reduce(
            fn (string $carry, $row) => bccomp($row->balance, '0', self::SCALE) === -1
                ? bcadd($carry, bcsub('0', $row->balance, self::SCALE), self::SCALE)
                : $carry,
            '0.00',
        );
    }

    /**
     * A friend paying back what they owe. A transfer from their balance into a
     * household account — the money arrives, and the debt shrinks by the same
     * amount.
     *
     * @return Collection<int, Transaction>
     */
    public function recordRepayment(Person $person, Account $into, string $amount, string $date, ?string $note = null): Collection
    {
        $this->assertOwnAccount($into);

        $owed = $this->balance($person);

        if (bccomp($owed, '0', self::SCALE) !== 1) {
            throw new InvalidArgumentException("{$person->name} does not owe you anything at the moment.");
        }

        $this->assertNotMoreThan($amount, $owed, "{$person->name} owes you ₹{$owed}. A repayment larger than that would turn it into money you owe them — record the extra separately if that is really what happened.");

        return $this->transfers->create([
            'from_account_id' => $this->accountFor($person),
            'to_account_id' => $into,
            'amount' => $amount,
            'transaction_date' => $date,
            'description' => "Repayment from {$person->name}",
            'notes' => $note,
        ]);
    }

    /**
     * The household paying back what it owes a friend.
     *
     * @return Collection<int, Transaction>
     */
    public function recordPayback(Person $person, Account $from, string $amount, string $date, ?string $note = null): Collection
    {
        $this->assertOwnAccount($from);

        $owed = bcsub('0', $this->balance($person), self::SCALE);

        if (bccomp($owed, '0', self::SCALE) !== 1) {
            throw new InvalidArgumentException("You do not owe {$person->name} anything at the moment.");
        }

        $this->assertNotMoreThan($amount, $owed, "You owe {$person->name} ₹{$owed}. Paying more than that would leave them owing you.");

        return $this->transfers->create([
            'from_account_id' => $from,
            'to_account_id' => $this->accountFor($person),
            'amount' => $amount,
            'transaction_date' => $date,
            'description' => "Paid back to {$person->name}",
            'notes' => $note,
        ]);
    }

    /**
     * Letting some or all of a debt go.
     *
     * Once it is not coming back, the money really was spent on whatever it
     * paid for — so it becomes an expense, and the balance shrinks to match.
     */
    public function writeOff(Person $person, string $amount, string $date, ?int $categoryId, ?int $eventId = null): Transaction
    {
        $owed = $this->balance($person);

        if (bccomp($owed, '0', self::SCALE) !== 1) {
            throw new InvalidArgumentException("{$person->name} does not owe you anything to write off.");
        }

        $this->assertNotMoreThan($amount, $owed, "{$person->name} only owes you ₹{$owed}.");

        return DB::transaction(fn () => $this->transactions->recordExpense([
            'account_id' => $this->accountFor($person),
            'amount' => $amount,
            'transaction_date' => $date,
            'category_id' => $categoryId,
            'event_id' => $eventId,
            'description' => "Written off — {$person->name} is not paying this back",
        ]));
    }

    private function assertOwnAccount(Account $account): void
    {
        if ($account->isFriendBalance()) {
            throw new InvalidArgumentException('Choose one of your own accounts, not a friend\'s balance.');
        }
    }

    private function assertNotMoreThan(string $amount, string $limit, string $message): void
    {
        if (! is_numeric($amount) || bccomp($amount, '0', self::SCALE) !== 1) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        if (bccomp($amount, $limit, self::SCALE) === 1) {
            throw new InvalidArgumentException($message);
        }
    }
}
