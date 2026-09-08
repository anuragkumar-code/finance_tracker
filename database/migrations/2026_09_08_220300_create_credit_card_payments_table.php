<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paying a card bill. This is a liability-clearing event, not an expense
 * (spec Decision B).
 *
 * Each payment writes two linked transaction legs sharing transfer_group_id:
 * the bank account decreases, and the amount owed on the card decreases.
 * Neither leg is type='expense', so the purchases already recorded during the
 * cycle are never counted a second time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_card_id')->constrained('credit_cards')->cascadeOnDelete();
            // Nullable so an advance or ad-hoc payment need not belong to a statement.
            $table->foreignId('statement_id')->nullable()
                ->constrained('credit_card_statements')->nullOnDelete();
            $table->foreignId('source_account_id')->constrained('accounts')->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->char('transfer_group_id', 36);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('transfer_group_id');
            $table->index(['credit_card_id', 'payment_date']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('credit_card_payment_id')->nullable()->after('source')
                ->constrained('credit_card_payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_card_payment_id');
        });

        Schema::dropIfExists('credit_card_payments');
    }
};
