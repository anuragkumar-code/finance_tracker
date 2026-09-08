<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A specific expected date for a recurring commitment.
 *
 * Exists so "we expect to pay rent on the 5th" is a distinct fact from "rent
 * was paid" — spec section 13 requires the app to show upcoming entries without
 * assuming they happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transaction_occurrences', function (Blueprint $table) {
            $table->id();
            // Named explicitly: the conventional name would exceed MySQL's
            // 64-character identifier limit.
            $table->foreignId('recurring_transaction_id')
                ->constrained('recurring_transactions', indexName: 'fk_rto_recurring')
                ->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->enum('status', ['scheduled', 'paid', 'skipped'])->default('scheduled');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['recurring_transaction_id', 'due_date'], 'uniq_recurring_due');
            $table->index(['status', 'due_date']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_transaction_id')->nullable()->after('loan_payment_id')
                ->constrained('recurring_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_transaction_id');
        });

        Schema::dropIfExists('recurring_transaction_occurrences');
    }
};
