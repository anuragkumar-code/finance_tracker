<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Things the household owns that are not accounts — land, a vehicle, jewellery
 * (spec section 12).
 *
 * `current_value` is NULLABLE by household decision: they did not want to be
 * forced to put a number on the land. An asset with no value is still worth
 * recording — it shows what the debt bought — but it contributes nothing to net
 * worth, and the net-worth screen says how many assets are unvalued so the
 * figure is never read as complete.
 *
 * The original design had a required purchase_value; that is dropped for the
 * same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('type', 50)->default('other');
            $table->foreignId('owner_id')->nullable()->constrained('people')->nullOnDelete();

            // Optional: an asset with no value still belongs on the list.
            $table->decimal('current_value', 14, 2)->nullable();
            $table->date('valued_on')->nullable();
            $table->date('acquired_on')->nullable();

            // Land bought with the land loan, bike bought with the bike loan.
            $table->foreignId('linked_loan_id')->nullable()->constrained('loans')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
