<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\MerchantGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * File existing merchants into groups.
 *
 * Deliberately a command rather than part of the migration. Creating the groups
 * table is a schema change with one right answer; deciding that "Thar Retraunt"
 * is a restaurant is a guess about somebody's real data, and a guess belongs
 * where it can be read before it is applied.
 *
 * Reports what it would do and changes nothing unless --apply is passed.
 */
class GroupMerchants extends Command
{
    protected $signature = 'merchants:group
        {--apply : Write the changes. Without this the command only reports.}
        {--regroup : Also re-file merchants that already have a group.}';

    protected $description = 'Assign merchants to groups by name, showing the proposed changes first';

    public function handle(): int
    {
        $groups = MerchantGroup::query()->get()->keyBy('name');

        if ($groups->isEmpty()) {
            $this->error('No merchant groups exist. Run `php artisan migrate` first.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $regroup = (bool) $this->option('regroup');

        $merchants = Merchant::query()->with('group')->orderBy('name')->get();

        if ($merchants->isEmpty()) {
            $this->info('No merchants to file.');

            return self::SUCCESS;
        }

        $rows = [];
        $changes = [];

        foreach ($merchants as $merchant) {
            $currentName = $merchant->group?->name;

            // A group someone chose by hand is never second-guessed unless the
            // caller explicitly asks for a re-file.
            if ($currentName !== null && ! $regroup) {
                $rows[] = [$merchant->name, $currentName, '—', 'kept'];

                continue;
            }

            $guess = MerchantGroup::guessNameFor($merchant->name);
            $target = $guess !== null ? $groups->get($guess) : null;

            if ($target === null) {
                // Nothing matched. Left ungrouped rather than swept into
                // "Other", because an empty cell asks to be filled in and a
                // wrong label does not.
                $rows[] = [$merchant->name, $currentName ?? '—', '—', 'no match'];

                continue;
            }

            if ($target->id === $merchant->merchant_group_id) {
                $rows[] = [$merchant->name, $currentName, $target->name, 'unchanged'];

                continue;
            }

            $rows[] = [$merchant->name, $currentName ?? '—', $target->name, $apply ? 'set' : 'would set'];
            $changes[$merchant->id] = $target->id;
        }

        $this->newLine();
        $this->table(['Merchant', 'Group now', 'Proposed', 'Action'], $rows);

        $unmatched = collect($rows)->where(3, 'no match')->count();

        $this->newLine();
        $this->line(sprintf(
            '%d merchant(s) examined, %d to change, %d with no matching rule.',
            $merchants->count(),
            count($changes),
            $unmatched
        ));

        if ($unmatched > 0) {
            $this->comment('Unmatched merchants stay ungrouped — file them by hand under Settings → Merchants.');
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('Dry run — nothing was written. Re-run with --apply to save these changes.');

            return self::SUCCESS;
        }

        if ($changes === []) {
            $this->info('Nothing to change.');

            return self::SUCCESS;
        }

        // One transaction: either every merchant is filed or none is, so a
        // failure halfway through cannot leave the list half-migrated.
        DB::transaction(function () use ($changes) {
            foreach ($changes as $merchantId => $groupId) {
                Merchant::whereKey($merchantId)->update(['merchant_group_id' => $groupId]);
            }
        });

        $this->newLine();
        $this->info(sprintf('%d merchant(s) filed.', count($changes)));

        return self::SUCCESS;
    }
}
