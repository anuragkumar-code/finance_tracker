<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-level category hierarchy (spec section 6): a parent like "Food" with
 * children like "Groceries", "Restaurants".
 *
 * Categories are reporting metadata only — they never carry balances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->enum('applies_to', ['expense', 'income', 'both'])->default('expense');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('parent_id');
            $table->index('applies_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
