<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a statement to the transactions it covers — purely for traceability and
 * drill-down.
 *
 * Crucially this table holds NO financial effect of its own: generating a
 * statement inserts rows here and zero rows in `transactions`, which is what
 * spec section 4 means by "the statement itself is not a new expense".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_statement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_id')->constrained('credit_card_statements')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->decimal('amount_snapshot', 14, 2);
            $table->timestamps();

            $table->unique(['statement_id', 'transaction_id'], 'uniq_statement_txn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_statement_items');
    }
};
