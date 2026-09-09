<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly spending targets per category (spec section 22).
 *
 * Budgets are dated rather than a single mutable amount: raising the food budget
 * in March must not silently rewrite what February was judged against. The
 * budget in force for a month is the one whose date range covers it, so past
 * comparisons stay true to what was actually agreed at the time.
 *
 * `effective_to` is null while a budget is current.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);

            // Stored as the first of the month it takes effect.
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'effective_from']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE budgets ADD CONSTRAINT chk_budgets_amount_positive CHECK (amount > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
