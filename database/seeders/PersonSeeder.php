<?php

namespace Database\Seeders;

use App\Models\Person;
use Illuminate\Database\Seeder;

/**
 * Payers and beneficiaries from spec section 5.
 *
 * Payers are who spent the money; beneficiaries are who it was for. The two
 * lists overlap but are not identical — parents can benefit from spending
 * without ever being the payer.
 */
class PersonSeeder extends Seeder
{
    public function run(): void
    {
        $people = [
            ['name' => 'Me',             'relationship' => 'self',          'payer' => true,  'beneficiary' => true,  'household' => false],
            ['name' => 'Wife',           'relationship' => 'spouse',        'payer' => true,  'beneficiary' => true,  'household' => false],
            ['name' => 'Household',      'relationship' => 'joint',         'payer' => true,  'beneficiary' => true,  'household' => true],
            ['name' => 'My Parents',     'relationship' => 'my parents',    'payer' => false, 'beneficiary' => true,  'household' => false],
            ['name' => "Wife's Parents", 'relationship' => "wife's parents", 'payer' => false, 'beneficiary' => true,  'household' => false],
            ['name' => 'Other',          'relationship' => 'other',         'payer' => false, 'beneficiary' => true,  'household' => false],
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
