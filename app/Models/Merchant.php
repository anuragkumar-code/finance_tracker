<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'default_category_id',
        'default_subcategory_id',
        'default_account_id',
        'default_payer_id',
        'default_beneficiary_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    public function defaultSubcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_subcategory_id');
    }

    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    public function defaultPayer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'default_payer_id');
    }

    public function defaultBeneficiary(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'default_beneficiary_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The remembered defaults for quick entry (spec section 16), as a sparse
     * array ready to merge over a new transaction's attributes.
     */
    public function quickEntryDefaults(): array
    {
        return array_filter([
            'category_id' => $this->default_category_id,
            'subcategory_id' => $this->default_subcategory_id,
            'account_id' => $this->default_account_id,
            'payer_id' => $this->default_payer_id,
            'beneficiary_id' => $this->default_beneficiary_id,
        ], fn ($value) => $value !== null);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
