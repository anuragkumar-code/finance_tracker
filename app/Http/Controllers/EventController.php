<?php

namespace App\Http\Controllers;

use App\Enums\EventKind;
use App\Enums\SettlementDirection;
use App\Models\Category;
use App\Models\Event;
use App\Models\Person;
use App\Models\SharedSettlement;
use App\Models\Transaction;
use App\Services\PersonBalanceService;
use App\Services\SharedExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Trips & events: the cost of an occasion as one figure, and squaring up the
 * part of it that friends shared.
 */
class EventController extends Controller
{
    private const SCALE = 2;

    public function __construct(
        private readonly SharedExpenseService $shared,
        private readonly PersonBalanceService $balances,
    ) {}

    public function index(): View
    {
        $events = Event::query()
            ->latestFirst()
            ->withSum('expenses as cost', 'amount')
            ->withCount('expenses')
            ->with(['people', 'settlements.person'])
            ->get();

        return view('events.index', [
            'events' => $events,
            'current' => $events->first(fn (Event $e) => ! $e->is_archived && $e->covers(today())),
            'holidayByYear' => $this->holidaySpendingByYear(),
            'owedToUs' => $this->balances->totalOwedToUs(),
            'weOwe' => $this->balances->totalWeOwe(),
        ]);
    }

    public function create(): View
    {
        return view('events.create', $this->formData() + ['event' => new Event([
            'kind' => EventKind::Trip,
            'start_date' => today(),
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $event = DB::transaction(function () use ($data) {
            $event = Event::create($data);
            $event->people()->sync($data['people'] ?? []);

            return $event;
        });

        return redirect()
            ->route('events.show', $event)
            ->with('status', "\"{$event->name}\" created. While it is on, Quick Entry files new spends under it for you.");
    }

    public function show(Event $event): View
    {
        $event->load(['people', 'settlements.person']);

        $expenses = $event->expenses()
            ->with(['account', 'category', 'subcategory', 'merchant', 'settlement.person', 'shareAllocations'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $theyOwe = $this->sumSettlements($event->settlements, SettlementDirection::TheyOwe);
        $weOwe = $this->sumSettlements($event->settlements, SettlementDirection::WeOwe);

        // What actually left the household's own accounts for this event: the
        // household's own spending plus the shares it covered for friends.
        $paidFromOwn = $this->sum($expenses->reject(fn (Transaction $t) => $t->account->isFriendBalance()));

        $friends = Person::query()->external()->active()->ordered()->get();

        return view('events.show', [
            'event' => $event,
            'expenses' => $expenses,
            'ourCost' => $this->sum($expenses),
            'paidOutOfPocket' => bcadd($paidFromOwn, $theyOwe, self::SCALE),
            'theyOwe' => $theyOwe,
            'weOwe' => $weOwe,
            'breakdown' => $this->breakdown($expenses),
            'byDay' => $expenses->groupBy(fn (Transaction $t) => $t->transaction_date->toDateString())
                ->map(fn (Collection $rows, string $date) => (object) [
                    'date' => \Illuminate\Support\Carbon::parse($date),
                    'amount' => $this->sum($rows),
                    'count' => $rows->count(),
                ])
                ->sortKeys()
                ->values(),
            'friends' => $friends,
            'friendBalances' => $friends
                ->filter(fn (Person $p) => $event->people->contains($p) || $event->settlements->contains('person_id', $p->id))
                ->map(fn (Person $p) => (object) ['person' => $p, 'balance' => $this->balances->balance($p)])
                ->values(),
            'categories' => Category::query()->active()->topLevel()->where('applies_to', '!=', 'income')->ordered()->get(),
            'holiday' => $this->holidayCategory(),
        ]);
    }

    public function edit(Event $event): View
    {
        $event->load('people');

        return view('events.edit', $this->formData() + ['event' => $event]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($event, $data, $request) {
            $event->update($data + ['is_archived' => $request->boolean('is_archived')]);
            $event->people()->sync($data['people'] ?? []);
        });

        return redirect()->route('events.show', $event)->with('status', 'Saved.');
    }

    /**
     * Deleted only while nothing hangs off it.
     *
     * An event with entries is part of the ledger's history, and one with a
     * settlement has reshaped real entries — both are archived instead.
     */
    public function destroy(Event $event): RedirectResponse
    {
        if ($event->transactions()->exists() || $event->settlements()->exists()) {
            $event->update(['is_archived' => true]);

            return redirect()
                ->route('events.index')
                ->with('status', "\"{$event->name}\" has entries, so it was archived rather than deleted.");
        }

        $event->delete();

        return redirect()->route('events.index')->with('status', 'Deleted.');
    }

    public function settle(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'person_id' => ['required', Rule::exists('people', 'id')->where('is_external', true)],
            'direction' => ['required', new Enum(SettlementDirection::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999.99'],
            'settled_on' => ['required', 'date'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'person_id.exists' => 'Choose a friend. Settling up is only for people outside the household.',
        ]);

        try {
            $settlement = $this->shared->settleEvent(
                $event,
                Person::findOrFail($data['person_id']),
                SettlementDirection::from($data['direction']),
                (string) $data['amount'],
                $data['settled_on'],
                $data['category_id'] ?? null,
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        $person = $settlement->person;
        $amount = \App\Support\Money::inr($settlement->amount);

        return back()->with('status', $settlement->direction === SettlementDirection::TheyOwe
            ? "{$person->name} owes you {$amount}. It has been taken out of this trip's spending."
            : "You owe {$person->name} {$amount}. It has been added to this trip's spending.");
    }

    public function undoSettlement(SharedSettlement $settlement): RedirectResponse
    {
        $name = $settlement->person->name;

        try {
            $this->shared->undo($settlement);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Settlement with {$name} undone. Every entry is back as it was.");
    }

    private function formData(): array
    {
        return [
            'kinds' => EventKind::cases(),
            'household' => Person::query()->active()->where('is_external', false)->where('is_household', false)->ordered()->get(),
            'friends' => Person::query()->active()->external()->ordered()->get(),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', new Enum(EventKind::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'people' => ['nullable', 'array'],
            'people.*' => ['integer', 'exists:people,id'],
        ], [
            'end_date.after_or_equal' => 'The trip cannot end before it starts.',
        ]);
    }

    /**
     * Where the event's money went, by subcategory where one was chosen and by
     * category otherwise — "Holiday › Stay" reads better than a bare "Holiday".
     *
     * @param  Collection<int, Transaction>  $expenses
     */
    private function breakdown(Collection $expenses): Collection
    {
        $total = $this->sum($expenses);

        return $expenses
            ->groupBy(fn (Transaction $t) => $t->subcategory?->name ?? $t->category?->name ?? 'Uncategorised')
            ->map(fn (Collection $rows, string $label) => (object) [
                'label' => $label,
                'amount' => $this->sum($rows),
                'share' => bccomp($total, '0', self::SCALE) === 1
                    ? (int) round((float) $this->sum($rows) / (float) $total * 100)
                    : 0,
            ])
            ->sortByDesc(fn ($row) => (float) $row->amount)
            ->values();
    }

    /** Holiday spending per calendar year, newest first. */
    private function holidaySpendingByYear(): Collection
    {
        $holiday = $this->holidayCategory();

        if ($holiday === null) {
            return collect();
        }

        return Transaction::query()
            ->spending()
            ->where('category_id', $holiday->id)
            ->selectRaw('YEAR(transaction_date) AS year, SUM(amount) AS amount, COUNT(DISTINCT event_id) AS trips')
            ->groupByRaw('YEAR(transaction_date)')
            ->orderByDesc('year')
            ->get()
            ->map(fn ($row) => (object) [
                'year' => (int) $row->year,
                'amount' => bcadd((string) $row->amount, '0', self::SCALE),
                'trips' => (int) $row->trips,
            ]);
    }

    private function holidayCategory(): ?Category
    {
        return Category::query()->whereNull('parent_id')->where('name', 'Holiday')->first();
    }

    /** @param  Collection<int, Transaction>  $rows */
    private function sum(Collection $rows): string
    {
        return $rows->reduce(fn (string $carry, Transaction $t) => bcadd($carry, (string) $t->amount, self::SCALE), '0.00');
    }

    private function sumSettlements(Collection $settlements, SettlementDirection $direction): string
    {
        return $settlements
            ->filter(fn (SharedSettlement $s) => $s->direction === $direction)
            ->reduce(fn (string $carry, SharedSettlement $s) => bcadd($carry, (string) $s->amount, self::SCALE), '0.00');
    }
}
