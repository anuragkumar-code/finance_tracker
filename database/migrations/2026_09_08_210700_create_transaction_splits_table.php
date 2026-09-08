<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Optional per-transaction split across categories/beneficiaries
 * (spec section 37, "Split expense").
 *
 * Splits refine reporting only. The parent transaction row remains the single
 * leg that moves the account balance, so a split can never double-count.
 * Application invariant: SUM(splits.amount) == parent transaction amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained('people')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index('transaction_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE transaction_splits ADD CONSTRAINT chk_splits_amount_positive CHECK (amount > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_splits');
    }
};
