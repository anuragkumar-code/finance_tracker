<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts hold money (bank, cash, investment) or represent money owed
 * (credit card). Loans are NOT accounts — see the loans table in Phase 3.
 *
 * opening_balance stores the household's starting position when they adopted
 * the app. It is deliberately never a transactions row, so it cannot leak into
 * current-period spending reports (spec Rule 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('type', [
                'bank', 'cash', 'credit_card', 'investment', 'other_asset', 'other_liability',
            ]);
            $table->string('institution', 100)->nullable();

            // Derived from `type` at creation; drives the sign matrix in AccountBalanceService.
            $table->enum('normal_balance', ['asset', 'liability']);

            // Positive magnitude. For a liability account this means "amount owed".
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->date('opening_balance_date');

            // Denormalised cache of opening_balance +/- ledger activity.
            // Always re-derivable from source rows (spec Rule 8).
            $table->decimal('cached_balance', 14, 2)->default(0);
            $table->timestamp('cached_balance_as_of')->nullable();

            $table->char('currency', 3)->default('INR');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
