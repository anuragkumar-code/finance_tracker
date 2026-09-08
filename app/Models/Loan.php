<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Enums\ScheduleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A loan described the way the household thinks about it: an EMI, a number of
 * months, and when it started. Everything else is derived.
 *
 * Note that `remaining` means cash still to pay (EMIs left x EMI amount), not
 * principal outstanding — it includes future interest. See the migration.
 */
class Loan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'lender',
        'owner_id',
        'emi_amount',
        'total_months',
        'start_date',
        'end_date',
        'due_day',
        'payment_account_id',
        'category_id',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'emi_amount' => 'decimal:2',
            'total_months' => 'integer',
            'due_day' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => LoanStatus::class,
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class)->orderBy('period_number');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    /** Whose loan this is — a label, not a permission. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'owner_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Total cash the loan will cost over its full tenure. */
    public function totalPayable(): string
    {
        return bcmul((string) $this->emi_amount, (string) $this->total_months, 2);
    }

    public function paidAmount(): string
    {
        return bcadd((string) $this->payments()->where('status', ScheduleStatus::Paid->value)->sum('amount'), '0', 2);
    }

    /** Cash still to pay — includes future interest, not principal only. */
    public function remainingAmount(): string
    {
        $remaining = bcsub($this->totalPayable(), $this->paidAmount(), 2);

        return bccomp($remaining, '0', 2) === -1 ? '0.00' : $remaining;
    }

    public function paidCount(): int
    {
        return $this->payments()->where('status', ScheduleStatus::Paid->value)->count();
    }

    public function remainingCount(): int
    {
        return max(0, $this->total_months - $this->paidCount());
    }

    public function progressPercent(): float
    {
        if ($this->total_months < 1) {
            return 0.0;
        }

        return round(($this->paidCount() / $this->total_months) * 100, 1);
    }

    /** Instalments that are due and not yet confirmed as paid. */
    public function overduePayments()
    {
        return $this->payments()
            ->where('status', ScheduleStatus::Scheduled->value)
            ->where('due_date', '<', today());
    }

    public function nextPayment(): ?LoanPayment
    {
        return $this->payments()
            ->where('status', ScheduleStatus::Scheduled->value)
            ->orderBy('due_date')
            ->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LoanStatus::Active->value);
    }
}
