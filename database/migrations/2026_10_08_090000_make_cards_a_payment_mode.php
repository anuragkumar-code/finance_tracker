<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Credit cards become a mode of payment, not a debt to track.
 *
 * With bank balances already gone, "what do I owe on cards" was the last
 * balance the household did not actually want: the question being asked of
 * this app is how much goes out each month and where it goes, and a card is
 * simply one of the ways it leaves. The card's own statement answers the
 * rest, and answers it better.
 *
 * What stays: every entry keeps naming the card it was paid with, so spending
 * can still be split by mode of payment. What goes: the derived outstanding
 * figure, utilisation, statements and bill reminders.
 *
 * Nothing is deleted here either. The credit_cards, statement and payment rows
 * remain, along with the eight bill-payment legs already in the ledger — they
 * are simply no longer the basis of a figure anyone reads. Setting the column
 * back to 1 restores the old behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')->where('type', 'credit_card')->update(['tracks_balance' => false]);
    }

    public function down(): void
    {
        DB::table('accounts')->where('type', 'credit_card')->update(['tracks_balance' => true]);
    }
};
