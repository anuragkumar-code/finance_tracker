<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant groups: the master list behind the "where did you buy it" picker.
 *
 * A flat list of every shop the household has ever used is fine at ten names
 * and unusable at two hundred. Grouping them — Cabs, E-commerce, Food delivery
 * — turns the picker into something you can scan, and gives reporting a cut
 * that categories cannot: "Travel" tells you a cab and a flight were bought,
 * "Cabs" tells you the auto-rickshaw habit costs ₹3,000 a month.
 *
 * A group is deliberately NOT the same thing as a channel. Channel says how you
 * buy (ten-minute delivery vs a week's wait vs walking into a shop) and drives
 * the quick-commerce figures. Group says what kind of place it is. Blinkit can
 * sit in the E-commerce group and still count as quick commerce, which is why
 * the two are kept as separate columns rather than one folded into the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();

            // Applied to new merchants filed under this group, so the household
            // classifies a shop once instead of twice. Nullable: a group like
            // "Other" has no channel that is right for everything in it.
            $table->string('default_channel', 30)->nullable();

            // Hand-ordered rather than alphabetical: the picker should lead with
            // the groups this household actually buys from most.
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('merchants', function (Blueprint $table) {
            // Nullable, and null on delete: a merchant must survive its group
            // being retired, and a name typed into quick entry for the first
            // time is allowed to sit ungrouped until someone files it.
            $table->foreignId('merchant_group_id')
                ->nullable()
                ->after('name')
                ->constrained('merchant_groups')
                ->nullOnDelete();
        });

        $now = now();

        // Seeded here rather than in a seeder so a fresh install and an
        // existing database end up with the same starting set. Assigning
        // merchants to these groups is a separate, reviewable step —
        // `php artisan merchants:group` — because that part is a judgement
        // call about real data, not a schema change.
        DB::table('merchant_groups')->insert(collect([
            ['Cabs & rides', 'other', 10],
            ['E-commerce', 'ecommerce', 20],
            ['Food delivery', 'food_delivery', 30],
            ['Restaurants & cafés', 'offline', 40],
            ['Groceries & kirana', 'offline', 50],
            ['Fuel & transport', 'offline', 60],
            ['Bills & insurance', 'offline', 70],
            ['Subscriptions', 'subscription', 80],
            ['Health & pharmacy', 'offline', 90],
            ['Beauty & grooming', 'offline', 100],
            ['Travel & stays', 'other', 110],
            ['Home & services', 'offline', 120],
            ['Education', 'offline', 130],
            ['Other', null, 999],
        ])->map(fn (array $row) => [
            'name' => $row[0],
            'default_channel' => $row[1],
            'sort_order' => $row[2],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merchant_group_id');
        });

        Schema::dropIfExists('merchant_groups');
    }
};
