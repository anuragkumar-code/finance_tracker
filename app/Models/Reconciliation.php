<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One check of the app against a real bank or card balance (spec section 18).
 */
class Reconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'reconciliation_date',
        'actual_balance',
        'system_balance',
        'difference',
        'adjustment_transaction_id',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'reconciliation_date' => 'date',
            'actual_balance' => 'decimal:2',
            'system_balance' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'adjustment_transaction_id');
    }

    public function matched(): bool
    {
        return bccomp((string) $this->difference, '0', 2) === 0;
    }

    /** A gap that has been seen but not yet explained or adjusted. */
    public function needsAttention(): bool
    {
        return $this->status === 'discrepancy';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'reconciled' => 'Matched',
            'discrepancy' => 'Gap found',
            'resolved' => 'Adjusted',
            default => ucfirst($this->status),
        };
    }

    public function badgeClass(): string
    {
        return match ($this->status) {
            'reconciled' => 'success',
            'discrepancy' => 'warning',
            'resolved' => 'info',
            default => 'secondary',
        };
    }
}
