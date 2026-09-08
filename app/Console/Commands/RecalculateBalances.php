<?php

namespace App\Console\Commands;

use App\Services\AccountBalanceService;
use Illuminate\Console\Command;

/**
 * Re-derives every account balance from its ledger rows.
 *
 * This is a consistency check, not the primary mechanism — balances are
 * maintained synchronously on every write. If this command ever reports drift,
 * something wrote to the transactions table outside the service layer.
 */
class RecalculateBalances extends Command
{
    protected $signature = 'finance:recalculate-balances';

    protected $description = 'Re-derive all account balances from the ledger and report any drift';

    public function handle(AccountBalanceService $balances): int
    {
        $drift = $balances->recalculateAll();

        if ($drift === []) {
            $this->info('All balances match the ledger.');

            return self::SUCCESS;
        }

        $this->warn('Balances drifted from the ledger and have been corrected:');

        $this->table(
            ['Account', 'Was', 'Now'],
            collect($drift)->map(fn ($d, $name) => [$name, $d['from'], $d['to']])->values()->all(),
        );

        return self::FAILURE;
    }
}
