<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A statement groups purchases that were already recorded during its cycle.
 *
 * statement_amount is a SNAPSHOT taken at generation time, deliberately not a
 * live sum: once the bank has issued a statement, its figure is a fact of
 * history and must not drift when an old transaction is later edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_card_id')->constrained('credit_cards')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('statement_date');
            $table->decimal('statement_amount', 14, 2)->default(0);
            $table->decimal('carried_balance', 14, 2)->default(0);
            $table->date('due_date');
            $table->decimal('minimum_due', 14, 2)->nullable();
            $table->enum('status', ['open', 'generated', 'partially_paid', 'paid', 'overdue'])
                ->default('generated');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['credit_card_id', 'period_start', 'period_end'], 'uniq_card_cycle');
            $table->index(['credit_card_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_statements');
    }
};
