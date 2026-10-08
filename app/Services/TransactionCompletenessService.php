<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Which entries are still missing something worth filling in.
 *
 * Recording a spend has to stay fast, so Quick Entry asks for almost nothing.
 * The cost is entries that arrive half-labelled, and a report that quietly
 * says less than it could — 63% of entries carry no merchant, which is why the
 * quick-commerce figures read mostly "not recorded".
 *
 * Two tiers, because a list of everything imperfect is a list nobody opens:
 *
 *   NEEDS — only the household knows these. A spend with no category cannot be
 *           reported on at all, and planned/unplanned is a judgement no rule
 *           can make later.
 *   RICHER — merchant, subcategory, who it was for. Each makes a report sharper
 *           without which nothing is actually wrong.
 *
 * Gaps that are not gaps are excluded by rule rather than by hand: an EMI has
 * no merchant, and a category with no children cannot have a subcategory.
 */
class TransactionCompletenessService
{
    public const NEEDS = 'needs';

    public const RICHER = 'richer';

    /**
     * Entries written by the app itself rather than typed in.
     *
     * An EMI, a confirmed recurring charge or a settlement entry has no shop
     * behind it, so asking for a merchant would make the list permanently
     * dirty with rows nobody can clean.
     */
    private function scopeAutomatic(Builder $query): Builder
    {
        return $query->whereNotNull('loan_payment_id')
            ->orWhereIn('source', ['recurring', 'settlement']);
    }

    private function isAutomatic(Transaction $t): bool
    {
        return $t->loan_payment_id !== null || in_array($t->source, ['recurring', 'settlement'], true);
    }

    /** Categories that have children, so a subcategory is a fair thing to ask for. */
    private function parentCategoryIds(): array
    {
        return Category::query()
            ->whereNull('parent_id')
            ->whereHas('children')
            ->pluck('id')
            ->all();
    }

    /** Categories that are themselves a child — filing an entry here skews the roll-up. */
    private function childCategoryIds(): array
    {
        return Category::query()->whereNotNull('parent_id')->pluck('id')->all();
    }

    /**
     * The base row set: spending entries only.
     *
     * Income, transfers and card bill payments are not things anyone needs to
     * categorise, and including them would make the list look hopeless.
     */
    public function base(): Builder
    {
        return Transaction::query()->spending();
    }

    /** Entries with at least one gap of the given tier. */
    public function query(string $tier): Builder
    {
        $query = $this->base();

        return $tier === self::NEEDS
            ? $query->where(fn (Builder $q) => $this->needsConditions($q))
            : $query->where(fn (Builder $q) => $this->richerConditions($q));
    }

    private function needsConditions(Builder $q): Builder
    {
        return $q->whereNull('category_id')
            ->orWhereNull('planned_status')
            ->orWhereNull('purpose')
            ->orWhereIn('category_id', $this->childCategoryIds());
    }

    /**
     * Only entries someone actually typed in are asked for a merchant, a payer
     * or a beneficiary. An EMI has no shop, and the person behind it is the
     * account it leaves from — asking would leave rows nobody can ever clear.
     */
    private function richerConditions(Builder $q): Builder
    {
        $parents = $this->parentCategoryIds();

        return $q
            ->orWhere(fn (Builder $w) => $w
                ->whereNull('loan_payment_id')
                ->whereNotIn('source', ['recurring', 'settlement'])
                ->where(fn (Builder $i) => $i
                    ->whereNull('merchant_id')
                    ->orWhereNull('beneficiary_id')
                    ->orWhereNull('payer_id')))
            ->orWhere(fn (Builder $w) => $w
                ->whereNull('subcategory_id')
                ->whereIn('category_id', $parents));
    }

    /**
     * What a single entry is missing, as labels for the screen.
     *
     * @return array<int, array{key: string, label: string, tier: string}>
     */
    public function gapsFor(Transaction $t): array
    {
        $gaps = [];

        if ($t->category_id === null) {
            $gaps[] = ['key' => 'category_id', 'label' => 'Category', 'tier' => self::NEEDS];
        } elseif ($t->category !== null && $t->category->parent_id !== null) {
            // Filed under a subcategory as though it were a top-level one, so
            // it shows as its own row in the category report instead of rolling
            // up into its parent.
            $gaps[] = ['key' => 'category_id', 'label' => 'Filed under a subcategory', 'tier' => self::NEEDS];
        }

        if ($t->planned_status === null) {
            $gaps[] = ['key' => 'planned_status', 'label' => 'Planned or not', 'tier' => self::NEEDS];
        }

        if ($t->purpose === null) {
            $gaps[] = ['key' => 'purpose', 'label' => 'Purpose', 'tier' => self::NEEDS];
        }

        if ($t->merchant_id === null && ! $this->isAutomatic($t)) {
            $gaps[] = ['key' => 'merchant_id', 'label' => 'Merchant', 'tier' => self::RICHER];
        }

        if ($t->subcategory_id === null && $t->category !== null && $t->category->children->isNotEmpty()) {
            $gaps[] = ['key' => 'subcategory_id', 'label' => 'Subcategory', 'tier' => self::RICHER];
        }

        if (! $this->isAutomatic($t)) {
            if ($t->payer_id === null) {
                $gaps[] = ['key' => 'payer_id', 'label' => 'Who paid', 'tier' => self::RICHER];
            }

            if ($t->beneficiary_id === null) {
                $gaps[] = ['key' => 'beneficiary_id', 'label' => 'Who it was for', 'tier' => self::RICHER];
            }
        }

        return $gaps;
    }

    /** How many entries sit in each tier, for the badge and the tabs. */
    public function counts(): array
    {
        return [
            self::NEEDS => (clone $this)->query(self::NEEDS)->count(),
            self::RICHER => (clone $this)->query(self::RICHER)->count(),
        ];
    }

    /**
     * How complete each of the last few months is.
     *
     * Measured against the NEEDS tier only: a month is not incomplete because
     * nobody named the shop.
     *
     * @return Collection<int, object{month: string, label: string, total: int, missing: int, percent: int}>
     */
    public function monthlyCompleteness(int $months = 6): Collection
    {
        return collect(range($months - 1, 0))->map(function (int $back) {
            $month = now()->startOfMonth()->subMonthsNoOverflow($back);
            $start = $month->toDateString();
            $end = $month->copy()->endOfMonth()->toDateString();

            $total = $this->base()->inPeriod($start, $end)->count();
            $missing = $this->query(self::NEEDS)->inPeriod($start, $end)->count();

            return (object) [
                'month' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
                'total' => $total,
                'missing' => $missing,
                'percent' => $total === 0 ? 100 : (int) round(($total - $missing) / $total * 100),
            ];
        })->values();
    }
}
