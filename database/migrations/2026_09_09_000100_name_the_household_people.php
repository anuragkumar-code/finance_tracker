<?php

use App\Models\Person;
use Illuminate\Database\Migrations\Migration;

/**
 * Replace the placeholder people with the household's actual names.
 *
 * "Me" and "Wife" only make sense when one person uses the app. Both partners
 * use this one, so "Me" is ambiguous on every screen — and "My Parents" is worse,
 * since whose parents it means depends on who is looking.
 *
 * Renames only rows still carrying the seeded defaults, so a name the household
 * has already changed is never overwritten. Renaming preserves the row id, so
 * every transaction already tagged to that person follows along.
 */
return new class extends Migration
{
    /** @var array<string, array{name: string, relationship: string}> */
    private const RENAMES = [
        'Me' => ['name' => 'Anurag', 'relationship' => 'self'],
        'Wife' => ['name' => 'Khushboo', 'relationship' => 'spouse'],
        'My Parents' => ['name' => "Anurag's Parents", 'relationship' => 'parents'],
        "Wife's Parents" => ['name' => "Khushboo's Parents", 'relationship' => 'parents'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            $person = Person::where('name', $from)->first();

            if ($person === null) {
                continue;
            }

            // Don't collide with a name the household already created themselves.
            if (Person::where('name', $to['name'])->whereKeyNot($person->getKey())->exists()) {
                continue;
            }

            $person->update($to);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $from => $to) {
            Person::where('name', $to['name'])->update(['name' => $from]);
        }
    }
};
