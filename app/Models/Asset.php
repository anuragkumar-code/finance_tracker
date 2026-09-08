<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Something the household owns that is not an account — land, a vehicle
 * (spec section 12).
 *
 * Value is optional. An unvalued asset still earns its place on the list: with
 * a land loan running, recording the land shows what the debt bought even when
 * nobody wants to put a number on it. It simply contributes nothing to net
 * worth, and the net-worth screen says how many assets are unvalued.
 */
class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'owner_id',
        'current_value',
        'valued_on',
        'acquired_on',
        'linked_loan_id',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'current_value' => 'decimal:2',
            'valued_on' => 'date',
            'acquired_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @var array<string, string> */
    public const TYPES = [
        'land' => 'Land / Property',
        'vehicle' => 'Vehicle',
        'investment' => 'Investment',
        'jewellery' => 'Jewellery',
        'other' => 'Other',
    ];

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'owner_id');
    }

    /** The loan taken to buy this, if any. */
    public function linkedLoan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'linked_loan_id');
    }

    public function hasValue(): bool
    {
        return $this->current_value !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeValued(Builder $query): Builder
    {
        return $query->whereNotNull('current_value');
    }
}
