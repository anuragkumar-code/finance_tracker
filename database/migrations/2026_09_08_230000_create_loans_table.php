<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loans, deliberately simplified (household decision, 2026-09-08).
 *
 * The spec's section 11 allows for a principal/interest split. This build skips
 * it: a loan is described by its EMI, its tenure in months and when it started,
 * because those are the numbers the household actually knows and they do not
 * change. Everything else is derived:
 *
 *   total_payable   = emi_amount * total_months
 *   paid            = sum of instalments marked paid
 *   remaining       = total_payable - paid
 *
 * Consequence, stated plainly: `remaining` is CASH STILL TO PAY, not principal
 * outstanding — it includes future interest. Net worth therefore treats future
 * interest as debt, which is conservative rather than exact. The UI labels it
 * as such so the number is never mistaken for principal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('lender', 100)->nullable();

            $table->decimal('emi_amount', 14, 2);
            $table->unsignedSmallInteger('total_months');
            $table->date('start_date');
            // Derived from start_date + total_months, stored so it can be sorted on.
            $table->date('end_date');
            $table->unsignedTinyInteger('due_day');

            $table->foreignId('payment_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            // Category the generated EMI expense is filed under.
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->enum('status', ['active', 'closed'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('end_date');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE loans ADD CONSTRAINT chk_loans_emi_positive CHECK (emi_amount > 0)');
            DB::statement('ALTER TABLE loans ADD CONSTRAINT chk_loans_months_positive CHECK (total_months > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
