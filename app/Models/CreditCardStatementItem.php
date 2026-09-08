<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pointer from a statement to a transaction it covers. Carries no financial
 * effect of its own — see the migration for why that matters.
 */
class CreditCardStatementItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'statement_id',
        'transaction_id',
        'amount_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'amount_snapshot' => 'decimal:2',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(CreditCardStatement::class, 'statement_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
