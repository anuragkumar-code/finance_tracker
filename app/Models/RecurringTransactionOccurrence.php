<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringTransactionOccurrence extends Model
{
    use HasFactory;

    protected $fillable = [
        'recurring_transaction_id',
        'due_date',
        'amount',
        'status',
        'transaction_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'status' => ScheduleStatus::class,
        ];
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class);
    }

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
}
