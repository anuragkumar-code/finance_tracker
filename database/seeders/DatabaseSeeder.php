<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data only — no sample transactions.
     *
     * The household's own opening balances and accounts are entered through
     * Settings, so the ledger starts genuinely empty and every number in the
     * app traces back to something they actually recorded.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            PersonSeeder::class,
        ]);
    }
}
