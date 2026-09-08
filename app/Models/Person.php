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
        'can_be_payer',
        'can_be_beneficiary',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_household' => 'boolean',
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

    public function scopePayers(Builder $query): Builder
    {
        return $query->where('can_be_payer', true);
    }

    public function scopeBeneficiaries(Builder $query): Builder
    {
        return $query->where('can_be_beneficiary', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
