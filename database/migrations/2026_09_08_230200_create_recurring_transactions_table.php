<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regular commitments — rent, utilities, subscriptions, family support
 * (spec section 13).
 *
 * A recurring transaction is a template. It never posts anything by itself; it
 * generates dated occurrences that someone confirms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->enum('type', ['expense', 'income'])->default('expense');
            $table->decimal('amount', 14, 2);
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->date('next_due_date');
            $table->date('end_date')->nullable();

            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();

            $table->enum('purpose', [
                'necessity', 'lifestyle', 'family', 'investment', 'debt', 'emergency', 'discretionary',
            ])->nullable();
            $table->enum('planned_status', ['planned', 'unplanned', 'emergency'])->default('planned');

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'next_due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
