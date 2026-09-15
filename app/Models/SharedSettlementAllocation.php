<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How much one expense row was reduced by one settlement.
 *
 * The amount taken off is stored rather than the amount the row had before, so
 * undoing adds it back. That stays correct even when two settlements have
 * reduced the same row and are undone in the opposite order.
 */
class SharedSettlementAllocation extends Model
{
    protected $fillable = [
        'shared_settlement_id',
        'transaction_id',
        'reduced_by',
    ];

    protected function casts(): array
    {
        return [
            'reduced_by' => 'decimal:2',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(SharedSettlement::class, 'shared_settlement_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class)->withTrashed();
    }
}
