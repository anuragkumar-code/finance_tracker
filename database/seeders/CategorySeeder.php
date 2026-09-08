<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The starting category hierarchy from spec section 6.
 *
 * These are a starting point, not a fixed list — categories are editable from
 * Settings, and this seeder is idempotent so re-running it never duplicates or
 * overwrites the household's own edits.
 */
class CategorySeeder extends Seeder
{
    /** @var array<string, array<int, string>> */
    private const EXPENSE_TREE = [
        'Food' => ['Groceries', 'Restaurants', 'Delivery', 'Snacks'],
        'Shopping' => ['Clothing', 'Electronics', 'Household', 'Personal'],
        'Travel' => ['Flights', 'Hotels', 'Local Transport', 'Fuel'],
        'Utilities' => ['Electricity', 'Internet', 'Mobile', 'Gas'],
        'Family' => ['My Parents', "Wife's Parents", 'Other Family'],
        'Medical' => ['Doctor', 'Medicine', 'Tests', 'Emergency'],
        'Entertainment' => ['Movies', 'Events', 'Subscriptions'],
        'Insurance' => [],
        'Education' => [],
        'Loan Interest' => [],
        'Taxes/Fees' => [],
        'Other' => [],
    ];

    /** @var array<int, string> */
    private const INCOME_CATEGORIES = [
        'Salary',
        'Bonus',
        'Interest Income',
        'Rental Income',
        'Reimbursement',
        'Other Income',
    ];

    public function run(): void
    {
        $sort = 0;

        foreach (self::EXPENSE_TREE as $parentName => $children) {
            $parent = Category::firstOrCreate(
                ['name' => $parentName, 'parent_id' => null],
                ['applies_to' => 'expense', 'sort_order' => $sort += 10],
            );

            $childSort = 0;

            foreach ($children as $childName) {
                Category::firstOrCreate(
                    ['name' => $childName, 'parent_id' => $parent->id],
                    ['applies_to' => 'expense', 'sort_order' => $childSort += 10],
                );
            }
        }

        foreach (self::INCOME_CATEGORIES as $name) {
            Category::firstOrCreate(
                ['name' => $name, 'parent_id' => null],
                ['applies_to' => 'income', 'sort_order' => $sort += 10],
            );
        }
    }
}
