<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchants remember the household's habits so quick entry can pre-fill
 * (spec section 16: "Amazon -> Shopping, HDFC Credit Card, Household").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->foreignId('default_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('default_subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('default_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('default_payer_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('default_beneficiary_id')->nullable()->constrained('people')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
