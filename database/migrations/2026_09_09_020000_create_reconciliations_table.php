<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checking the app against reality (spec section 18).
 *
 * A reconciliation records what the bank actually said versus what the app
 * calculated, and keeps that comparison as history even after the gap is
 * resolved — which is why `difference` is stored rather than derived. Later
 * activity changes the system balance, but what was true on the day you checked
 * should not move.
 *
 * The app NEVER edits a calculated balance to make it match (spec section 18 is
 * explicit). A gap is closed only by posting a visible adjustment transaction
 * that the household confirms, so every balance stays explainable by its ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->date('reconciliation_date');

            $table->decimal('actual_balance', 14, 2);
            $table->decimal('system_balance', 14, 2);
            $table->decimal('difference', 14, 2);

            $table->foreignId('adjustment_transaction_id')->nullable()
                ->constrained('transactions')->nullOnDelete();

            $table->enum('status', ['reconciled', 'discrepancy', 'resolved'])->default('reconciled');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'reconciliation_date']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('reconciliation_id')->nullable()->after('recurring_transaction_id')
                ->constrained('reconciliations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reconciliation_id');
        });

        Schema::dropIfExists('reconciliations');
    }
};
