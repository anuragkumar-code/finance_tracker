<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expense-first: stop deriving bank balances nobody maintains.
 *
 * A month of real use showed the household records expenses faithfully (115 in
 * a month) and income almost never (2 entries). Every balance is derived as
 * opening balance + income − expenses, so with income missing the bank figures
 * drift further from reality every week — one current account was showing
 * −₹56,932, and "realistically available" −₹57,314. Numbers that confident,
 * built from half a picture, are worse than no numbers.
 *
 * Two switches, both additive and reversible:
 *
 * 1. accounts.tracks_balance — a bank or cash account becomes a payment source:
 *    a label for where money went out from, with no balance anywhere. Credit
 *    cards keep theirs, because card purchases AND card bill payments are both
 *    recorded, so what is owed on them is real and is money still to flow out.
 *
 * 2. categories.counts_as_spending — money can leave an account without being
 *    consumed. Investments were landing in "Other", making it the largest
 *    category and overstating spending by ₹34,280. They stay in the ledger and
 *    on the account; they are simply counted apart from consumption.
 *
 * Nothing is deleted: opening balances, income rows and every transaction stay
 * exactly as they are. Flipping the columns back restores the old behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('tracks_balance')->default(true)->after('is_set_aside');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('counts_as_spending')->default(true)->after('applies_to');
        });

        // Bank and cash become payment sources. Credit cards, investments and
        // other assets/liabilities keep their balances.
        DB::table('accounts')->whereIn('type', ['bank', 'cash'])->update(['tracks_balance' => false]);

        $this->addCategory('Investments', false, ['Stocks & IPO', 'Mutual funds', 'Gold', 'Deposits', 'Other']);
        $this->addCategory('Loan EMI', true, []);

        // Both loans were filed under "Other", which is why that category had
        // grown larger than Travel, Rent and Food. EMIs get their own line.
        $emiCategory = DB::table('categories')->whereNull('parent_id')->where('name', 'Loan EMI')->value('id');

        if ($emiCategory !== null) {
            DB::table('loans')->update(['category_id' => $emiCategory]);
        }
    }

    private function addCategory(string $name, bool $countsAsSpending, array $children): void
    {
        if (DB::table('categories')->whereNull('parent_id')->where('name', $name)->exists()) {
            return;
        }

        $now = now();

        $parentId = DB::table('categories')->insertGetId([
            'name' => $name,
            'parent_id' => null,
            'applies_to' => 'expense',
            'counts_as_spending' => $countsAsSpending,
            'is_active' => true,
            'sort_order' => (int) DB::table('categories')->max('sort_order') + 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sort = 0;

        foreach ($children as $child) {
            DB::table('categories')->insert([
                'name' => $child,
                'parent_id' => $parentId,
                'applies_to' => 'expense',
                'counts_as_spending' => $countsAsSpending,
                'is_active' => true,
                'sort_order' => $sort += 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('counts_as_spending');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('tracks_balance');
        });

        // The Investments and Loan EMI categories are left in place: entries may
        // already be filed under them, and removing them would uncategorise
        // those rows.
    }
};
