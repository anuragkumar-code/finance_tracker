<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per instalment for the whole tenure, generated when the loan is
 * created. This is the EMI schedule.
 *
 * A row starts as 'scheduled' and only becomes 'paid' when someone confirms it
 * (spec section 13: never silently assume a payment happened). Future rows are
 * what the Upcoming Obligations view reads.
 *
 * transaction_id is nullable on purpose: instalments paid before the household
 * started using the app are marked paid without inventing bank transactions
 * that would corrupt account history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_number');
            $table->date('due_date');
            $table->date('payment_date')->nullable();
            $table->decimal('amount', 14, 2);
            $table->enum('status', ['scheduled', 'paid', 'skipped'])->default('scheduled');
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'period_number'], 'uniq_loan_period');
            $table->index(['status', 'due_date']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('loan_payment_id')->nullable()->after('credit_card_payment_id')
                ->constrained('loan_payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_payment_id');
        });

        Schema::dropIfExists('loan_payments');
    }
};
