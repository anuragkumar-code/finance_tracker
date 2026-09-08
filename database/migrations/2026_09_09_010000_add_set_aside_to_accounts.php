<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money that exists but is not available to spend — an emergency fund.
 *
 * A set-aside account is excluded from every "what can we spend" figure:
 * spendable cash, the dashboard's available balance, and the realistically-
 * available calculation. Counting an emergency fund as spendable is exactly how
 * a household talks itself into spending it.
 *
 * It is still counted in NET WORTH, because the household does own that money.
 * Hiding it there would understate their real position rather than protect it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('is_set_aside')->default(false)->after('is_active');
            $table->string('set_aside_reason', 100)->nullable()->after('is_set_aside');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['is_set_aside', 'set_aside_reason']);
        });
    }
};
