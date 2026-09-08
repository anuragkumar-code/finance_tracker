<?php

namespace App\Models;

use App\Enums\Frequency;
use App\Enums\PlannedStatus;
use App\Enums\Purpose;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A template for a regular commitment. Posts nothing by itself — it generates
 * occurrences that someone confirms (spec section 13).
 */
class RecurringTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'amount',
        'frequency',
        'next_due_date',
        'end_date',
        'account_id',
        'category_id',
        'subcategory_id',
        'payer_id',
        'beneficiary_id',
        'merchant_id',
        'purpose',
        'planned_status',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'frequency' => Frequency::class,
            'type' => TransactionType::class,
            'purpose' => Purpose::class,
            'planned_status' => PlannedStatus::class,
            'next_due_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurringTransactionOccurrence::class)->orderBy('due_date');
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

    public function nextOccurrence(): ?RecurringTransactionOccurrence
    {
        return $this->occurrences()
            ->where('status', \App\Enums\ScheduleStatus::Scheduled->value)
            ->orderBy('due_date')
            ->first();
    }

    public function hasEnded(): bool
    {
        return $this->end_date !== null && $this->end_date->isPast();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
