<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whose account, card or loan is this?
 *
 * The household is two people managing money together, so an account needs to
 * say who it belongs to — Anurag's salary account, Khushboo's card, or a joint
 * one. This is a label, not a permission: there are no logins, and every screen
 * still shows the whole household by default (spec section 5: "do not turn the
 * system into a competition between spouses").
 *
 * Credit cards get this for free, since every card is backed by an account.
 * Nullable, because an account whose owner is not recorded is still valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('institution')
                ->constrained('people')->nullOnDelete();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('lender')
                ->constrained('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
