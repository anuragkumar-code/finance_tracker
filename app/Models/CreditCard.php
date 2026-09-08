<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'card_name',
        'credit_limit',
        'statement_day',
        'payment_due_day',
        'annual_fee',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'annual_fee' => 'decimal:2',
            'statement_day' => 'integer',
            'payment_due_day' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function statements(): HasMany
    {
        return $this->hasMany(CreditCardStatement::class)->orderByDesc('statement_date');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CreditCardPayment::class)->orderByDesc('payment_date');
    }

    /** Purchases and refunds posted to this card, of any type. */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id', 'account_id');
    }

    /**
     * What is owed right now — the card account's balance, derived from the
     * ledger like any other balance.
     */
    public function outstanding(): string
    {
        return (string) $this->account->cached_balance;
    }

    public function availableCredit(): string
    {
        $available = bcsub((string) $this->credit_limit, $this->outstanding(), 2);

        return bccomp($available, '0', 2) === -1 ? '0.00' : $available;
    }

    /** Percentage of the limit currently used. */
    public function utilisation(): float
    {
        if (bccomp((string) $this->credit_limit, '0', 2) !== 1) {
            return 0.0;
        }

        return round(((float) $this->outstanding() / (float) $this->credit_limit) * 100, 1);
    }

    /** The cycle currently in progress — purchases not yet on any statement. */
    public function currentCycleStart(): Carbon
    {
        $today = Carbon::today();
        $thisMonthStatement = $this->statementDateFor($today->year, $today->month);

        // Past this month's statement day, the open cycle began the day after it.
        return $today->greaterThan($thisMonthStatement)
            ? $thisMonthStatement->copy()->addDay()
            : $this->statementDateFor(
                $today->copy()->subMonth()->year,
                $today->copy()->subMonth()->month
            )->addDay();
    }

    /**
     * Statement day clamped to a real date — a card that bills on the 31st
     * still has a statement in February.
     */
    public function statementDateFor(int $year, int $month): Carbon
    {
        $date = Carbon::create($year, $month, 1);

        return $date->setDay(min($this->statement_day, $date->daysInMonth));
    }

    public function dueDateFor(Carbon $statementDate): Carbon
    {
        // The due day usually falls in the month after the statement.
        $due = $statementDate->copy()->addMonthNoOverflow();

        $due = $due->setDay(min($this->payment_due_day, $due->daysInMonth));

        return $due->lessThanOrEqualTo($statementDate) ? $due->addMonthNoOverflow() : $due;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
