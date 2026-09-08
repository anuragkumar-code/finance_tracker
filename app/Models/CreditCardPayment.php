<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditCardPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'credit_card_id',
        'statement_id',
        'source_account_id',
        'payment_date',
        'amount',
        'transfer_group_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(CreditCardStatement::class, 'statement_id');
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    /** The two ledger legs this payment created. */
    public function legs(): HasMany
    {
        return $this->hasMany(Transaction::class, 'credit_card_payment_id');
    }
}
