<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A credit card is the card-specific detail attached to an existing account of
 * type 'credit_card'. The balance owed lives on that account, derived from the
 * ledger like any other — this table only adds limit, cycle and due dates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained('accounts')->cascadeOnDelete();
            $table->string('card_name', 100);
            $table->decimal('credit_limit', 14, 2);
            $table->unsignedTinyInteger('statement_day');
            $table->unsignedTinyInteger('payment_due_day');
            $table->decimal('annual_fee', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_cards');
    }
};
