<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Refines a transaction's category/beneficiary breakdown without affecting the
 * account balance — the parent transaction remains the only ledger leg.
 */
class TransactionSplit extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'category_id',
        'subcategory_id',
        'beneficiary_id',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'beneficiary_id');
    }
}
