<?php

namespace App\Models;

use App\Enums\StatementStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCardStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_card_id',
        'period_start',
        'period_end',
        'statement_date',
        'statement_amount',
        'carried_balance',
        'due_date',
        'minimum_due',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'statement_date' => 'date',
            'due_date' => 'date',
            'statement_amount' => 'decimal:2',
            'carried_balance' => 'decimal:2',
            'minimum_due' => 'decimal:2',
            'status' => StatementStatus::class,
        ];
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditCardStatementItem::class, 'statement_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CreditCardPayment::class, 'statement_id');
    }

    /** The purchases this statement covers. */
    public function transactions()
    {
        return $this->hasManyThrough(
            Transaction::class,
            CreditCardStatementItem::class,
            'statement_id',
            'id',
            'id',
            'transaction_id',
        );
    }

    /** Total due including anything rolled forward. */
    public function totalDue(): string
    {
        return bcadd((string) $this->statement_amount, (string) $this->carried_balance, 2);
    }

    public function amountPaid(): string
    {
        return (string) bcadd((string) $this->payments()->sum('amount'), '0', 2);
    }

    public function balanceRemaining(): string
    {
        return bcsub($this->totalDue(), $this->amountPaid(), 2);
    }

    public function isOverdue(): bool
    {
        return ! $this->status->isSettled() && $this->due_date->isPast();
    }
}
