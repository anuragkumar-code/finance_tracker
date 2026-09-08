<?php

namespace App\Models;

use App\Enums\BalanceEffect;
use App\Enums\LegRole;
use App\Enums\PlannedStatus;
use App\Enums\Purpose;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One account-affecting leg of a financial event.
 *
 * Create these through the service layer (TransactionService / TransferService),
 * never directly — the services own the sign matrix, the linked-leg pairing and
 * the balance recalculation that keep the ledger consistent.
 */
class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_date',
        'type',
        'leg_role',
        'transfer_group_id',
        'account_id',
        'amount',
        'balance_effect',
        'category_id',
        'subcategory_id',
        'payer_id',
        'beneficiary_id',
        'merchant_id',
        'planned_status',
        'purpose',
        'description',
        'notes',
        'reference',
        'source',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'type' => TransactionType::class,
            'leg_role' => LegRole::class,
            'balance_effect' => BalanceEffect::class,
            'planned_status' => PlannedStatus::class,
            'purpose' => Purpose::class,
            'amount' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'payer_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'beneficiary_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** The other side of a transfer or liability payment, if this is one leg of a pair. */
    public function counterpartLeg(): ?self
    {
        if ($this->transfer_group_id === null) {
            return null;
        }

        return static::where('transfer_group_id', $this->transfer_group_id)
            ->whereKeyNot($this->getKey())
            ->first();
    }

    /** Both legs of this event (just itself, for single-leg transactions). */
    public function linkedLegs(): \Illuminate\Support\Collection
    {
        if ($this->transfer_group_id === null) {
            return collect([$this]);
        }

        return static::where('transfer_group_id', $this->transfer_group_id)->get()->collect();
    }

    /** Signed contribution of this leg to its account's balance. */
    public function signedAmount(): string
    {
        return $this->balance_effect === BalanceEffect::Increase
            ? $this->amount
            : bcsub('0', $this->amount, 2);
    }

    public function scopeOfType(Builder $query, TransactionType|array $types): Builder
    {
        $values = collect(is_array($types) ? $types : [$types])
            ->map(fn (TransactionType|string $t) => $t instanceof TransactionType ? $t->value : $t)
            ->all();

        return $query->whereIn('type', $values);
    }

    public function scopeForAccount(Builder $query, Account|int $account): Builder
    {
        return $query->where('account_id', $account instanceof Account ? $account->getKey() : $account);
    }

    public function scopeInPeriod(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('transaction_date', [$start, $end]);
    }

    public function scopeUpTo(Builder $query, $date): Builder
    {
        return $query->where('transaction_date', '<=', $date);
    }

    /**
     * The canonical "spending" row set (spec section 26). Everything the
     * dashboard and reports call spending is a filtered variant of this scope,
     * so summary figures and drill-downs can never disagree.
     */
    public function scopeSpending(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Expense->value);
    }
}
