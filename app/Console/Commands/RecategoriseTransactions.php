<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-file existing entries under a different category.
 *
 * Built for two corrections a month of real use exposed: share purchases
 * sitting in "Other" as though they were spending, and loan EMIs doing the
 * same. Both are money leaving an account, but only one of them is
 * consumption, and "Other" had quietly grown larger than Travel, Rent or Food.
 *
 * Only the category changes. Amount, account and date are never touched, no
 * entry is created or removed, and balances cannot move — a category is
 * reporting metadata, not part of the ledger arithmetic.
 */
class RecategoriseTransactions extends Command
{
    protected $signature = 'transactions:recategorise
        {--to= : Name of the top-level category to file them under.}
        {--match=* : Text to look for in the description or merchant. Repeatable.}
        {--id=* : Specific transaction ids. Repeatable.}
        {--apply : Write the changes. Without this the command only reports.}';

    protected $description = 'Move existing entries into another category, after showing what would change';

    public function handle(): int
    {
        $target = Category::query()->whereNull('parent_id')->where('name', $this->option('to'))->first();

        if ($target === null) {
            $this->error('Pass --to with the name of an existing top-level category.');

            return self::FAILURE;
        }

        $matches = $this->option('match');
        $ids = $this->option('id');

        if ($matches === [] && $ids === []) {
            $this->error('Pass at least one --match or --id, so this can never sweep up more than you meant.');

            return self::FAILURE;
        }

        $entries = Transaction::query()
            ->where('type', 'expense')
            ->where(function ($q) use ($matches, $ids) {
                foreach ($matches as $needle) {
                    $q->orWhere('description', 'like', '%'.$needle.'%')
                        ->orWhereHas('merchant', fn ($m) => $m->where('name', 'like', '%'.$needle.'%'));
                }

                if ($ids !== []) {
                    $q->orWhereIn('id', $ids);
                }
            })
            ->with(['category', 'account', 'merchant'])
            ->orderBy('transaction_date')
            ->get();

        if ($entries->isEmpty()) {
            $this->info('Nothing matched. No entry was changed.');

            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $rows = [];
        $changing = collect();

        foreach ($entries as $entry) {
            $from = $entry->category?->name ?? 'Uncategorised';
            $already = $entry->category_id === $target->id;

            $rows[] = [
                $entry->id,
                $entry->transaction_date->format('d M Y'),
                \Illuminate\Support\Str::limit($entry->description ?: $entry->merchant?->name ?: '—', 32),
                (string) $entry->amount,
                $entry->account->name,
                $from,
                $already ? 'already there' : ($apply ? 'moved' : 'would move'),
            ];

            if (! $already) {
                $changing->push($entry);
            }
        }

        $this->newLine();
        $this->table(['#', 'Date', 'Entry', 'Amount', 'Account', 'Category now', 'Action'], $rows);

        $total = $changing->reduce(fn ($c, $e) => bcadd($c, (string) $e->amount, 2), '0.00');

        $this->newLine();
        $this->line(sprintf(
            '%d matched · %d to move into "%s" · ₹%s in total.',
            $entries->count(), $changing->count(), $target->name, number_format((float) $total, 2),
        ));

        if ($target->counts_as_spending === false) {
            $this->comment('"'.$target->name.'" is not counted as spending, so these stop appearing in spending totals.');
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('Dry run — nothing was written. Re-run with --apply to make these changes.');

            return self::SUCCESS;
        }

        if ($changing->isEmpty()) {
            $this->info('Nothing to change.');

            return self::SUCCESS;
        }

        // Category and subcategory only. A subcategory of the old parent would
        // not belong under the new one, so it is cleared rather than left
        // pointing somewhere that no longer makes sense.
        DB::transaction(function () use ($changing, $target) {
            Transaction::whereIn('id', $changing->pluck('id'))
                ->update(['category_id' => $target->id, 'subcategory_id' => null]);
        });

        $this->newLine();
        $this->info(sprintf('%d entr%s re-filed.', $changing->count(), $changing->count() === 1 ? 'y' : 'ies'));

        return self::SUCCESS;
    }
}
