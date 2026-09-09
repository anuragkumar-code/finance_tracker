<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the household buys from a merchant, so quick-commerce spending can be
 * seen on its own.
 *
 * Category already says what was bought. This says how: Blinkit groceries and a
 * supermarket run are both "Food", but ten-minute delivery is a different habit
 * with a different cost, and it hides inside category totals otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->enum('channel', [
                'quick_commerce', 'ecommerce', 'food_delivery', 'offline', 'subscription', 'other',
            ])->default('offline')->after('name');

            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropIndex(['channel']);
            $table->dropColumn('channel');
        });
    }
};
