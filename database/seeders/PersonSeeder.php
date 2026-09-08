<?php

namespace Database\Seeders;

use App\Models\Person;
use Illuminate\Database\Seeder;

/**
 * The household's people (spec section 5).
 *
 * Both partners use this app, so people are named rather than labelled "Me" and
 * "Wife" — on a shared screen "Me" is ambiguous, and "My Parents" depends on who
 * is reading it.
 *
 * Payers are who money came from; beneficiaries are who it was for. The lists
 * overlap but are not identical: parents benefit from spending without ever
 * being the payer.
 */
class PersonSeeder extends Seeder
{
    public function run(): void
    {
        $people = [
            ['name' => 'Anurag',             'relationship' => 'self',     'payer' => true,  'beneficiary' => true,  'household' => false],
            ['name' => 'Khushboo',           'relationship' => 'spouse',   'payer' => true,  'beneficiary' => true,  'household' => false],
            ['name' => 'Household',          'relationship' => 'joint',    'payer' => true,  'beneficiary' => true,  'household' => true],
            ['name' => "Anurag's Parents",   'relationship' => 'parents',  'payer' => false, 'beneficiary' => true,  'household' => false],
            ['name' => "Khushboo's Parents", 'relationship' => 'parents',  'payer' => false, 'beneficiary' => true,  'household' => false],
            ['name' => 'Other',              'relationship' => 'other',    'payer' => false, 'beneficiary' => true,  'household' => false],
        ];

        $sort = 0;

        foreach ($people as $person) {
            Person::firstOrCreate(
                ['name' => $person['name']],
                [
                    'relationship' => $person['relationship'],
                    'is_household' => $person['household'],
                    'can_be_payer' => $person['payer'],
                    'can_be_beneficiary' => $person['beneficiary'],
                    'sort_order' => $sort += 10,
                ],
            );
        }
    }
}
