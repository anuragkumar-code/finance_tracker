<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trips & events, friends' balances, and shared-expense settlement.
 *
 * The problem this solves: on a trip with friends, one person pays for most
 * things and the group squares up afterwards. Recording the full amount as
 * spending overstates what the household actually consumed, and recording the
 * friend's repayment as income inflates income by the same amount. Neither is
 * true. The part paid on a friend's behalf is a short loan, and it belongs in a
 * balance that friend owes — not in spending, and not in income.
 *
 * How that is represented, and why:
 *
 * - Each friend gets an ordinary asset account (accounts.person_id), so what
 *   they owe is derived by the same balance arithmetic as every bank account
 *   and can never drift from the entries behind it. It goes negative when the
 *   household is the one that owes.
 *
 * - Settling up rewrites spending as real ledger entries rather than as an
 *   adjustment applied to totals. The friend's share is taken out of the trip's
 *   expense rows and moved to their balance with a transfer from the same
 *   account on the same date. Every bank and card balance is therefore
 *   identical before and after, on every date, and nothing that sums spending
 *   needs to know that settlements exist.
 *
 * - Each reduction is recorded against the row it came from, so a settlement
 *   can be undone exactly, to the paisa, in any order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            // A friend or relative outside the household: someone who can owe
            // or be owed, but who never appears in "who paid" or "who it was for".
            $table->boolean('is_external')->default(false)->after('is_household');
        });

        Schema::table('accounts', function (Blueprint $table) {
            // Set only on the balance account the app keeps for a friend. Every
            // screen that lists "your accounts" filters these out.
            $table->foreignId('person_id')->nullable()->after('owner_id')
                ->constrained('people')->restrictOnDelete();
            $table->unique('person_id');
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->enum('kind', ['trip', 'event'])->default('trip');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });

        Schema::create('event_person', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->unique(['event_id', 'person_id']);
        });

        Schema::create('shared_settlements', function (Blueprint $table) {
            $table->id();
            // Nullable: a single shared bill split from Quick Entry need not
            // belong to any trip.
            $table->foreignId('event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->enum('direction', ['they_owe', 'we_owe']);
            $table->decimal('amount', 14, 2);
            $table->date('settled_on');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('shared_settlement_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_settlement_id')->constrained()->cascadeOnDelete();
            // The expense row that was reduced. Restrict: an allocation must never
            // outlive the row it would need to put money back into.
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->decimal('reduced_by', 14, 2);
            $table->timestamps();

            $table->index('transaction_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('merchant_id')
                ->constrained()->nullOnDelete();

            // Set on the entries a settlement wrote (the transfers to a friend's
            // balance, or the household's share of what a friend paid), so they
            // can be recognised and are only ever removed by undoing it.
            $table->foreignId('shared_settlement_id')->nullable()->after('event_id')
                ->constrained()->nullOnDelete();
        });

        // Entries a settlement writes are marked by source as well as by
        // shared_settlement_id. The foreign key is cleared when a settlement is
        // undone, but the voided entries must still be recognisable afterwards
        // so they cannot be restored on their own and double-count the share.
        DB::statement(
            "ALTER TABLE transactions MODIFY source ENUM('manual', 'recurring', 'import', 'settlement') "
            ."NOT NULL DEFAULT 'manual'"
        );

        $this->addHolidayCategory();
    }

    /**
     * One Holiday category holding the whole cost of a trip.
     *
     * Kept apart from Food and Travel on purpose: trip meals filed under Food
     * would count against the everyday food budget and make an ordinary month
     * look like overspending. Subcategories are optional detail; the Holiday
     * total is always the complete figure, whichever of them are used.
     */
    private function addHolidayCategory(): void
    {
        if (DB::table('categories')->whereNull('parent_id')->where('name', 'Holiday')->exists()) {
            return;
        }

        $now = now();

        $parentId = DB::table('categories')->insertGetId([
            'name' => 'Holiday',
            'parent_id' => null,
            'applies_to' => 'expense',
            'is_active' => true,
            'sort_order' => (int) DB::table('categories')->max('sort_order') + 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sort = 0;

        foreach (['Stay', 'Getting there', 'Local travel', 'Food & drinks', 'Activities', 'Shopping', 'Other'] as $name) {
            DB::table('categories')->insert([
                'name' => $name,
                'parent_id' => $parentId,
                'applies_to' => 'expense',
                'is_active' => true,
                'sort_order' => $sort += 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('transactions')->where('source', 'settlement')->update(['source' => 'manual']);

        DB::statement(
            "ALTER TABLE transactions MODIFY source ENUM('manual', 'recurring', 'import') "
            ."NOT NULL DEFAULT 'manual'"
        );

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shared_settlement_id');
            $table->dropConstrainedForeignId('event_id');
        });

        Schema::dropIfExists('shared_settlement_allocations');
        Schema::dropIfExists('shared_settlements');
        Schema::dropIfExists('event_person');
        Schema::dropIfExists('events');

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['person_id']);
            $table->dropConstrainedForeignId('person_id');
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('is_external');
        });

        // The Holiday category is left in place: transactions may already be
        // filed under it, and removing it would silently uncategorise them.
    }
};
