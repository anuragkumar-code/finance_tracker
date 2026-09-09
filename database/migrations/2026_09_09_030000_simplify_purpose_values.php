<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Collapse the purpose list from seven overlapping options to five clear ones.
 *
 * The household filled this in once and then stopped, because "lifestyle" and
 * "discretionary" meant the same thing and "emergency" duplicated the
 * planned-status field. A blunter list they actually use beats a precise one
 * they ignore.
 *
 * Existing values are remapped rather than dropped, so nothing already recorded
 * loses its meaning.
 */
return new class extends Migration
{
    private const OLD = "'necessity','lifestyle','family','investment','debt','emergency','discretionary'";
    private const NEW = "'need','want','family','investment','debt'";
    private const BOTH = "'necessity','lifestyle','family','investment','debt','emergency','discretionary','need','want'";

    /** @var array<string, string> */
    private const REMAP = [
        'necessity' => 'need',
        'emergency' => 'need',      // an unavoidable cost; urgency lives on planned_status
        'lifestyle' => 'want',
        'discretionary' => 'want',
    ];

    public function up(): void
    {
        foreach (['transactions', 'recurring_transactions'] as $table) {
            // Widen to accept old and new at once, remap, then narrow.
            DB::statement("ALTER TABLE `{$table}` MODIFY `purpose` ENUM(".self::BOTH.') NULL');

            foreach (self::REMAP as $from => $to) {
                DB::table($table)->where('purpose', $from)->update(['purpose' => $to]);
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `purpose` ENUM(".self::NEW.') NULL');
        }
    }

    public function down(): void
    {
        $reverse = ['need' => 'necessity', 'want' => 'lifestyle'];

        foreach (['transactions', 'recurring_transactions'] as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `purpose` ENUM(".self::BOTH.') NULL');

            foreach ($reverse as $from => $to) {
                DB::table($table)->where('purpose', $from)->update(['purpose' => $to]);
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `purpose` ENUM(".self::OLD.') NULL');
        }
    }
};
