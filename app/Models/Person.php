<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Payers and beneficiaries share this table (design doc D8); the flags decide
 * which pickers a person appears in.
 */
class Person extends Model
{
    use HasFactory;

    protected $table = 'people';

    protected $fillable = [
        'name',
        'relationship',
        'is_household',
        'is_external',
        'can_be_payer',
        'can_be_beneficiary',
        'is_active',
        'sort_order',
    ];

    protected static function booted(): void
    {
        // The balance account is labelled with the person's name wherever it
        // appears in the ledger ("Paid with: Rahul"), so a rename has to carry
        // through or old entries would show a name nobody recognises.
        static::updated(function (Person $person) {
            if ($person->wasChanged('name')) {
                Account::where('person_id', $person->id)->update(['name' => $person->name]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_household' => 'boolean',
            'is_external' => 'boolean',
            'can_be_payer' => 'boolean',
            'can_be_beneficiary' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function transactionsPaid(): HasMany
    {
        return $this->hasMany(Transaction::class, 'payer_id');
    }

    public function transactionsBenefited(): HasMany
    {
        return $this->hasMany(Transaction::class, 'beneficiary_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Friends are never offered as "who paid" or "who it was for": those
     * pickers describe the household's own money. A friend's part in a cost is
     * expressed through their balance instead.
     */
    public function scopePayers(Builder $query): Builder
    {
        return $query->where('can_be_payer', true)->where('is_external', false);
    }

    public function scopeBeneficiaries(Builder $query): Builder
    {
        return $query->where('can_be_beneficiary', true)->where('is_external', false);
    }

    /** Friends and relatives outside the household who can owe or be owed. */
    public function scopeExternal(Builder $query): Builder
    {
        return $query->where('is_external', true);
    }

    /** The account holding what this person owes the household, if one exists yet. */
    public function balanceAccount(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Account::class, 'person_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
