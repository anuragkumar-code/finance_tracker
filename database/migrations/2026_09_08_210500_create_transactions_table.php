<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The core ledger. One row = one account-affecting leg (design doc D1).
 *
 * Every row touches exactly ONE account, in ONE direction, by ONE positive
 * amount. Multi-account events (transfers, credit-card bill payments) are two
 * rows sharing a transfer_group_id, written and voided together by the service
 * layer. That is what makes "a transfer is not spending" and "a card payment is
 * not a second expense" structural rather than defensive: neither leg of those
 * events is ever type='expense'.
 *
 * Phase 1 exposes only expense / income / transfer at the application layer.
 * The remaining enum values are present now so later phases need no migration.
 * FK columns pointing at later-phase tables (credit_card_payment_id,
 * loan_payment_id, asset_id, reconciliation_id, recurring_transaction_id) are
 * added by the migrations that create those tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');

            $table->enum('type', [
                'expense', 'income', 'transfer', 'asset_purchase', 'liability_payment', 'adjustment',
            ]);
            $table->enum('leg_role', [
                'single', 'transfer_from', 'transfer_to', 'payment_from', 'payment_to',
            ])->default('single');

            // Shared by the two legs of a multi-account event.
            $table->char('transfer_group_id', 36)->nullable();

            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();

            // Always a positive magnitude; direction lives in balance_effect (design doc D2).
            $table->decimal('amount', 14, 2);
            $table->enum('balance_effect', ['increase', 'decrease']);

            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();

            $table->enum('planned_status', ['planned', 'unplanned', 'emergency'])->nullable();
            $table->enum('purpose', [
                'necessity', 'lifestyle', 'family', 'investment', 'debt', 'emergency', 'discretionary',
            ])->nullable();

            $table->string('description', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('reference', 100)->nullable();

            $table->enum('source', ['manual', 'recurring', 'import'])->default('manual');

            // Required when voiding a posted transaction (spec Rule 7).
            $table->string('void_reason', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'transaction_date']);
            $table->index(['type', 'transaction_date']);
            $table->index('transfer_group_id');
            $table->index('category_id');
            $table->index('payer_id');
            $table->index('beneficiary_id');
            $table->index('merchant_id');
        });

        // Enforce the positive-magnitude invariant in the database itself, not
        // just in validation — a negative amount would silently invert a balance.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE transactions ADD CONSTRAINT chk_transactions_amount_positive CHECK (amount > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
