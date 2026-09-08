<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payers and beneficiaries in one table (design doc D8).
 *
 * The payer list (Me / Wife / Joint) is a strict subset of the beneficiary
 * list, so two near-identical tables would only add friction. The flags keep
 * the two Settings tabs distinct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('relationship', 50)->nullable();
            $table->boolean('is_household')->default(false);
            $table->boolean('can_be_payer')->default(true);
            $table->boolean('can_be_beneficiary')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
