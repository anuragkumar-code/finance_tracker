<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'institution',
        'normal_balance',
        'opening_balance',
        'opening_balance_date',
        'cached_balance',
        'cached_balance_as_of',
        'currency',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'normal_balance' => NormalBalance::class,
            'opening_balance' => 'decimal:2',
            'opening_balance_date' => 'date',
            'cached_balance' => 'decimal:2',
            'cached_balance_as_of' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // normal_balance is derived from type, never set by hand — otherwise an
        // account could be created whose sign logic contradicts its own type.
        static::saving(function (Account $account) {
            if ($account->type instanceof AccountType) {
                $account->normal_balance = $account->type->normalBalance();
            }
        });
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isLiability(): bool
    {
        return $this->normal_balance === NormalBalance::Liability;
    }

    /**
     * Displayed balance. Reads the synchronously-maintained cache; call
     * AccountBalanceService::balance() when a guaranteed-fresh or as-of-date
     * figure is needed.
     */
    public function getBalanceAttribute(): string
    {
        return $this->cached_balance;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, AccountType|array $types): Builder
    {
        $values = collect(is_array($types) ? $types : [$types])
            ->map(fn (AccountType|string $t) => $t instanceof AccountType ? $t->value : $t)
            ->all();

        return $query->whereIn('type', $values);
    }

    /** Bank + cash only — the "money we can actually spend today" set. */
    public function scopeSpendableCash(Builder $query): Builder
    {
        return $query->whereIn('type', [AccountType::Bank->value, AccountType::Cash->value]);
    }

    public function scopeAssets(Builder $query): Builder
    {
        return $query->where('normal_balance', NormalBalance::Asset->value);
    }

    public function scopeLiabilities(Builder $query): Builder
    {
        return $query->where('normal_balance', NormalBalance::Liability->value);
    }
}
