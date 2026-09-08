<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One instalment in a loan's schedule.
 *
 * Rows exist for the whole tenure from the moment the loan is created; they
 * start as 'scheduled' and become 'paid' only when confirmed.
 */
class LoanPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'period_number',
        'due_date',
        'payment_date',
        'amount',
        'status',
        'account_id',
        'transaction_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'period_number' => 'integer',
            'status' => ScheduleStatus::class,
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** The ledger entry created when this instalment was actually paid. */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isOverdue(): bool
    {
        return $this->status === ScheduleStatus::Scheduled && $this->due_date->isPast();
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', ScheduleStatus::Scheduled->value);
    }

    public function scopeDueBetween(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('due_date', [$start, $end]);
    }
}
